<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeAsset;
use App\Services\VectorStoreService;

class ReingestAsset extends Command
{
    protected $signature = 'asset:reingest {id}';
    protected $description = 'Reanexa um asset ao Vector Store e aguarda indexação';

    public function handle(VectorStoreService $svc)
    {
        $id = (int)$this->argument('id');
        $asset = KnowledgeAsset::findOrFail($id);

        $this->info("Reanexando asset #{$id} (VS: {$asset->openai_vector_store_id})...");
        $vsId = $asset->openai_vector_store_id;

        if (!$vsId) {
            $vsId = $svc->ensureVectorStoreForEmpreendimento($asset->empreendimento_id);
        }

        // Força reprocessar do zero
        $asset->status = 'pending';
        $asset->error  = null;
        $asset->save();

        $svc->uploadAndAttach($vsId, $asset);

        $this->info("Status final: {$asset->status}");
        return self::SUCCESS;
    }
}
