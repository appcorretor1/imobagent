<?php
// app/Services/EmpreendimentoUnidadeService.php

namespace App\Services;

use App\Models\Empreendimento;
use App\Models\EmpreendimentoUnidade;
use Illuminate\Support\Facades\Log;

class EmpreendimentoUnidadeService
{
    /**
     * Importa lista de nomes de unidades (array de strings) 
     * criando todas como "livre".
     */
    public function importarLista(
        Empreendimento $empreendimento,
        array $nomesUnidades
    ): int {
        $count = 0;

        foreach ($nomesUnidades as $nome) {
            $nome = trim((string) $nome);
            if ($nome === '') {
                continue;
            }

            // evita duplicar se já existir
            $exists = EmpreendimentoUnidade::where('empreendimento_id', $empreendimento->id)
                ->where('nome_unidade', $nome)
                ->exists();

            if ($exists) {
                continue;
            }

            EmpreendimentoUnidade::create([
                'empreendimento_id' => $empreendimento->id,
                'nome_unidade'      => $nome,
                'status'            => EmpreendimentoUnidade::STATUS_LIVRE,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Atualiza o status de uma unidade pelo nome.
     * Útil para IA / WhatsApp.
     */
    public function setStatusByNome(
        Empreendimento $empreendimento,
        string $nomeUnidade,
        string $status
    ): bool {
        $nomeUnidade = trim($nomeUnidade);

        if (! in_array($status, array_keys(EmpreendimentoUnidade::statusOptions()))) {
            Log::warning('Status inválido para unidade', [
                'empreendimento_id' => $empreendimento->id,
                'nome_unidade'      => $nomeUnidade,
                'status'            => $status,
            ]);
            return false;
        }

        $unidade = EmpreendimentoUnidade::where('empreendimento_id', $empreendimento->id)
            ->where('nome_unidade', $nomeUnidade)
            ->first();

        if (! $unidade) {
            Log::warning('Unidade não encontrada para atualização de status', [
                'empreendimento_id' => $empreendimento->id,
                'nome_unidade'      => $nomeUnidade,
                'status'            => $status,
            ]);
            return false;
        }

        $unidade->status = $status;
        $unidade->save();

        return true;
    }
}
