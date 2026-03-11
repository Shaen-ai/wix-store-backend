<?php

namespace App\Services;

use App\Contracts\FxRateProvider;
use Illuminate\Support\Facades\Http;

class ExchangeRateFxProvider implements FxRateProvider
{
    public function __construct(
        private readonly string $apiKey = '',
    ) {}

    public function fetchRates(string $baseCurrency): array
    {
        if ($this->apiKey) {
            $url = "https://v6.exchangerate-api.com/v6/{$this->apiKey}/latest/{$baseCurrency}";
        } else {
            $url = "https://open.er-api.com/v6/latest/{$baseCurrency}";
        }

        $response = Http::timeout(10)->get($url);
        $response->throw();

        $data = $response->json();

        return $data['rates'] ?? $data['conversion_rates'] ?? [];
    }
}
