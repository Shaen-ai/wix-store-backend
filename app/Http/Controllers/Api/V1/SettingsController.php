<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WidgetSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get unified global settings.
     * Query params: comp_id (required), instance (optional), source (widget|dashboard).
     * When source=widget: API provider fields (fx_provider, fx_api_key, image_to_3d_*) are excluded.
     * When source=dashboard or omitted: full settings including API providers.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $compId = trim((string) ($request->query('comp_id') ?? ''));
        $compId = $compId !== '' ? $compId : $this->getDefaultCompId();
        $instance = $request->query('instance');
        $source = strtolower(trim((string) ($request->query('source') ?? 'dashboard')));
        $isWidget = $source === 'widget';

        $tenantSettings = $tenant->settings;
        $ws = WidgetSetting::where('tenant_id', $tenant->id)
            ->where('widget_instance_id', $compId)
            ->first();

        $widgetDefaults = [
            'base_currency' => $tenantSettings->base_currency ?? 'EUR',
            'grid_columns' => 3,
            'layout' => 'grid',
            'autoplay' => true,
            'one_page' => false,
            'thumb_ratio' => 'square',
            'darken_bg' => true,
            'show_name' => 'show',
            'show_price' => 'show',
            'show_availability' => 'hide',
            'card_border' => false,
        ];

        $widgetData = $ws ? $ws->settings_json : $widgetDefaults;
        $widgetData = array_merge($widgetDefaults, is_array($widgetData) ? $widgetData : []);
        unset($widgetData['default_currency']); // Removed: use base_currency from tenant only

        $settings = array_merge([
            'notification_email' => $tenantSettings->notification_email ?? '',
            'paypal_receiver_email' => $tenantSettings->paypal_receiver_email ?? '',
            'base_currency' => $tenantSettings->base_currency ?? 'EUR',
        ], $widgetData);

        if (!$isWidget) {
            $settings['fx_provider'] = $tenantSettings->fx_provider ?? 'exchangerate';
            $settings['fx_api_key'] = $tenantSettings->fx_api_key ? '••••••' : '';
            $settings['image_to_3d_provider'] = $tenantSettings->image_to_3d_provider ?? 'meshy';
            $settings['image_to_3d_api_key'] = $tenantSettings->image_to_3d_api_key ? '••••••' : '';
        }

        $meta = [
            'instance_token' => $request->attributes->get('instanceToken'),
        ];

        return response()->json([
            'data' => [
                'comp_id' => $compId,
                'instance' => $instance,
                'settings' => $settings,
            ],
            'meta' => $meta,
        ]);
    }

    /**
     * Update tenant and/or widget settings.
     * Query params: comp_id (required), instance (optional).
     * Body: tenant_settings (optional), widget_settings (optional).
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $compId = trim((string) ($request->query('comp_id') ?? ''));
        $compId = $compId !== '' ? $compId : $this->getDefaultCompId();

        $request->validate([
            'tenant_settings' => 'nullable|array',
            'tenant_settings.notification_email' => 'nullable|email|max:255',
            'tenant_settings.paypal_receiver_email' => 'nullable|email|max:255',
            'tenant_settings.base_currency' => 'nullable|string|size:3',
            'tenant_settings.fx_provider' => 'nullable|string|max:50',
            'tenant_settings.fx_api_key' => 'nullable|string|max:255',
            'tenant_settings.image_to_3d_provider' => 'nullable|string|max:50',
            'tenant_settings.image_to_3d_api_key' => 'nullable|string|max:255',
            'widget_settings' => 'nullable|array',
            'widget_settings.grid_columns' => 'nullable|integer|min:1|max:6',
            'widget_settings.layout' => 'nullable|string|in:grid,list,masonry,magazine,showcase,reels,commerce,gallery',
            'widget_settings.autoplay' => 'nullable|boolean',
            'widget_settings.one_page' => 'nullable|boolean',
            'widget_settings.thumb_ratio' => 'nullable|string|in:square,portrait,landscape,wide',
            'widget_settings.darken_bg' => 'nullable|boolean',
            'widget_settings.show_name' => 'nullable|string|in:show,hide',
            'widget_settings.show_price' => 'nullable|string|in:show,hide',
            'widget_settings.show_availability' => 'nullable|string|in:show,hide',
            'widget_settings.card_border' => 'nullable|boolean',
        ]);

        $tenantSettings = $tenant->settings;
        $tenantData = $request->input('tenant_settings', []);

        if (!empty($tenantData)) {
            $emailVal = $tenantData['notification_email'] ?? null;
            $paypalVal = $tenantData['paypal_receiver_email'] ?? null;
            if (($emailVal !== null && $emailVal !== '') || ($paypalVal !== null && $paypalVal !== '')) {
                Validator::make(
                    [
                        'notification_email' => $emailVal ?: null,
                        'paypal_receiver_email' => $paypalVal ?: null,
                    ],
                    [
                        'notification_email' => 'nullable|email|max:255',
                        'paypal_receiver_email' => 'nullable|email|max:255',
                    ]
                )->validate();
            }
            $updateData = [];
            foreach (['notification_email', 'paypal_receiver_email', 'base_currency', 'fx_provider', 'image_to_3d_provider'] as $field) {
                if (array_key_exists($field, $tenantData)) {
                    $val = $tenantData[$field];
                    $updateData[$field] = ($val === '' || $val === null) && in_array($field, ['notification_email', 'paypal_receiver_email']) ? null : $val;
                }
            }
            foreach (['fx_api_key', 'image_to_3d_api_key'] as $keyField) {
                if (isset($tenantData[$keyField]) && $tenantData[$keyField] !== '••••••') {
                    $updateData[$keyField] = $tenantData[$keyField];
                }
            }
            if (!empty($updateData)) {
                $tenantSettings->update($updateData);
                $tenantSettings->refresh();
            }
        }

        $widgetData = $request->input('widget_settings');
        if (!empty($widgetData) && is_array($widgetData)) {
            unset($widgetData['default_currency'], $widgetData['base_currency']); // Currency from tenant only
            WidgetSetting::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'widget_instance_id' => $compId,
                ],
                ['settings_json' => $widgetData]
            );
        }

        return $this->show($request);
    }

    private function getDefaultCompId(): string
    {
        return config('services.wix.dev_comp_id', 'comp-dev-local');
    }
}
