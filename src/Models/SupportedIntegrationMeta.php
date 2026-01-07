<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportedIntegrationMeta extends Model
{
    use HasFactory;
    
    protected $table = 'supported_integration_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Meta belongs to a single integration.
     */
    public function supportedIntegration(): BelongsTo
    {
        return $this->belongsTo(SupportedIntegration::class, 'ref_parent');
    }
}