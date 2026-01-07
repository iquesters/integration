<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationMeta extends Model
{
    use HasFactory;
    
    protected $table = 'integration_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the integration this meta belongs to
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class, 'ref_parent');
    }
}