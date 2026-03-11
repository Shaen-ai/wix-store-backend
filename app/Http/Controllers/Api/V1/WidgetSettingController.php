<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WidgetSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetSettingController extends Controller
{
    public function show(Request $request, string $widgetInstanceId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $ws = WidgetSetting::where('tenant_id', $tenant->id)
            ->where('widget_instance_id', $widgetInstanceId)
            ->first();

        $baseCurrency = $tenant->settings?->base_currency ?? 'EUR';
        if (!$ws) {
            return response()->json([
                'data' => [
                    'widget_instance_id' => $widgetInstanceId,
                    'settings' => [
                        'base_currency' => $baseCurrency,
                        'grid_columns' => 3,
                        'layout' => 'grid',
                        'autoplay' => true,
                        'one_page' => false,
                        'thumb_ratio' => 'square',
                        'darken_bg' => true,
                        'show_name' => 'show',
                        'show_price' => 'show',
                        'card_border' => false,
                    ],
                ],
            ]);
        }

        $merged = array_merge(is_array($ws->settings_json) ? $ws->settings_json : [], [
            'base_currency' => $tenant->settings?->base_currency ?? 'EUR',
        ]);
        return response()->json([
            'data' => [
                'id' => $ws->id,
                'widget_instance_id' => $ws->widget_instance_id,
                'settings' => $merged,
            ],
        ]);
    }

    public function update(Request $request, string $widgetInstanceId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $request->validate([
            'settings' => 'required|array',
            'settings.grid_columns' => 'nullable|integer|min:1|max:6',
            'settings.layout' => 'nullable|string|in:grid,list,masonry,magazine,showcase,reels,commerce,gallery',
            'settings.autoplay' => 'nullable|boolean',
            'settings.one_page' => 'nullable|boolean',
            'settings.thumb_ratio' => 'nullable|string|in:square,portrait,landscape,wide',
            'settings.darken_bg' => 'nullable|boolean',
            'settings.show_name' => 'nullable|string|in:show,hide',
            'settings.show_price' => 'nullable|string|in:show,hide',
            'settings.card_border' => 'nullable|boolean',
        ]);

        $settings = $request->input('settings');
        unset($settings['default_currency'], $settings['base_currency']); // Currency comes from tenant_settings only

        $ws = WidgetSetting::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'widget_instance_id' => $widgetInstanceId,
            ],
            [
                'settings_json' => $settings,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $ws->id,
                'widget_instance_id' => $ws->widget_instance_id,
                'settings' => $ws->settings_json,
            ],
            'message' => 'Widget settings saved',
        ]);
    }
}
