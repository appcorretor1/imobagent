<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\WhatsappThread;
use App\Services\Crm\AssistenteService;
use App\Services\Crm\SimpleNlpParser;
use App\Services\Crm\CommandRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CrmAssistenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_visita_via_whatsapp(): void
    {
        $company = Company::factory()->create();
        $corretor = User::factory()->create(['company_id' => $company->id, 'role' => 'corretor']);
        
        $thread = WhatsappThread::create([
            'phone' => '5562999999999',
            'thread_id' => 'test_thread',
            'corretor_id' => $corretor->id,
            'state' => 'idle',
            'context' => ['crm_mode' => true],
        ]);

        $parser = new SimpleNlpParser();
        $router = new CommandRouter();
        $assistente = new AssistenteService($parser, $router);

        $resposta = $assistente->processar($thread, $corretor, 'visita amanhã 15h com João Silva');

        $this->assertStringContainsString('Visita agendada', $resposta);
        $this->assertStringContainsString('João Silva', $resposta);
        
        // Verifica se a atividade foi criada
        $this->assertDatabaseHas('crm_activities', [
            'corretor_id' => $corretor->id,
            'tipo' => 'visita',
        ]);

        // Verifica se o lead foi criado
        $this->assertDatabaseHas('crm_leads', [
            'corretor_id' => $corretor->id,
            'nome' => 'João Silva',
        ]);
    }

    public function test_criar_proposta_via_whatsapp(): void
    {
        $company = Company::factory()->create();
        $corretor = User::factory()->create(['company_id' => $company->id, 'role' => 'corretor']);
        
        $thread = WhatsappThread::create([
            'phone' => '5562999999999',
            'thread_id' => 'test_thread',
            'corretor_id' => $corretor->id,
            'state' => 'idle',
            'context' => ['crm_mode' => true],
        ]);

        $parser = new SimpleNlpParser();
        $router = new CommandRouter();
        $assistente = new AssistenteService($parser, $router);

        $resposta = $assistente->processar($thread, $corretor, 'proposta 520000 para Maria Santos, aguardando');

        $this->assertStringContainsString('Proposta registrada', $resposta);
        
        // Verifica se a proposta foi criada
        $this->assertDatabaseHas('crm_deals', [
            'corretor_id' => $corretor->id,
            'tipo' => 'proposta',
            'valor' => 520000.00,
        ]);
    }
}
