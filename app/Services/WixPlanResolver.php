<?php

namespace App\Services;

class WixPlanResolver
{
    /**
     * Resolve paid tier from Wix webhook event payload (structure varies by event).
     * Returns null if no known vendor id is found — caller should use a default paid tier.
     */
    public function resolvePaidPlanFromEventData(mixed $eventData): ?string
    {
        if ($eventData === null) {
            return null;
        }

        $map = config('wix_billing.vendor_product_to_plan', []);
        if (!is_array($map) || $map === []) {
            return null;
        }

        $tree = is_object($eventData) ? json_decode(json_encode($eventData), true) : (array) $eventData;
        if (!is_array($tree)) {
            return null;
        }

        foreach ($this->dotFlatten($tree) as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (isset($map[$value])) {
                return $map[$value];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dotFlatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out = array_merge($out, $this->dotFlatten($value, $path));
            } else {
                $out[$path] = $value;
            }
        }

        return $out;
    }
}
