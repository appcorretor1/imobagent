<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use OpenAI;

class VectorStoreService
{
    /** Client OpenAI */
    public function client()
    {
        $key = config('services.openai.key') ?: env('OPENAI_API_KEY');
        return OpenAI::client($key);
    }

    /** Garante VS para o empreendimento (reaproveita o mais comum dos assets) */
    public function ensureVectorStoreForEmpreendimento(int $empId): string
    {
        $rows = DB::table('knowledge_assets')
            ->where('empreendimento_id', $empId)
            ->whereNotNull('openai_vector_store_id')
            ->pluck('openai_vector_store_id')
            ->filter()
            ->all();

        if (!empty($rows)) {
            $counts = array_count_values($rows);
            arsort($counts);
            return array_key_first($counts);
        }

        $client = $this->client();
        $vs = $client->vectorStores()->create(['name' => "emp_{$empId}_vs"]);
        return $vs->id;
    }

    /** Anexa todos os openai_file_id READY ao VS */
    public function syncEmpreendimentoAssetsToVS(int $empId, string $vsId): array
    {
        $client = $this->client();

        $files = DB::table('knowledge_assets')
            ->where('empreendimento_id', $empId)
            ->where('status', 'ready')
            ->whereNotNull('openai_file_id')
            ->pluck('openai_file_id')->filter()->unique()->values()->all();

        if (empty($files)) {
            Log::info('VS sync: nenhum arquivo pronto', ['empId' => $empId, 'vsId' => $vsId]);
            return ['uploaded' => 0];
        }

        $existing = [];
        try {
            $listed = $client->vectorStores()->files()->list($vsId, ['limit' => 100]);
            foreach ($listed->data as $f) $existing[] = $f->id;
        } catch (\Throwable $e) {
            Log::warning('VS list files falhou', ['empId'=>$empId,'vsId'=>$vsId,'err'=>$e->getMessage()]);
        }

        $toAttach = array_values(array_diff($files, $existing));
        $uploaded = 0;

        foreach ($toAttach as $fileId) {
            try {
                $client->vectorStores()->files()->create($vsId, ['file_id' => $fileId]);
                $uploaded++;
            } catch (\Throwable $e) {
                Log::error('VS attach falhou', ['empId'=>$empId,'vsId'=>$vsId,'fileId'=>$fileId,'err'=>$e->getMessage()]);
            }
        }

        if ($uploaded > 0) {
            DB::table('knowledge_assets')
                ->where('empreendimento_id', $empId)
                ->whereNotNull('openai_file_id')
                ->update(['openai_vector_store_id' => $vsId]);
        }

        Log::info('VS sync ok', ['empId'=>$empId,'uploaded'=>$uploaded,'vsId'=>$vsId]);
        return ['uploaded'=>$uploaded,'vsId'=>$vsId];
    }

    /** Garante Assistant com file_search + VS */
    public function ensureAssistantForEmpreendimento(int $empId, string $vsId): string
    {
        $cacheKey = "asst:emp:{$empId}";
        if ($cached = Cache::get($cacheKey)) return $cached;

        $client = $this->client();
        $assistant = $client->assistants()->create([
            'name'         => "Emp {$empId} Assistant",
            'model'        => 'gpt-4o-mini',
            'instructions' => "Você é um assistente para corretores de imóveis. Responda apenas com base nos documentos anexados do empreendimento. Se não tiver certeza, diga que não encontrou nos documentos.",
            'tools'        => [['type' => 'file_search']],
            'tool_resources' => [
                'file_search' => ['vector_store_ids' => [$vsId]],
            ],
        ]);

        Cache::put($cacheKey, $assistant->id, now()->addDays(7));
        return $assistant->id;
    }

    /** Faz pergunta ao assistant (mantido) */
    public function askEmpreendimento(int $empId, string $question): string
    {
        $client = $this->client();
        $vsId   = $this->ensureVectorStoreForEmpreendimento($empId);
        $this->syncEmpreendimentoAssetsToVS($empId, $vsId);
        $asstId = $this->ensureAssistantForEmpreendimento($empId, $vsId);

        $th = $client->threads()->create();
        $threadId = $th->id;

        $client->threads()->messages()->create($threadId, [
            'role'    => 'user',
            'content' => $question,
        ]);

        $run = $client->threads()->runs()->create($threadId, [
            'assistant_id' => $asstId,
            'additional_instructions' => "Responda de forma objetiva. Se não encontrar nos documentos, diga isso claramente.",
        ]);

        do {
            usleep(300000);
            $run = $client->threads()->runs()->retrieve($threadId, $run->id);
        } while (in_array($run->status, ['queued','in_progress','cancelling']));

        if ($run->status !== 'completed') {
            Log::warning('Run não completou', ['empId'=>$empId,'status'=>$run->status]);
            return '';
        }

        $msgs = $client->threads()->messages()->list($threadId, ['limit' => 10]);
        foreach ($msgs->data as $m) {
            if ($m->role === 'assistant') {
                foreach ($m->content as $c) {
                    if ($c->type === 'text') return $c->text->value ?? '';
                }
            }
        }
        return '';
    }

    /* ======================= INGESTÃO (novo + compat c/ legado) ======================= */

    /**
     * Aceita:
     * 1) uploadAndAttach($vsId, $asset) // legado
     * 2) uploadAndAttach($empId, $assetId, $source, ?$fileName) // nova
     */
    public function uploadAndAttach(...$args): array
    {
        Log::info('VS.uploadAndAttach.dispatch', ['argc'=>count($args), 'types'=>array_map(fn($a)=>gettype($a), $args)]);

        // Assinatura 1: ($vsId, $asset)
        if (count($args) === 2 && is_string($args[0]) && str_starts_with($args[0], 'vs_')) {
            [$vsId, $asset] = $args;
            return $this->uploadAndAttach_ForVsAndAsset($vsId, $asset);
        }

        // Assinatura 2: ($empId, $assetId, $source, ?$fileName)
        if (count($args) >= 3 && is_int($args[0])) {
            $empId    = (int) $args[0];
            $assetId  = (int) $args[1];
            $source   = (string) $args[2];
            $fileName = $args[3] ?? null;
            return $this->uploadAndAttach_BySource($empId, $assetId, $source, $fileName);
        }

        Log::error('VS.uploadAndAttach.invalid_signature', ['args'=>$args]);
        return ['ok'=>false, 'error'=>'invalid_signature'];
    }

    /** Normaliza a “row” do asset */
    protected function normalizeAssetRow($asset): ?array
    {
        if (is_int($asset)) {
            $row = DB::table('knowledge_assets')->where('id', $asset)->first();
            return $row ? (array) $row : null;
        }
        if ($asset instanceof \Illuminate\Database\Eloquent\Model) {
            return $asset->getAttributes();
        }
        if ($asset instanceof \stdClass) {
            return (array) $asset;
        }
        if (is_array($asset)) {
            return $asset;
        }
        return null;
    }

    /** Legado: ($vsId, $asset) */
    protected function uploadAndAttach_ForVsAndAsset(string $vsId, $asset): array
    {
        $row = $this->normalizeAssetRow($asset);
        Log::info('VS.asset.row.keys', ['keys' => $row ? array_keys($row) : []]);

        if (!$row) {
            Log::error('VS.uploadAndAttach.ForVsAndAsset: asset inválido', ['vsId'=>$vsId]);
            return ['ok'=>false, 'error'=>'invalid_asset'];
        }

        $assetId = (int) ($row['id'] ?? 0);
        $empId   = (int) ($row['empreendimento_id'] ?? 0);
        Log::info('VS.uploadAndAttach.ForVsAndAsset.start', compact('vsId','assetId','empId','row'));

        // Nome base
        $fileName    = $row['original_name'] ?? $row['name'] ?? $row['filename'] ?? ('asset_'.$assetId.'.bin');
        $extFromPath = strtolower(pathinfo((string)($row['path'] ?? $fileName), PATHINFO_EXTENSION));
        $mime        = (string) ($row['mime'] ?? '');

        // 🔕 SKIP: vídeos – não indexa no VS
        $videoExts = ['mp4','mov','mkv','avi','webm'];
        $isVideo   = (str_starts_with($mime, 'video/') || in_array($extFromPath, $videoExts, true));
        if ($isVideo) {
            DB::table('knowledge_assets')->where('id', $assetId)->update([
                'status'     => 'ready',
                'error_info' => null,
                'updated_at' => now(),
            ]);
            Log::info('VS.skip.video_asset', ['assetId'=>$assetId, 'mime'=>$mime, 'ext'=>$extFromPath]);
            return ['ok'=>true, 'skipped'=>true, 'reason'=>'video_not_ingested'];
        }

        // 1) disk + path
        if (!empty($row['disk']) && !empty($row['path'])) {
            $disk = (string) $row['disk'];
            $path = (string) $row['path'];

            if (in_array($disk, ['s3','minio','spaces'], true)) {
                Log::info('VS.source.resolved', ['assetId'=>$assetId,'kind'=>'s3','disk'=>$disk,'path'=>$path,'name'=>$fileName,'why'=>'disk+path(s3-like)']);
                return $this->uploadFromS3AndAttachToVs($vsId, $empId, $assetId, $path, $fileName);
            }

            try {
                $abs = Storage::disk($disk)->path($path);
                Log::info('VS.source.resolved', ['assetId'=>$assetId,'kind'=>'local','disk'=>$disk,'path'=>$path,'abs'=>$abs,'name'=>$fileName,'why'=>'disk+path(local)']);
                return $this->uploadFromLocalAndAttachToVs($vsId, $empId, $assetId, $abs, $fileName);
            } catch (\Throwable $e) {
                Log::warning('VS.source.disk_path.resolve_fail', ['disk'=>$disk,'path'=>$path,'e'=>$e->getMessage()]);
            }
        }

        // 2) alternativos
        if (!empty($row['s3_path']))   return $this->uploadFromS3AndAttachToVs($vsId, $empId, $assetId, $row['s3_path'], $fileName);
        if (!empty($row['url']))       return $this->uploadFromUrlAndAttachToVs($vsId, $empId, $assetId, $row['url'], $fileName);
        if (!empty($row['local_path']))return $this->uploadFromLocalAndAttachToVs($vsId, $empId, $assetId, $row['local_path'], $fileName);

        Log::error('VS.uploadAndAttach.ForVsAndAsset.no_source', ['assetId'=>$assetId, 'row_keys'=>array_keys($row)]);
        $this->markAssetError($assetId, 'no_source_fields');
        return ['ok'=>false, 'error'=>'no_source_fields'];
    }

    /** Nova: ($empId, $assetId, $source, ?$fileName) */
    protected function uploadAndAttach_BySource(int $empId, int $assetId, string $source, ?string $fileName = null): array
    {
        Log::info('VS.uploadAndAttach.BySource.start', compact('empId','assetId','source','fileName'));

        if (str_starts_with($source, 's3://')) {
            $s3Path   = ltrim(substr($source, 5), '/');
            $name     = $fileName ?: basename($s3Path);
            $vsId     = $this->ensureVectorStoreForEmpreendimento($empId);
            return $this->uploadFromS3AndAttachToVs($vsId, $empId, $assetId, $s3Path, $name);
        }

        if (preg_match('#^https?://#i', $source)) {
            $name     = $fileName ?: basename(parse_url($source, PHP_URL_PATH) ?? 'file.bin');
            $vsId     = $this->ensureVectorStoreForEmpreendimento($empId);
            return $this->uploadFromUrlAndAttachToVs($vsId, $empId, $assetId, $source, $name);
        }

        $name = $fileName ?: basename($source ?: 'file.bin');
        $vsId = $this->ensureVectorStoreForEmpreendimento($empId);
        return $this->uploadFromLocalAndAttachToVs($vsId, $empId, $assetId, $source, $name);
    }

    /* ======================= fontes ======================= */

    protected function uploadFromS3AndAttachToVs(string $vsId, int $empId, int $assetId, string $s3Path, string $fileName): array
    {
        Log::info('VS.ingest.s3.start', compact('vsId','empId','assetId','s3Path','fileName'));

        try {
            $url = Storage::disk('s3')->temporaryUrl($s3Path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::error('VS.ingest.s3.presign_fail', ['e'=>$e->getMessage()]);
            $this->markAssetError($assetId, 's3_presign_fail: '.$e->getMessage());
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }

        return $this->downloadCreateFileAndAttach($vsId, $empId, $assetId, $url, $fileName, 's3');
    }

    protected function uploadFromUrlAndAttachToVs(string $vsId, int $empId, int $assetId, string $url, string $fileName): array
    {
        Log::info('VS.ingest.url.start', compact('vsId','empId','assetId','url','fileName'));
        return $this->downloadCreateFileAndAttach($vsId, $empId, $assetId, $url, $fileName, 'url');
    }

    protected function uploadFromLocalAndAttachToVs(string $vsId, int $empId, int $assetId, string $localPath, string $fileName): array
    {
        Log::info('VS.ingest.local.start', compact('vsId','empId','assetId','localPath','fileName'));

        if (!is_file($localPath) || !is_readable($localPath)) {
            $msg = 'local_file_not_readable';
            Log::error('VS.ingest.local.fail', ['path'=>$localPath]);
            $this->markAssetError($assetId, $msg);
            return ['ok'=>false, 'error'=>$msg];
        }

        $client = $this->client();

        try {
            // ✅ SDK atual: upload()
            $file = $client->files()->upload([
                'file'    => fopen($localPath, 'r'),
                'purpose' => 'assistants',
            ]);
        } catch (\Throwable $e) {
            Log::error('VS.ingest.local.openai_upload_fail', ['e'=>$e->getMessage()]);
            $this->markAssetError($assetId, 'openai_file_upload_error: '.$e->getMessage());
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }

        return $this->attachFileIdAndPersist($vsId, $empId, $assetId, $file->id ?? null);
    }

    /** Baixa URL → arquivo tmp → files()->upload() → anexa ao VS */
    protected function downloadCreateFileAndAttach(string $vsId, int $empId, int $assetId, string $url, string $fileName, string $origin): array
    {
        try {
            $bin = Http::timeout(120)->get($url);
            if (!$bin->successful()) {
                $msg = "download_failed: status=".$bin->status();
                Log::error('VS.ingest.download_fail', ['origin'=>$origin, 'status'=>$bin->status(), 'url'=>$url]);
                $this->markAssetError($assetId, $msg);
                return ['ok'=>false, 'error'=>$msg];
            }

            $tmp = sys_get_temp_dir().'/ingest_'.$origin.'_'.Str::random(8).'_'.($fileName ?: 'file.bin');
            file_put_contents($tmp, $bin->body());

            $client = $this->client();
            // ✅ SDK atual: upload()
            $file = $client->files()->upload([
                'file'    => fopen($tmp, 'r'),
                'purpose' => 'assistants',
            ]);

            @unlink($tmp);

            return $this->attachFileIdAndPersist($vsId, $empId, $assetId, $file->id ?? null);
        } catch (\Throwable $e) {
            Log::error('VS.ingest.download_exception', ['origin'=>$origin, 'e'=>$e->getMessage()]);
            $this->markAssetError($assetId, 'download_exception: '.$e->getMessage());
            return ['ok'=>false, 'error'=>$e->getMessage()];
        }
    }

    /** Persiste e anexa ao VS */
    protected function attachFileIdAndPersist(string $vsId, int $empId, int $assetId, ?string $fileId): array
    {
        if (!$fileId) {
            Log::error('VS.attach.missing_file_id', compact('assetId','vsId'));
            $this->markAssetError($assetId, 'openai_file_missing_id');
            return ['ok'=>false, 'error'=>'openai_file_missing_id'];
        }

        $client = $this->client();

        try {
            $client->vectorStores()->files()->create($vsId, ['file_id' => $fileId]);

            DB::table('knowledge_assets')->where('id', $assetId)->update([
                'openai_file_id'         => $fileId,
                'openai_vector_store_id' => $vsId,
                'status'                 => 'ready',
                'error_info'             => null,
                'updated_at'             => now(),
            ]);

            Log::info('VS.attach.ok', compact('assetId','empId','vsId','fileId'));
            return ['ok'=>true, 'fileId'=>$fileId, 'vsId'=>$vsId];
        } catch (\Throwable $e) {
            Log::error('VS.attach.fail', ['assetId'=>$assetId,'vsId'=>$vsId,'fileId'=>$fileId,'e'=>$e->getMessage()]);
            DB::table('knowledge_assets')->where('id', $assetId)->update([
                'openai_file_id' => $fileId,
                'status'         => 'processing',
                'updated_at'     => now(),
            ]);
            return ['ok'=>false, 'fileId'=>$fileId, 'error'=>$e->getMessage()];
        }
    }

    /** Marca erro no asset (usa error_info) */
    protected function markAssetError(int $assetId, string $msg): void
    {
        DB::table('knowledge_assets')->where('id', $assetId)->update([
            'status'     => 'error',
            'error_info' => $msg,
            'updated_at' => now(),
        ]);
        Log::warning('VS.asset.mark_error', ['assetId'=>$assetId, 'msg'=>$msg]);
    }

    /** Anexa um openai_file_id existente ao VS */
    public function attachExistingOpenAIFileToVS(int $empId, int $assetId, string $openaiFileId): array
    {
        $client = $this->client();
        $vsId   = $this->ensureVectorStoreForEmpreendimento($empId);

        try {
            $client->vectorStores()->files()->create($vsId, ['file_id' => $openaiFileId]);
        } catch (\Throwable $e) {
            Log::error('Attach existente falhou', ['empId'=>$empId,'assetId'=>$assetId,'fileId'=>$openaiFileId,'err'=>$e->getMessage()]);
            return ['ok'=>false];
        }

        DB::table('knowledge_assets')->where('id', $assetId)->update([
            'openai_vector_store_id' => $vsId,
            'status'                 => 'ready',
            'error_info'             => null,
            'updated_at'             => now(),
        ]);

        Log::info('Attach existente OK', ['empId'=>$empId,'assetId'=>$assetId,'fileId'=>$openaiFileId,'vsId'=>$vsId]);
        return ['ok'=>true, 'vsId'=>$vsId];
    }
}
