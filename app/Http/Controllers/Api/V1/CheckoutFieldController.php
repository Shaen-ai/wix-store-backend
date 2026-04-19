<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CheckoutField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutFieldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $fields = CheckoutField::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get();

        // Seed defaults on first access if none exist
        if ($fields->isEmpty()) {
            CheckoutField::seedDefaults($tenant->id);
            $fields = CheckoutField::where('tenant_id', $tenant->id)
                ->orderBy('sort_order')
                ->get();
        }

        return response()->json(['data' => $fields]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'field_key' => 'required|string|max:64|regex:/^[a-z_]+$/',
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,email,phone,textarea,select',
            'placeholder' => 'nullable|string|max:255',
            'options_json' => 'nullable|array',
            'options_json.*' => 'string|max:255',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $existing = CheckoutField::where('tenant_id', $tenant->id)
            ->where('field_key', $validated['field_key'])
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'A field with this key already exists'], 422);
        }

        $maxSort = CheckoutField::where('tenant_id', $tenant->id)->max('sort_order') ?? 0;

        $field = CheckoutField::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'sort_order' => $maxSort + 1,
            'is_default' => false,
        ]));

        return response()->json(['data' => $field], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $field = CheckoutField::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:text,email,phone,textarea,select',
            'placeholder' => 'nullable|string|max:255',
            'options_json' => 'nullable|array',
            'options_json.*' => 'string|max:255',
            'is_required' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $field->update($validated);

        return response()->json(['data' => $field]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $field = CheckoutField::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($field->is_default) {
            return response()->json(['error' => 'Default fields cannot be deleted. You can deactivate them instead.'], 422);
        }

        $field->delete();

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'order' => 'required|array|min:1',
            'order.*.id' => 'required|integer',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['order'] as $item) {
            CheckoutField::where('tenant_id', $tenant->id)
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        $fields = CheckoutField::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $fields]);
    }
}
