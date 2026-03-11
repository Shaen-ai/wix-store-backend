<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'wix_site_id' => $tenant->wix_site_id,
                'plan' => $tenant->plan,
                'created_at' => $tenant->created_at,
            ],
        ]);
    }

    public function getSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $tenant->settings;

        return response()->json([
            'data' => [
                'notification_email' => $settings->notification_email ?? '',
                'paypal_receiver_email' => $settings->paypal_receiver_email ?? '',
                'base_currency' => $settings->base_currency ?? 'EUR',
                'fx_provider' => $settings->fx_provider ?? 'exchangerate',
                'fx_api_key' => $settings->fx_api_key ? '••••••' : '',
                'image_to_3d_provider' => $settings->image_to_3d_provider ?? 'meshy',
                'image_to_3d_api_key' => $settings->image_to_3d_api_key ? '••••••' : '',
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Convert empty strings to null so nullable|email accepts cleared fields
        $request->merge([
            'notification_email' => $request->input('notification_email') === '' ? null : $request->input('notification_email'),
            'paypal_receiver_email' => $request->input('paypal_receiver_email') === '' ? null : $request->input('paypal_receiver_email'),
        ]);

        $validated = $request->validate([
            'notification_email' => 'nullable|email|max:255',
            'paypal_receiver_email' => 'nullable|email|max:255',
            'base_currency' => 'nullable|string|size:3',
            'fx_provider' => 'nullable|string|max:50',
            'fx_api_key' => 'nullable|string|max:255',
            'image_to_3d_provider' => 'nullable|string|max:50',
            'image_to_3d_api_key' => 'nullable|string|max:255',
        ]);

        $settings = $tenant->settings;
        $updateData = [];

        foreach (['notification_email', 'paypal_receiver_email', 'base_currency', 'fx_provider', 'image_to_3d_provider'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }

        // Only update API keys if non-masked value provided
        foreach (['fx_api_key', 'image_to_3d_api_key'] as $keyField) {
            if (isset($validated[$keyField]) && $validated[$keyField] !== '••••••') {
                $updateData[$keyField] = $validated[$keyField];
            }
        }

        $settings->update($updateData);
        $settings->refresh();

        return response()->json([
            'data' => [
                'notification_email' => $settings->notification_email ?? '',
                'paypal_receiver_email' => $settings->paypal_receiver_email ?? '',
                'base_currency' => $settings->base_currency ?? 'EUR',
                'fx_provider' => $settings->fx_provider ?? 'exchangerate',
                'fx_api_key' => $settings->fx_api_key ? '••••••' : '',
                'image_to_3d_provider' => $settings->image_to_3d_provider ?? 'meshy',
                'image_to_3d_api_key' => $settings->image_to_3d_api_key ? '••••••' : '',
            ],
            'message' => 'Settings updated',
        ]);
    }
}
