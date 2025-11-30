<?php

namespace App\Jobs;

use App\Models\KnowledgeAsset;
use App\Services\VectorStoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class IngestKnowledgeAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public int $assetId) {}

    public function handle(VectorStoreService $svc): void
    {
        /** @var KnowledgeAsset|null $asset */
        $asset = KnowledgeAsset::find($this->assetId);
        if (!$asset) {
            Log::warning('ingest.asset_not_found', ['assetId' => $this->assetId]);
            return;
        }

        try {
            $asset->updateQuietly(['status' => 'processing']);

            $vsId = $svc->ensureVectorStoreForEmpreendimento($asset->empreendimento_id);

            // Agora o service retorna o file_id OU já marca failed com motivo claro.
            $fileId = $svc->uploadAndAttach($vsId, $asset);

            // Se retornou fileId, ótimo. Se não, o service já marcou status/erro.
            if ($fileId) {
                Log::info('ingest.success', ['assetId' => $asset->id, 'fileId' => $fileId, 'vsId' => $vsId]);
            } else {
                Log::error('ingest.failed_no_file_id', ['assetId' => $asset->id, 'vsId' => $vsId, 'error' => $asset->error]);
            }

        } catch (\Throwable $e) {
            $asset?->updateQuietly(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error('ingest.exception', [
                'assetId' => $asset?->id,
                'msg'     => $e->getMessage(),
            ]);
        }
    }
}
