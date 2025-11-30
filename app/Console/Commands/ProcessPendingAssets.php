<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeAsset;
use App\Services\VectorStoreService;

class ProcessPendingAssets extends Command
{
    protected $signature = 'assets:process-pending';
    protected $description = 'Processa assets pendentes/processing, anexando ao Vector Store e aguardando indexação';

    public function handle(VectorStoreService $svc)
    {
        $assets = KnowledgeAsset::whereIn('status', ['pending','processing'])->orderBy('id')->get();
        if ($assets->isEmpty()) {
            $this->info('Nenhum asset pendente/processing.');
            return self::SUCCESS;
        }

        foreach ($assets as $asset) {
            $this->line("Processando asset #{$asset->id} (emp: {$asset->empreendimento_id})");
            $vsId = $asset->openai_vector_store_id ?: $svc->ensureVectorStoreForEmpreendimento($asset->empreendimento_id);

            // força status inicial
            $asset->update(['status' => 'processing', 'error' => null]);

            try {
                $svc->uploadAndAttach($vsId, $asset); // <- com polling (precisa do método atualizado)
                $this->info("OK: asset #{$asset->id} => {$asset->status}");
            } catch (\Throwable $e) {
                $asset->update(['status' => 'failed', 'error' => $e->getMessage()]);
                $this->error("FAIL: asset #{$asset->id} => {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
