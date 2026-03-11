<?php

namespace App\Contracts;

interface FxRateProvider
{
    /**
     * Fetch all exchange rates for a given base currency.
     *
     * @return array<string, float> e.g. ['USD' => 1.08, 'GBP' => 0.86, ...]
     */
    public function fetchRates(string $baseCurrency): array;
}
