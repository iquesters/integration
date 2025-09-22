<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtIntegration extends Model
{
    use HasFactory;

    protected $table = 'ext_integrations';

    protected $fillable = [
        'uid',
        'org_inte_id',
        'ext_id',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization integration meta that owns this external integration.
     */
    public function organisationIntegrationMeta(): BelongsTo
    {
        return $this->belongsTo(OrganisationIntegrationMeta::class, 'org_inte_id');
    }

    /**
     * Get all meta data for this external integration.
     */
    public function metas(): HasMany
    {
        return $this->hasMany(ExtIntegrationMeta::class, 'ref_parent');
    }

    /**
     * Get a specific meta value by key.
     */
    public function meta(string $key): ?string
    {
        $meta = $this->metas()->where('meta_key', $key)->first();
        return $meta ? $meta->meta_value : null;
    }

    /**
     * Get decoded meta value by key.
     */
    public function getDecodedMeta(string $key): ?array
    {
        $metaValue = $this->meta($key);
        if (!$metaValue) {
            return null;
        }

        $decoded = json_decode($metaValue, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Set meta value for this external integration.
     */
    public function setMeta(string $key, $value, string $status = 'active'): ExtIntegrationMeta
    {
        $metaValue = is_array($value) ? json_encode($value) : $value;

        return ExtIntegrationMeta::updateOrCreate(
            [
                'ref_parent' => $this->id,
                'meta_key' => $key
            ],
            [
                'meta_value' => $metaValue,
                'status' => $status,
                'updated_by' => auth()->id() ?? 0
            ]
        );
    }

    /**
     * Scope to filter by status.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by organization integration meta ID.
     */
    public function scopeForOrgIntegration($query, $orgInteId)
    {
        return $query->where('org_inte_id', $orgInteId);
    }

    /**
     * Get the sync data for this external integration.
     */
    public function getSyncDataAttribute(): ?array
    {
        return $this->getDecodedMeta('syncdata');
    }

    /**
     * Check if this external integration has sync data.
     */
    public function hasSyncData(): bool
    {
        return !is_null($this->sync_data);
    }
}