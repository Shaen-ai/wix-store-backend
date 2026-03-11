<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\WixWebhook;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class WixController extends Controller
{
    /**
     * Handle Wix app lifecycle webhooks for 3D Store.
     * Proactively provisions tenants, updates plans, and logs events.
     */
    public function handleWixWebhooks(Request $request)
    {
        $body      = file_get_contents('php://input');
        $publicKey = config('services.wix.3d_store_public_key');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        try {
            $decoded   = JWT::decode($body, new Key($publicKey, 'RS256'));
            $event     = json_decode($decoded->data);
            $eventData = json_decode($event->data ?? '{}');
            $identity  = json_decode($event->identity ?? '{}');
        } catch (Exception $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()], 400);
        }

        $instanceId = $event->instanceId ?? null;
        if (!$instanceId) {
            return response()->json(['error' => 'Missing instanceId'], 400);
        }

        // Log webhook for audit
        $this->logWebhook($event, $eventData, $identity);

        // 3D Store–specific handling
        switch ($event->eventType) {
            case 'AppInstalled':
                $this->handleAppInstalled($instanceId, $eventData);
                break;

            case 'AppRemoved':
                $this->handleAppRemoved($instanceId);
                break;

            case 'PaidPlanPurchased':
            case 'PaidPlanChanged':
                $this->handlePlanUpgrade($instanceId);
                break;

            case 'PaidPlanAutoRenewalCancelled':
                $this->handlePlanDowngrade($instanceId);
                break;

            case 'SitePropertiesUpdated':
                // Optional: sync site metadata if needed later
                break;
        }

        return response('', 200);
    }

    private function logWebhook($event, $eventData, $identity): void
    {
        $webhookData = [
            'type'     => '3D Store ' . ($event->eventType ?? 'Unknown'),
            'instance' => $event->instanceId ?? null,
            'content'  => ['identity' => $identity, 'data' => $eventData],
        ];

        if (isset($identity->wixUserId)) {
            $webhookData['user_id'] = $identity->wixUserId;
        }

        if (in_array($event->eventType ?? '', ['AppInstalled', 'SitePropertiesUpdated'])
            && isset($eventData->originInstanceId)) {
            $webhookData['origin_instance'] = $eventData->originInstanceId;
        }

        WixWebhook::create($webhookData);
    }

    private function handleAppInstalled(string $instanceId, $eventData): void
    {
        $tenant = Tenant::firstOrCreate(
            ['wix_site_id' => $instanceId],
            ['plan' => 'free']
        );

        $tenant->settings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['base_currency' => 'EUR']
        );
    }

    private function handleAppRemoved(string $instanceId): void
    {
        // Tenant data retained for potential reinstall; can add uninstalled_at later if needed
    }

    private function handlePlanUpgrade(string $instanceId): void
    {
        Tenant::where('wix_site_id', $instanceId)->update(['plan' => 'premium']);
    }

    private function handlePlanDowngrade(string $instanceId): void
    {
        Tenant::where('wix_site_id', $instanceId)->update(['plan' => 'free']);
    }
}
