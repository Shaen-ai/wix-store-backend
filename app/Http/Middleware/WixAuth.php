<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WixAuth
{
    /**
     * Validate the Wix access token and resolve the tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        // Dev bypass: when APP_ENV=local, allow no token or "dev" token
        if (app()->environment('local')) {
            $devToken = config('services.wix.dev_instance_token', 'dev');
            $devInstanceId = config('services.wix.dev_instance_id', 'dev-local');
            if (!$token || $token === $devToken) {
                $wixSiteId = $devInstanceId;
                $tenant = Tenant::firstOrCreate(
                    ['wix_site_id' => $wixSiteId],
                    ['plan' => 'free']
                );
                $tenant->settings()->firstOrCreate(
                    ['tenant_id' => $tenant->id],
                    [
                        'base_currency' => 'EUR',
                    ]
                );
                $request->attributes->set('tenant', $tenant);
                $request->attributes->set('instanceToken', $token ?? $devToken);
                return $next($request);
            }
        }

        if (!$token) {
            return response()->json(['error' => 'Unauthorized – missing Wix token'], 401);
        }

        $payload = $this->decodeWixToken($token);

        if (!$payload || empty($payload['instanceId'])) {
            return response()->json(['error' => 'Unauthorized – invalid token'], 401);
        }

        $wixSiteId = $payload['instanceId'];

        $tenant = Tenant::firstOrCreate(
            ['wix_site_id' => $wixSiteId],
            ['plan' => 'free']
        );

        $tenant->settings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'base_currency' => 'EUR',
            ]
        );

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('instanceToken', $token);

        return $next($request);
    }

    /**
     * Decode and verify the Wix instance token.
     *
     * Wix instance tokens are base64url-encoded, dot-separated:
     *   signature.payload (2-part) or header.payload.signature (JWT, 3-part)
     */
    private function decodeWixToken(string $token): ?array
    {
        $parts = explode('.', $token);
        $count = count($parts);

        if ($count === 4) {
            // Wix 4-part format: sig.header.payload.extra or header.payload.sig.extra
            // Try part index 2 as payload (most common)
            $signatureB64  = $parts[0];
            $payloadB64    = $parts[2];
            $signingInput  = $parts[1] . '.' . $parts[2];
        } elseif ($count === 3) {
            // JWT format: header.payload.signature — signed over "header.payload"
            $signatureB64  = $parts[2];
            $payloadB64    = $parts[1];
            $signingInput  = $parts[0] . '.' . $parts[1];
        } elseif ($count === 2) {
            // Wix 2-part format: signature.payload — signed over payload
            $signatureB64  = $parts[0];
            $payloadB64    = $parts[1];
            $signingInput  = $parts[1];
        } else {
            return null;
        }

        $secret = config('services.wix.app_secret');
        if ($secret) {
            $expectedSig = hash_hmac('sha256', $signingInput, $secret, true);
            $actualSig = base64_decode(strtr($signatureB64, '-_', '+/'));
            if (!$actualSig || !hash_equals($expectedSig, $actualSig)) {
                return null;
            }
        }

        $json = base64_decode(strtr($payloadB64, '-_', '+/'));

        if (!$json) return null;

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }
}
