<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'integrations';

    /**
     * Mass-assignable attributes.
     */
    protected $fillable = [
        'user_id',
        'supported_integration_id',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the supported (master) integration definition.
     *
     * Example: Zoho Books, Slack, Stripe, etc.
     */
    public function supportedIntegration()
    {
        return $this->belongsTo(
            SupportedIntegration::class,
            'supported_integration_id'
        );
    }

    /**
     * Get all meta records associated with this integration.
     *
     * These store user-specific configuration values
     * (tokens, flags, settings, etc.).
     */
    public function metas()
    {
        return $this->hasMany(
            IntegrationMeta::class,
            'ref_parent'
        );
    }

    /**
     * Retrieve a meta value by its key.
     *
     * @param string $key     Meta key name
     * @param mixed  $default Default value if meta is not found
     *
     * @return mixed
     */
    public function getMeta(string $key, $default = null)
    {
        $meta = $this->metas()
            ->where('meta_key', $key)
            ->first();

        return $meta ? $meta->meta_value : $default;
    }

    /**
     * Create or update a meta value for this integration.
     *
     * If the meta key already exists, its value will be updated.
     * Otherwise, a new meta record will be created.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Determine whether the integration is active.
     *
     * Uses the `is_active` meta flag:
     * - '1' => active
     * - '0' => inactive
     */
    public function isActive(): bool
    {
        return $this->getMeta('is_active', '0') === '1';
    }

    /**
     * Retrieve selected Zoho Books API-related metas.
     *
     * The `ZB_api_id` meta is expected to store a JSON array
     * of SupportedIntegrationMeta IDs.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSelectedZohoBooksMetas()
    {
        $meta = $this->metas()
            ->where('meta_key', 'ZB_api_id')
            ->first();

        if (!$meta || empty($meta->meta_value)) {
            return collect();
        }

        $ids = json_decode($meta->meta_value, true) ?? [];

        return SupportedIntegrationMeta::whereIn('id', $ids)->get();
    }
}