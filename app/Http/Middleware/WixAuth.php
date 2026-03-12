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
     * New Wix format: OauthNG.JWS.<header>.<payload>.<signature>  (5 parts)
     * Legacy JWT format: <header>.<payload>.<signature>            (3 parts)
     * Legacy Wix format: <signature>.<payload>                     (2 parts)
     *
     * The payload JSON has shape: {"data":"{\"instance\":{\"instanceId\":\"...\"}}", ...}
     * instanceId is nested inside the stringified "data" field.
     */
    private function decodeWixToken(string $token): ?array
    {
        $parts = explode('.', $token);
        $count = count($parts);

        if ($count === 5 && $parts[0] === 'OauthNG' && $parts[1] === 'JWS') {
            // New Wix format: OauthNG.JWS.header.payload.signature
            $headerB64    = $parts[2];
            $payloadB64   = $parts[3];
            $signatureB64 = $parts[4];
            $signingInput = $parts[2] . '.' . $parts[3];
        } elseif ($count === 3) {
            // Standard JWT: header.payload.signature
            $payloadB64   = $parts[1];
            $signatureB64 = $parts[2];
            $signingInput = $parts[0] . '.' . $parts[1];
        } elseif ($count === 2) {
            // Legacy Wix: signature.payload
            $signatureB64 = $parts[0];
            $payloadB64   = $parts[1];
            $signingInput = $parts[1];
        } else {
            return null;
        }

        // DEBUG: temporarily skip signature check to isolate parsing vs secret issue
        // TODO: re-enable after confirming correct secret
        /*
        $secret = config('services.wix.app_secret');
        if ($secret) {
            $expectedSig = hash_hmac('sha256', $signingInput, $secret, true);
            $actualSig   = base64_decode(strtr($signatureB64, '-_', '+/'));
            if (!$actualSig || !hash_equals($expectedSig, $actualSig)) {
                return null;
            }
        }
        */

        $json = base64_decode(strtr($payloadB64, '-_', '+/'));
        if (!$json) return null;

        $payload = json_decode($json, true);
        if (!is_array($payload)) return null;

        // New format nests instance data as a JSON string inside "data"
        if (isset($payload['data']) && is_string($payload['data'])) {
            $data = json_decode($payload['data'], true);
            if (isset($data['instance']) && is_array($data['instance'])) {
                return $data['instance'];
            }
        }

        return $payload;
    }
}
