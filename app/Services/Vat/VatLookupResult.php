<?php

namespace App\Services\Vat;

use Carbon\Carbon;

readonly class VatLookupResult
{
    public function __construct(
        public string $name,
        public string $address,
        public string $vatNumber,
        public Carbon $validatedAt,
    ) {}

    public function nameIsUndisclosed(): bool
    {
        return $this->name === '---';
    }

    public function addressIsUndisclosed(): bool
    {
        return $this->address === '---';
    }
}
