<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Company, User, Empreendimento};
use Illuminate\Support\Str;

class SetupDemoData extends Command
{
    protected $signature = 'setup:demo';
    protected $description = 'Cria empresa, usuário diretor e empreendimento de demonstração';

    public function handle()
    {
        // 1. Empresa
        $company = Company::firstOrCreate(
            ['slug' => 'imobiliaria-demo'],
            ['name' => 'Imobiliária Demo', 'whatsapp_number' => '5599999999999']
        );

        $this->info("🏢 Empresa criada: {$company->name} (ID {$company->id})");

        // 2. Usuário diretor
        $user = User::firstOrCreate(
            ['email' => 'diretor@demo.com'],
            [
                'name' => 'Diretor Demo',
                'password' => bcrypt('senha123'),
                'role' => 'diretor',
                'company_id' => $company->id,
            ]
        );

        $this->info("👤 Usuário criado: {$user->email} (senha: senha123)");

        // 3. Empreendimento
        $empreendimento = Empreendimento::firstOrCreate(
            ['nome' => 'Residencial Aurora'],
            [
                'company_id' => $company->id,
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'tipologia' => '2 e 3 dormitórios',
                'metragem' => '68 a 95 m²',
                'preco_base' => 650000,
                'descricao' => 'Condomínio moderno com lazer completo.',
                'contexto_ia' => 'Residencial Aurora possui 2 e 3 dormitórios, varanda gourmet, 
                                 academia e piscina. Localizado em São Paulo/SP.'
            ]
        );

        $this->info("🏗️ Empreendimento criado: {$empreendimento->nome}");

        $this->info("\n✅ Dados de demonstração criados com sucesso!");
    }
}
