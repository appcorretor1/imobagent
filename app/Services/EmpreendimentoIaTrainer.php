<?php

namespace App\Services;

use App\Models\Empreendimento;
use App\Models\KnowledgeAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use OpenAI;

class EmpreendimentoIaTrainer
{
    /**
     * Treina a IA com base em um asset PDF específico.
     */
    public function trainFromAsset(KnowledgeAsset $asset): ?array
    {
        // só faz sentido para PDFs
        if ($asset->kind !== 'pdf' && $asset->mime !== 'application/pdf') {
            Log::info('ia_trainer.skip_not_pdf', [
                'asset_id' => $asset->id,
                'kind'     => $asset->kind,
                'mime'     => $asset->mime,
            ]);
            return null;
        }

        $emp = Empreendimento::find($asset->empreendimento_id);
        if (! $emp) {
            Log::warning('ia_trainer.emp_not_found', ['asset_id' => $asset->id]);
            return null;
        }

        if (! Storage::disk($asset->disk)->exists($asset->path)) {
            Log::warning('ia_trainer.file_not_found', [
                'asset_id' => $asset->id,
                'disk'     => $asset->disk,
                'path'     => $asset->path,
            ]);
            return null;
        }

        $binary = Storage::disk($asset->disk)->get($asset->path);
        $text   = $this->extractTextFromPdf($binary);

        // se o texto veio vazio ou muito curto
        if (! $text || mb_strlen(trim($text)) < 50) {
            $payload = [
                'resumo'  => null,
                'topicos' => [],
                'faqs'    => [],
                'healthcheck' => [
                    'tem_dados_relevantes' => false,
                    'motivo' => 'Texto extraído vazio ou muito curto. O PDF pode ser apenas imagem ou estar protegido.',
                ],
                'fonte' => [
                    'asset_id'       => $asset->id,
                    'original_name'  => $asset->original_name,
                ],
            ];

            $emp->contexto_ia = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $emp->texto_ia    = $this->buildTextoIa($payload, $emp);
            $emp->save();

            return $payload;
        }

        // chama IA
        $json = $this->callAi($text, $emp, $asset);

        // persiste no empreendimento
        $emp->contexto_ia = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $emp->texto_ia    = $this->buildTextoIa($json, $emp);
        $emp->save();

        return $json;
    }

    private function extractTextFromPdf(string $binary): string
    {
        try {
            $tmp = tmpfile();
            fwrite($tmp, $binary);
            $meta = stream_get_meta_data($tmp);
            $path = $meta['uri'];

            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            $text   = $pdf->getText();

            fclose($tmp);

            return $text ?: '';
        } catch (\Throwable $e) {
            Log::warning('PDF_PARSE_ERROR', ['error' => $e->getMessage()]);
            return '';
        }
    }

   private function callAi(string $text, Empreendimento $emp, KnowledgeAsset $asset): array
{
    // limita tamanho do texto pra não explodir tokens
    $maxChars = 15000;
    if (mb_strlen($text) > $maxChars) {
        $text = mb_substr($text, 0, $maxChars) . "\n\n[Texto truncado para análise]";
    }

    $systemPrompt = <<<PROMPT
Você é uma IA especialista em empreendimentos imobiliários.

Sua função é:
- Ler o texto bruto extraído de um PDF de empreendimento.
- Gerar um JSON com resumo, tópicos, FAQs e um healthcheck.
- NÃO inventar dados financeiros não presentes no texto.

Responda sempre em JSON válido.
PROMPT;

    $userPrompt = <<<PROMPT
Você recebeu o texto bruto extraído do PDF "{$asset->original_name}" do empreendimento "{$emp->nome}".

Gere uma resposta em JSON com a seguinte estrutura EXATA:

{
  "resumo": "texto curto resumindo o empreendimento (5 a 8 linhas)",
  "topicos": [
    "bullet point 1",
    "bullet point 2"
  ],
  "faqs": [
    {
      "pergunta": "Pergunta importante sobre o empreendimento",
      "resposta": "Resposta objetiva que um corretor usaria com o cliente"
    }
  ],
  "healthcheck": {
    "tem_dados_relevantes": true,
    "motivo": "Explique rapidamente porque considera que o PDF tem (ou não) informações úteis"
  },
  "fonte": {
    "asset_id": {$asset->id},
    "original_name": "{$asset->original_name}"
  }
}

Regras:
- Responda apenas o JSON, sem texto antes ou depois.
- Não invente dados financeiros que não apareçam no texto.

--- TEXTO DO PDF ---

{$text}
PROMPT;

    $client = OpenAI::client(config('services.openai.key'));

    // 🔁 Usando Chat Completions, não Responses API
    $response = $client->chat()->create([
        'model' => 'gpt-4.1-mini',
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role'    => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role'    => 'user',
                'content' => $userPrompt,
            ],
        ],
    ]);

    $content = $response->choices[0]->message->content ?? '{}';

    $json = json_decode($content, true);
    if (! is_array($json)) {
        Log::warning('AI_JSON_PARSE_ERROR', ['raw' => $content]);
        throw new \RuntimeException('A IA não retornou um JSON válido.');
    }

    $json['resumo']      = $json['resumo']      ?? null;
    $json['topicos']     = $json['topicos']     ?? [];
    $json['faqs']        = $json['faqs']        ?? [];
    $json['healthcheck'] = $json['healthcheck'] ?? [
        'tem_dados_relevantes' => true,
        'motivo' => 'Healthcheck não especificado no JSON.',
    ];
    $json['fonte'] = $json['fonte'] ?? [
        'asset_id'      => $asset->id,
        'original_name' => $asset->original_name,
    ];

    return $json;
}

    private function buildTextoIa(array $data, Empreendimento $emp): string
    {
        $resumo  = $data['resumo'] ?? '';
        $topicos = $data['topicos'] ?? [];
        $faqs    = $data['faqs'] ?? [];
        $health  = $data['healthcheck'] ?? [];
        $fonte   = $data['fonte'] ?? [];

        $out = [];

        $out[] = "# Contexto IA – {$emp->nome}";

        if (!empty($fonte['original_name'])) {
            $out[] = "_Baseado no PDF: **{$fonte['original_name']}**_";
        }

        if ($resumo) {
            $out[] = "## Resumo do empreendimento\n\n" . $resumo;
        }

        if (! empty($topicos)) {
            $out[] = "## Principais tópicos";
            foreach ($topicos as $t) {
                $out[] = "- " . $t;
            }
        }

        if (! empty($faqs)) {
            $out[] = "## Perguntas frequentes importantes";
            foreach ($faqs as $faq) {
                $pergunta = $faq['pergunta'] ?? '';
                $resposta = $faq['resposta'] ?? '';
                if ($pergunta && $resposta) {
                    $out[] = "**Pergunta:** {$pergunta}\n\n**Resposta:** {$resposta}\n";
                }
            }
        }

        if (! empty($health)) {
            $status = $health['tem_dados_relevantes'] ?? null;
            $motivo = $health['motivo'] ?? null;

            if (! is_null($status)) {
                $out[] = "## Healthcheck do PDF\n\n".
                    "- Tem dados relevantes: " . ($status ? 'Sim' : 'Não') . "\n" .
                    ($motivo ? "- Motivo: {$motivo}\n" : '');
            }
        }

        return implode("\n\n", $out);
    }
}
