<?php

namespace App\DTOs\Crm;

class IntentDTO
{
    public function __construct(
        public string $intent,
        public array $entities = [],
        public float $confidence = 0.0,
        public ?string $rawText = null,
    ) {}

    public function hasEntity(string $key): bool
    {
        return isset($this->entities[$key]);
    }

    public function getEntity(string $key, $default = null)
    {
        return $this->entities[$key] ?? $default;
    }
}
