<?php

namespace App\Jobs;

use App\Models\KnowledgeAsset;
use App\Services\EmpreendimentoIaTrainer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrainEmpreendimentoFromPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $assetId;

    public function __construct(int $assetId)
    {
        $this->assetId = $assetId;
    }

    public function handle(EmpreendimentoIaTrainer $trainer): void
    {
        $asset = KnowledgeAsset::find($this->assetId);

        if (! $asset) {
            Log::warning('ia_trainer.asset_not_found', ['asset_id' => $this->assetId]);
            return;
        }

        $trainer->trainFromAsset($asset);
    }
}
