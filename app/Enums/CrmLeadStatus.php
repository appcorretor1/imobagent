<?php

namespace App\Enums;

enum CrmLeadStatus: string
{
    case NOVO = 'novo';
    case CONTATO_INICIAL = 'contato_inicial';
    case INTERESSADO = 'interessado';
    case PROPOSTA_ENVIADA = 'proposta_enviada';
    case NEGOCIANDO = 'negociando';
    case FECHADO = 'fechado';
    case PERDIDO = 'perdido';
    case ARQUIVADO = 'arquivado';

    public function label(): string
    {
        return match($this) {
            self::NOVO => 'Novo',
            self::CONTATO_INICIAL => 'Contato Inicial',
            self::INTERESSADO => 'Interessado',
            self::PROPOSTA_ENVIADA => 'Proposta Enviada',
            self::NEGOCIANDO => 'Negociando',
            self::FECHADO => 'Fechado',
            self::PERDIDO => 'Perdido',
            self::ARQUIVADO => 'Arquivado',
        };
    }
}
