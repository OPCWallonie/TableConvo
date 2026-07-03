<?php

namespace App\Services\Vat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VatValidationService
{
    private const VIES_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/BE/vat/';

    public function validate(string $vatNumber): bool
    {
        $normalized = $this->normalize($vatNumber);

        if (! $this->isFormatValid($normalized)) {
            return false;
        }

        // Strip the BE prefix for the VIES API
        $number = substr($normalized, 2);

        try {
            $response = Http::timeout(10)->get(self::VIES_URL . $number);

            if ($response->successful()) {
                return (bool) ($response->json('isValid') ?? false);
            }
        } catch (\Throwable $e) {
            Log::warning('VIES API unavailable, skipping remote validation', [
                'vat' => $normalized,
                'error' => $e->getMessage(),
            ]);

            // Fail open when VIES is unreachable: format is valid, accept it
            return true;
        }

        return false;
    }

    public function isFormatValid(string $vatNumber): bool
    {
        $normalized = $this->normalize($vatNumber);

        // Belgian format: BE0XXXXXXXXX (BE + 10 digits starting with 0 or 1)
        return (bool) preg_match('/^BE[01]\d{9}$/', $normalized);
    }

    public function normalize(string $vatNumber): string
    {
        $upper = strtoupper(trim($vatNumber));

        // Accept formats: BE0123456789, BE 0123456789, 0123456789
        if (! str_starts_with($upper, 'BE')) {
            $upper = 'BE' . $upper;
        }

        return preg_replace('/\s+/', '', $upper);
    }

    public function lookup(string $vatNumber): ?VatLookupResult
    {
        $normalized = $this->normalize($vatNumber);

        if (! $this->isFormatValid($normalized)) {
            return null;
        }

        $number = substr($normalized, 2);

        try {
            $response = Http::timeout(10)->get(self::VIES_URL . $number);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! ($data['isValid'] ?? false)) {
                return null;
            }

            return new VatLookupResult(
                name: $data['name'] ?? '---',
                address: $data['address'] ?? '---',
                vatNumber: $normalized,
                validatedAt: Carbon::now(),
            );
        } catch (\Throwable $e) {
            Log::warning('VIES API unavailable during lookup', [
                'vat'   => $normalized,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
