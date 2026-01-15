<?php

namespace App\Services\Crm;

use App\DTOs\Crm\IntentDTO;

interface NlpParserInterface
{
    /**
     * Analisa o texto e retorna um IntentDTO com a intenção e entidades extraídas
     */
    public function parse(string $text, array $context = []): IntentDTO;
}
