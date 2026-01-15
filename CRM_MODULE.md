# Módulo CRM - Assistente do Corretor

## Visão Geral

O módulo CRM permite que corretores gerenciem suas rotinas via WhatsApp e Dashboard:
- **Visitas marcadas** (agendamentos)
- **Propostas aguardando resposta**
- **Vendas fechadas**
- **Follow-ups** (tarefas e lembretes)
- **Pipeline do corretor** (status do lead/cliente)

## Arquitetura

### Estrutura de Pastas

```
app/
├── Enums/
│   ├── CrmLeadStatus.php
│   ├── CrmDealStatus.php
│   └── CrmActivityStatus.php
├── DTOs/Crm/
│   └── IntentDTO.php
├── Models/
│   ├── CrmLead.php
│   ├── CrmInteraction.php
│   ├── CrmActivity.php
│   ├── CrmDeal.php
│   └── CrmAudit.php
└── Services/Crm/
    ├── NlpParserInterface.php
    ├── SimpleNlpParser.php
    ├── CommandRouter.php
    └── AssistenteService.php
```

### Tabelas do Banco

1. **crm_leads**: Clientes/Leads
2. **crm_interactions**: Mensagens, ligações, notas
3. **crm_activities**: Visitas, follow-ups, tarefas
4. **crm_deals**: Propostas e vendas
5. **crm_audits**: Histórico de mudanças

## Como Usar via WhatsApp

### Comandos Principais

#### 1. Agendar Visita
```
visita amanhã 15h com João no Paradizzo
visita segunda 14:30 com Maria
```

#### 2. Registrar Proposta
```
proposta 520k para Maria, aguardando
proposta 480000 para João Silva
```

#### 3. Fechar Venda
```
venda fechada 480k cliente Ana, unidade 203
venda fechada 550000 para João, torre 5
```

#### 4. Criar Follow-up
```
lembrar de ligar para João amanhã
follow up com Maria na segunda
```

#### 5. Listar Informações
```
listar visitas hoje
listar propostas aguardando
minhas pendências
resumo da semana
```

#### 6. Menu e Navegação
```
assistente (entra no modo CRM)
menu (mostra opções)
sair (sai do modo CRM)
```

## Exemplos de Mensagens

### Exemplo 1: Agendar Visita Completa
**Usuário:** `visita amanhã 15h com João Silva no Paradizzo`

**Resposta:**
```
✅ Visita agendada!

👤 Cliente: João Silva
📅 Data: 14/01/2026
🕐 Horário: 15:00

Digite listar visitas para ver todas.
```

### Exemplo 2: Registrar Proposta
**Usuário:** `proposta 520k para Maria Santos, aguardando`

**Resposta:**
```
✅ Proposta registrada!

👤 Cliente: Maria Santos
💰 Valor: R$ 520.000,00
📊 Status: Aguardando Resposta

Digite listar propostas para ver todas.
```

### Exemplo 3: Fechar Venda
**Usuário:** `venda fechada 480k cliente Ana, unidade 203`

**Resposta:**
```
🎉 Venda fechada!

👤 Cliente: Ana
💰 Valor: R$ 480.000,00
✅ Parabéns pela venda!
```

### Exemplo 4: Resumo da Semana
**Usuário:** `resumo da semana`

**Resposta:**
```
📊 Resumo da Semana

📅 Visitas: 5
📄 Propostas aguardando: 3
💰 Vendas: R$ 1.200.000,00
📋 Pendências: 2
```

## Dashboard

Acesse: `/admin/crm`

O dashboard mostra:
- **KPIs**: Visitas da semana, propostas aguardando, vendas do mês, pendências
- **Atividades Recentes**: Lista de visitas, follow-ups e tarefas
- **Propostas e Vendas**: Lista de todas as propostas e vendas

## Integração no Código

### No WppController

O módulo está integrado via **SUPER-GATE** que detecta comandos CRM:

```php
// Detecta comandos CRM
$isCrmCommand = $this->isCrmCommand($norm);
$isCrmMode = data_get($context, 'crm_mode', false) || $isCrmCommand;

if ($isCrmCommand || $isCrmMode) {
    // Processa com AssistenteService
    $assistente = new AssistenteService($parser, $router);
    $resposta = $assistente->processar($thread, $corretor, $text);
    // ...
}
```

## Extensões Futuras

- Integração com agenda (Google Calendar, Outlook)
- Notificações automáticas (lembretes de visitas)
- Score de lead (qualificação automática)
- Integração com IA para análise de sentimento
- Relatórios avançados e gráficos

## Timezone

Todos os horários são processados em `America/Sao_Paulo`.

## Auditoria

Todas as mudanças são registradas em `crm_audits` com:
- Origem (whatsapp, dashboard, api)
- Valores antigos e novos
- Usuário responsável
- Timestamp
