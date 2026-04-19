<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutField extends Model
{
    protected $fillable = [
        'tenant_id',
        'field_key',
        'label',
        'type',
        'placeholder',
        'options_json',
        'is_required',
        'sort_order',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function seedDefaults(int $tenantId): void
    {
        $defaults = [
            ['field_key' => 'full_name',  'label' => 'Full Name',        'type' => 'text',     'placeholder' => 'John Doe',           'is_required' => true,  'sort_order' => 1],
            ['field_key' => 'email',      'label' => 'Email',            'type' => 'email',    'placeholder' => 'john@example.com',    'is_required' => true,  'sort_order' => 2],
            ['field_key' => 'phone',      'label' => 'Phone Number',     'type' => 'phone',    'placeholder' => '+1 234 567 8900',     'is_required' => true,  'sort_order' => 3],
            ['field_key' => 'country',    'label' => 'Country',          'type' => 'select',   'placeholder' => 'Select your country', 'is_required' => true,  'sort_order' => 4, 'options_json' => self::defaultCountries()],
            ['field_key' => 'city',       'label' => 'City',             'type' => 'text',     'placeholder' => 'New York',            'is_required' => true,  'sort_order' => 5],
            ['field_key' => 'address',    'label' => 'Address',          'type' => 'text',     'placeholder' => '123 Main St, Apt 4',  'is_required' => true,  'sort_order' => 6],
            ['field_key' => 'zip_code',   'label' => 'ZIP / Postal Code','type' => 'text',     'placeholder' => '10001',               'is_required' => true,  'sort_order' => 7],
            ['field_key' => 'notes',      'label' => 'Order Notes',      'type' => 'textarea', 'placeholder' => 'Special instructions…','is_required' => false, 'sort_order' => 8],
        ];

        foreach ($defaults as $field) {
            self::firstOrCreate(
                ['tenant_id' => $tenantId, 'field_key' => $field['field_key']],
                array_merge($field, ['tenant_id' => $tenantId, 'is_default' => true, 'is_active' => true])
            );
        }
    }

    private static function defaultCountries(): array
    {
        return [
            'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany',
            'France', 'Italy', 'Spain', 'Netherlands', 'Belgium', 'Switzerland',
            'Austria', 'Sweden', 'Norway', 'Denmark', 'Finland', 'Ireland',
            'Portugal', 'Poland', 'Czech Republic', 'Romania', 'Hungary',
            'Israel', 'Japan', 'China', 'India', 'Brazil', 'Mexico', 'Turkey',
            'South Korea', 'Singapore', 'New Zealand', 'South Africa', 'Greece',
            'Argentina', 'Chile', 'Colombia', 'Thailand', 'Philippines',
            'Malaysia', 'Indonesia', 'Vietnam', 'Egypt', 'Nigeria', 'Kenya',
            'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'Kuwait', 'Other',
        ];
    }
}
