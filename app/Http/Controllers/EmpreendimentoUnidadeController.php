<?php

namespace App\Http\Controllers;

use App\Models\Empreendimento;
use App\Models\EmpreendimentoUnidade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmpreendimentoUnidadeController extends Controller
{
    /**
     * Lista as unidades de um empreendimento.
     */
  public function index(Empreendimento $empreendimento)
{
    $unidades = $empreendimento->unidades()
        ->orderBy('grupo_unidade')
        ->orderBy('unidade')
        ->get();

    // Opções de status para o select na view
    $statusOptions = [
        EmpreendimentoUnidade::STATUS_LIVRE     => 'Livre',
        EmpreendimentoUnidade::STATUS_RESERVADO => 'Reservado',
        EmpreendimentoUnidade::STATUS_FECHADO   => 'Fechado',
    ];

    return view('admin.empreendimentos.unidades.index', [
        'empreendimento' => $empreendimento,
        'unidades'       => $unidades,
        'statusOptions'  => $statusOptions,
    ]);
}


    /**
     * Cria uma unidade manualmente (form simples).
     */
    public function store(Request $request, Empreendimento $empreendimento)
    {
        $data = $request->validate([
            'grupo_unidade' => ['nullable', 'string', 'max:100'],
            'unidade'       => ['required', 'string', 'max:100'],
            'status'        => ['required', 'in:livre,reservado,fechado'],
        ]);

        $data['empreendimento_id'] = $empreendimento->id;

        EmpreendimentoUnidade::create($data);

        return redirect()
            ->route('admin.empreendimentos.unidades.index', $empreendimento->id)
            ->with('ok', 'Unidade criada com sucesso.');
    }

    /**
     * Atualiza uma unidade (nome, grupo, status).
     */
    public function update(Request $request, Empreendimento $empreendimento, EmpreendimentoUnidade $unidade)
    {
        // garante que a unidade pertence ao empreendimento
        if ($unidade->empreendimento_id !== $empreendimento->id) {
            abort(404);
        }

        $data = $request->validate([
            'grupo_unidade' => ['nullable', 'string', 'max:100'],
            'unidade'       => ['required', 'string', 'max:100'],
            'status'        => ['required', 'in:livre,reservado,fechado'],
        ]);

        $unidade->update($data);

        return redirect()
            ->route('admin.empreendimentos.unidades.index', $empreendimento->id)
            ->with('ok', 'Unidade atualizada com sucesso.');
    }

    /**
     * Atualiza status de várias unidades de uma vez (tela com select).
     */
    public function bulkUpdateStatus(Request $request, Empreendimento $empreendimento)
    {
        $data = $request->validate([
            'unidades'   => ['required', 'array'],
            'unidades.*' => ['required', 'in:livre,reservado,fechado'],
        ]);

        foreach ($data['unidades'] as $id => $status) {
            EmpreendimentoUnidade::where('empreendimento_id', $empreendimento->id)
                ->where('id', $id)
                ->update(['status' => $status]);
        }

        return redirect()
            ->route('admin.empreendimentos.unidades.index', $empreendimento->id)
            ->with('ok', 'Status das unidades atualizado.');
    }

    /**
     * Remove uma unidade.
     */
    public function destroy(Empreendimento $empreendimento, EmpreendimentoUnidade $unidade)
    {
        if ($unidade->empreendimento_id !== $empreendimento->id) {
            abort(404);
        }

        $unidade->delete();

        return redirect()
            ->route('admin.empreendimentos.unidades.index', $empreendimento->id)
            ->with('ok', 'Unidade removida com sucesso.');
    }

    /**
     * Importa unidades a partir de uma planilha.
     *
     * Formato esperado de cabeçalho (flexível):
     * - coluna de unidade: "unidade", "apto", "apartamento", "lote", "casa"
     * - coluna de grupo:   "grupo_unidade", "torre", "bloco", "quadra", "ala", "setor", "modulo", "módulo", "alameda"
     * - coluna de status:  "status", "situacao", "situação"
     */
    public function import(Request $request, Empreendimento $empreendimento)
{
    // 1) Validação: o nome do campo TEM QUE ser "arquivo" (igual no Blade)
  $data = $request->validate([
        'arquivo' => [
            'required',
            'file',
            // Aceita CSV/xlsx/xls e também txt (muito CSV vem assim)
            'mimes:csv,txt,xlsx,xls',
        ],
    ]);
    $file = $data['arquivo'];
    $path = $file->getRealPath();

    // 2) Carrega a planilha
    $spreadsheet = IOFactory::load($path);
    $sheet       = $spreadsheet->getActiveSheet();
    $rows        = $sheet->toArray(null, true, true, true);

    if (empty($rows)) {
        return back()->with('error', 'Planilha vazia.');
    }

    // 3) Descobrir cabeçalho (linha com nome das colunas)
    $headerIndex = null;
    $mapCols     = [
        'grupo_unidade' => null,
        'unidade'       => null,
        'status'        => null,
    ];

    foreach ($rows as $idx => $row) {
        $normalized = [];
        foreach ($row as $col => $val) {
            $normalized[$col] = $this->normalizeHeader($val);
        }

        // Se tiver ao menos "unidade", consideramos cabeçalho
        if (in_array('unidade', $normalized, true) ||
            in_array('apto', $normalized, true) ||
            in_array('apartamento', $normalized, true) ||
            in_array('lote', $normalized, true) ||
            in_array('casa', $normalized, true)) {

            $headerIndex = $idx;

            foreach ($normalized as $col => $txt) {
                // unidade
                if (in_array($txt, ['unidade','apto','apartamento','lote','casa'], true)) {
                    $mapCols['unidade'] = $col;
                }

                // grupo_unidade
                if (in_array($txt, [
                    'grupo_unidade','torre','bloco','quadra',
                    'ala','setor','modulo','modulo_','modulo_unidade','alameda'
                ], true)) {
                    $mapCols['grupo_unidade'] = $col;
                }

                // status
                if (in_array($txt, ['status','situacao','situacao_','situacao_unidade'], true)) {
                    $mapCols['status'] = $col;
                }
            }

            break;
        }
    }

    if ($headerIndex === null || !$mapCols['unidade']) {
        return back()->with('error', 'Não consegui identificar a coluna de "unidade" na planilha.');
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;

    // 4) Ler linhas de dados após o cabeçalho
    foreach ($rows as $idx => $row) {
        if ($idx <= $headerIndex) {
            continue;
        }

        $unidadeVal = $row[$mapCols['unidade']] ?? null;
        $unidade    = trim((string) $unidadeVal);

        if ($unidade === '') {
            $skipped++;
            continue;
        }

        $grupoVal = $mapCols['grupo_unidade']
            ? ($row[$mapCols['grupo_unidade']] ?? null)
            : null;

        $grupo = $grupoVal !== null ? trim((string) $grupoVal) : null;
        if ($grupo === '') {
            $grupo = null;
        }

        $statusVal = $mapCols['status']
            ? ($row[$mapCols['status']] ?? null)
            : null;

        $status = $this->normalizeStatus($statusVal);

        // upsert por (empreendimento_id, grupo_unidade, unidade)
        $existing = EmpreendimentoUnidade::where('empreendimento_id', $empreendimento->id)
            ->where('unidade', $unidade)
            ->where(function($q) use ($grupo) {
                if ($grupo !== null) {
                    $q->where('grupo_unidade', $grupo);
                } else {
                    $q->whereNull('grupo_unidade');
                }
            })
            ->first();

        if ($existing) {
            $existing->status        = $status;
            $existing->grupo_unidade = $grupo;
            $existing->unidade       = $unidade;
            $existing->save();
            $updated++;
        } else {
            EmpreendimentoUnidade::create([
                'empreendimento_id' => $empreendimento->id,
                'grupo_unidade'     => $grupo,
                'unidade'           => $unidade,
                'status'            => $status,
            ]);
            $created++;
        }
    }

    Log::info('Import de unidades concluído', [
        'empId'   => $empreendimento->id,
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
    ]);

    return redirect()
        ->route('admin.empreendimentos.unidades.index', $empreendimento->id)
        ->with('ok', "Importação concluída. Criadas: {$created}, Atualizadas: {$updated}, Ignoradas: {$skipped}.");
}

    /**
     * Normaliza cabeçalho: minúsculo, sem acento, espaços -> underline.
     */
    private function normalizeHeader($val): string
    {
        $txt = trim((string) $val);
        $txt = mb_strtolower($txt);

        if (class_exists('\Normalizer')) {
            $txt = \Normalizer::normalize($txt, \Normalizer::FORM_D);
            $txt = preg_replace('/\p{Mn}+/u', '', $txt);
        }

        $txt = preg_replace('/\s+/', '_', $txt);

        return $txt;
    }

    /**
     * Normaliza valor de status vindo da planilha.
     * Aceita variações, default = 'livre'.
     */
    private function normalizeStatus($raw): string
    {
        $txt = trim(mb_strtolower((string) $raw));

        if ($txt === '') {
            return EmpreendimentoUnidade::STATUS_LIVRE;
        }

        if (in_array($txt, ['livre','disponivel','disponível','vago'], true)) {
            return EmpreendimentoUnidade::STATUS_LIVRE;
        }

        if (in_array($txt, ['reservado','reserva','bloqueado'], true)) {
            return EmpreendimentoUnidade::STATUS_RESERVADO;
        }

        if (in_array($txt, ['fechado','vendido','assinado','contratado'], true)) {
            return EmpreendimentoUnidade::STATUS_FECHADO;
        }

        // fallback
        return EmpreendimentoUnidade::STATUS_LIVRE;
    }



    public function downloadTemplate(Empreendimento $empreendimento)
{
    $fileName = 'modelo_unidades_empreendimento_' . $empreendimento->id . '.csv';

    $headers = [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
    ];

    $callback = function () {
        $out = fopen('php://output', 'w');

        // BOM para o Excel reconhecer UTF-8
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        // Cabeçalho
        fputcsv($out, ['grupo_unidade', 'unidade', 'status'], ';');

        // Linhas de exemplo (modelo)
        fputcsv($out, ['Torre 1', '101', 'livre'], ';');
        fputcsv($out, ['Torre 1', '102', 'livre'], ';');
        fputcsv($out, ['Torre 2', '101', 'reservado'], ';');
        fputcsv($out, ['Quadra A', 'Casa 08', 'livre'], ';');
        fputcsv($out, ['Alameda Azul', 'Casa 01', 'fechado'], ';');
        fputcsv($out, ['—', '201', 'livre'], ';');

        fclose($out);
    };

    return response()->stream($callback, 200, $headers);
}
}
