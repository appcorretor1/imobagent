<?php

namespace App\Http\Controllers;

use App\Models\{Empreendimento, KnowledgeAsset};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    /**
     * Lista os arquivos do empreendimento + formulário de upload.
     */
    public function index(Empreendimento $e)
    {
        $assets = KnowledgeAsset::where('empreendimento_id', $e->id)
            ->latest()
            ->get();

        return view('admin.assets.index', compact('e', 'assets'));
    }

    /**
     * Recebe o upload e cria o registro no banco.
     */
    public function store(Request $r, Empreendimento $e)
    {
        $r->validate([
            'files.*' => ['required','file','max:204800'], // 200MB
        ]);

        // Use S3 por padrão; permite trocar via .env
        $disk = env('ASSETS_DISK', config('filesystems.default', 'local'));
        $dir  = "documentos/tenants/{$e->company_id}/empreendimentos/{$e->id}";

        // Log inicial para debug
        \Log::info('assets.upload.start', [
            'empreendimento_id' => $e->id,
            'company_id'        => $e->company_id,
            'disk'              => $disk,
            'dir'               => $dir,
            'qtde_files'        => count($r->file('files', [])),
        ]);

        // Verifica se o disk existe
        if (!config("filesystems.disks.{$disk}")) {
            \Log::error('assets.upload.invalid_disk', ['disk' => $disk]);
            return back()->with('error', "Disco de armazenamento inválido: {$disk}");
        }

        foreach ($r->file('files', []) as $file) {
            $origName = $file->getClientOriginalName();
            $safeName = \Str::slug(pathinfo($origName, PATHINFO_FILENAME));
            $ext      = strtolower($file->getClientOriginalExtension());
            $final    = $safeName . ($ext ? ".{$ext}" : '');

            try {
                // 1) putFileAs (S3/private por padrão; pode usar 'visibility' => 'private')
                $storedPath = Storage::disk($disk)->putFileAs($dir, $file, $final, [
                    'visibility' => 'private',
                ]);

                // 2) Fallback com put()
                if (!$storedPath) {
                    \Log::warning('assets.upload.putFileAs.null', compact('disk','dir','final','origName'));
                    $contents   = file_get_contents($file->getRealPath());
                    $stored     = Storage::disk($disk)->put("{$dir}/{$final}", $contents, ['visibility' => 'private']);
                    $storedPath = $stored ? "{$dir}/{$final}" : null;
                }

                if (!$storedPath || !\is_string($storedPath)) {
                    \Log::error('assets.upload.return_invalid', compact('disk','dir','final','origName'));
                    return back()->with('error', 'Falha no upload (retorno inválido do Storage). Tente novamente.');
                }

                $size = (int) $file->getSize();
                if ($size <= 0) {
                    Storage::disk($disk)->delete($storedPath);
                    \Log::error('assets.upload.empty_size', compact('storedPath','origName'));
                    return back()->with('error', 'Arquivo vazio ou tamanho inválido.');
                }

                $mime = $file->getMimeType() ?: 'application/octet-stream';

                $asset = KnowledgeAsset::create([
                    'company_id'        => $e->company_id,
                    'empreendimento_id' => $e->id,
                    'original_name'     => $origName,
                    'mime'              => $mime,
                    'disk'              => $disk,        // <- IMPORTANTE
                    'path'              => $storedPath,  // <- IMPORTANTE
                    'kind' => $this->detectKind($file->getMimeType(), $ext),
                    'status'            => 'pending',
                    'size'              => $size,
                ]);

                \Log::info('assets.upload.saved', [
                    'asset_id'   => $asset->id,
                    'disk'       => $disk,
                    'path'       => $storedPath,
                    'mime'       => $mime,
                    'kind'       => $asset->kind,
                ]);

                // dispara ingestão (job usa assinatura legada: ($vsId, $asset))
                \App\Jobs\IngestKnowledgeAsset::dispatch($asset->id);

            } catch (\Throwable $ex) {
                \Log::error('assets.upload.exception', [
                    'msg'      => $ex->getMessage(),
                    'disk'     => $disk,
                    'dir'      => $dir,
                    'final'    => $final,
                    'origName' => $origName,
                ]);
                return back()->with('error', 'Erro no upload: '.$ex->getMessage());
            }
        }

        return back()->with('ok', 'Arquivos enviados! Estamos processando com a IA.');
    }

    /**
     * Detecta o tipo de conteúdo com base no MIME (e extensão como apoio).
     */
    private function detectKind(string $mime, ?string $ext = null): string
    {
        $ext = strtolower((string)$ext);

        if (str_starts_with($mime, 'image/'))  return 'image';
        if (str_starts_with($mime, 'audio/'))  return 'audio';
        if (str_starts_with($mime, 'video/'))  return 'video';

        // Documentos comuns
        $officeDoc = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/csv',
            'text/plain',
            'application/pdf',
        ];
        if (in_array($mime, $officeDoc, true)) {
            return match ($mime) {
                'application/pdf' => 'pdf',
                default           => 'doc',
            };
        }

        // fallback por extensão
        if (in_array($ext, ['pdf'])) return 'pdf';
        if (in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx','csv','txt'])) return 'doc';

        return 'doc';
    }

    /**
     * Exibe um arquivo direto do storage via URL temporária.
     */
    public function show(Empreendimento $e, KnowledgeAsset $asset)
    {
        abort_unless($asset->empreendimento_id === $e->id, 403);

        $url = Storage::disk($asset->disk)
            ->temporaryUrl($asset->path, now()->addMinutes(10));

        return redirect()->away($url);
    }

    /**
     * Gera download direto com URL temporária.
     */
    public function download(Empreendimento $e, KnowledgeAsset $asset)
    {
        abort_unless($asset->empreendimento_id === $e->id, 403);

        $url = Storage::disk($asset->disk)
            ->temporaryUrl($asset->path, now()->addMinutes(10));

        return redirect()->away($url);
    }

    /**
     * Exclui o asset (do storage e do banco).
     */
    public function destroy(Empreendimento $e, KnowledgeAsset $asset)
    {
        abort_unless($asset->empreendimento_id === $e->id, 403);

        try {
            if (Storage::disk($asset->disk)->exists($asset->path)) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
            $asset->delete();

            return back()->with('ok', 'Arquivo excluído com sucesso!');
        } catch (\Throwable $ex) {
            return back()->with('error', 'Erro ao excluir arquivo: ' . $ex->getMessage());
        }
    }
}
