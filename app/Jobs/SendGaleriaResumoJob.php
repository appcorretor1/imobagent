<?php

namespace App\Jobs;

use App\Models\Empreendimento;
use App\Models\EmpreendimentoMidia;
use App\Models\WhatsappThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendGaleriaResumoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public int $empreendimentoId;
    public int $corretorId;

    public function __construct(string $phone, int $empreendimentoId, int $corretorId)
    {
        $this->phone           = $phone;
        $this->empreendimentoId = $empreendimentoId;
        $this->corretorId      = $corretorId;
    }

    public function handle(): void
    {
        $phone           = $this->phone;
        $empreendimentoId = $this->empreendimentoId;
        $corretorId      = $this->corretorId;

        $batchKey = "galeria:batch:{$phone}:{$empreendimentoId}:{$corretorId}";

        $batch = Cache::get($batchKey);
        if (!$batch || empty($batch['count'])) {
            // nada acumulado, nada a fazer
            return;
        }

        // Se teve atualização MUITO recente, significa que outro Job
        // mais novo ainda vai rodar → este aqui aborta
        $lastAt = $batch['last_at'] ?? null;
        if ($lastAt) {
            try {
                $lastAtCarbon = now()->parse($lastAt);
            } catch (\Throwable $e) {
                $lastAtCarbon = null;
            }

            if ($lastAtCarbon && $lastAtCarbon->gt(now()->subSeconds(4))) {
                // ainda está "quente" → tem mensagem mais recente no pipeline
                return;
            }
        }

        // Se chegou aqui, é porque passou a janela de 4–5s sem novas mídias
        $loteCount = (int) $batch['count'];

        // Limpa o batch para evitar repetir mensagem
        Cache::forget($batchKey);

        // Calcula total atual de mídias no empreendimento para esse corretor
        $totalAtual = EmpreendimentoMidia::where('empreendimento_id', $empreendimentoId)
            ->where('corretor_id', $corretorId)
            ->count();

        $emp = Empreendimento::find($empreendimentoId);
        $nomeEmp = $emp?->nome ?? 'empreendimento';

        $urlGaleria = route('galeria.publica', [
            'empreendimentoId' => $empreendimentoId,
            'corretorId'       => $corretorId,
        ]);

        $mensagem  = "✅ Salvei *{$loteCount} arquivo(s)* na sua galeria do: *{$nomeEmp}*.\n";
        $mensagem .= "📸 Agora já são *{$totalAtual} arquivo(s)* salvos.\n\n";
        $mensagem .= "🔗 Link da sua galeria:\n{$urlGaleria}";

        // Como estamos num Job, precisamos de uma forma de enviar WhatsApp.
        // Se o método sendText estiver no WppController, podemos expor um service estático
        // ou usar um pequeno helper aqui. Vou assumir que você tem um serviço de envio:

        app(\App\Services\WppSender::class)->sendText($phone, $mensagem);
    }
}
