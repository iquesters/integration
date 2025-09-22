<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationIntegration extends Model
{
    protected $table = 'organisation_integration';

    protected $fillable = [
        'organisation_id',
        'integration_masterdata_id',
        'status',
        'created_by',
        'updated_by',
    ];

    // Optional: link to Organisation if it exists
    public function organisation()
    {
        return class_exists(\App\Models\Organisation::class)
            ? $this->belongsTo(\App\Models\Organisation::class)
            : null;
    }

    // Link to integration masterdata
    public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_masterdata_id');
    }

    // Link to meta records
    public function metas()
    {
        return $this->hasMany(IntegrationMeta::class, 'ref_parent');
    }

    // Get meta value by key
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : $default;
    }

    // Check if integration is active
    public function isActive(): bool
    {
        return $this->getMeta('is_active', '0') === '1';
    }

    // Get selected integration metas for Zoho Books
    public function getSelectedZohoBooksMetas()
    {
        $meta = $this->metas()->where('meta_key', 'ZB_api_id')->first();

        if (!$meta || empty($meta->meta_value)) {
            return collect();
        }

        $ids = json_decode($meta->meta_value, true) ?? [];
        return IntegrationMeta::whereIn('id', $ids)->get();
    }
}