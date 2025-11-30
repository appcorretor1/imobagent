<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Empreendimento;

class TesteBanco extends Command
{
    protected $signature = 'teste:app_ia';
    protected $description = 'Cria um registro de teste no banco';

    public function handle()
    {
        $e = Empreendimento::create(['nome' => 'Empreendimento de Teste']);
        $this->info('Registro criado com ID: '.$e->id);
    }
}
