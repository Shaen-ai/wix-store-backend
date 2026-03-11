<?php

namespace App\Services;

use App\Contracts\FxRateProvider;
use App\Models\FxRate;
use Illuminate\Support\Carbon;

class FxService
{
    private int $cacheTtlMinutes;

    public function __construct(
        private readonly FxRateProvider $provider,
    ) {
        $this->cacheTtlMinutes = (int) config('services.fx.cache_ttl', 60);
    }

    public function convert(int $amountCents, string $from, string $to): array
    {
        if ($from === $to) {
            return ['amount_cents' => $amountCents, 'rate' => 1.0];
        }

        $rate = $this->getRate($from, $to);

        return [
            'amount_cents' => (int) round($amountCents * $rate),
            'rate' => $rate,
        ];
    }

    public function getRate(string $from, string $to): float
    {
        $cached = FxRate::where('base_currency', $from)
            ->where('target_currency', $to)
            ->where('fetched_at', '>=', Carbon::now()->subMinutes($this->cacheTtlMinutes))
            ->first();

        if ($cached) {
            return (float) $cached->rate;
        }

        $this->refreshRates($from);

        $fresh = FxRate::where('base_currency', $from)
            ->where('target_currency', $to)
            ->first();

        if (!$fresh) {
            throw new \RuntimeException("Exchange rate not available for {$from} to {$to}");
        }

        return (float) $fresh->rate;
    }

    public function refreshRates(string $baseCurrency): void
    {
        $rates = $this->provider->fetchRates($baseCurrency);
        $now = Carbon::now();

        foreach ($rates as $target => $rate) {
            FxRate::updateOrCreate(
                ['base_currency' => $baseCurrency, 'target_currency' => $target],
                ['rate' => $rate, 'fetched_at' => $now]
            );
        }
    }
}
