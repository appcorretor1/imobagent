<?php

namespace App\Enums;

enum CrmDealStatus: string
{
    case RASCUNHO = 'rascunho';
    case ENVIADA = 'enviada';
    case AGUARDANDO_RESPOSTA = 'aguardando_resposta';
    case NEGOCIANDO = 'negociando';
    case ACEITA = 'aceita';
    case RECUSADA = 'recusada';
    case FECHADA = 'fechada';
    case CANCELADA = 'cancelada';

    public function label(): string
    {
        return match($this) {
            self::RASCUNHO => 'Rascunho',
            self::ENVIADA => 'Enviada',
            self::AGUARDANDO_RESPOSTA => 'Aguardando Resposta',
            self::NEGOCIANDO => 'Negociando',
            self::ACEITA => 'Aceita',
            self::RECUSADA => 'Recusada',
            self::FECHADA => 'Fechada',
            self::CANCELADA => 'Cancelada',
        };
    }
}
