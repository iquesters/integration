<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class SupportedIntegration extends Model
{
    use HasFactory;
    
    protected $table = 'supported_integrations';
    
    protected $fillable = [
        'uid',
        'name',
        'small_name',
        'nature',
        'status',
        'category',
        'created_by',
        'updated_by',
    ];

    /**
     * Relationship: Entity has many EntityMeta records
     */
    public function metas(): HasMany
    {
        return $this->hasMany(SupportedIntegrationMeta::class, 'ref_parent');
    }

    /**
     * Retrieve a specific meta value by key.
     */
    public function getMeta(string $key)
    {
        return optional(
            $this->metas()->where('meta_key', $key)->first()
        )->meta_value;
    }

    /**
     * Create or update a meta value by key.
     */
    public function setMeta(string $key, $value)
    {
        return $this->metas()->updateOrCreate(
            ['meta_key' => $key],
            ['meta_value' => $value]
        );
    }
}