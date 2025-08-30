<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationMeta extends Model
{
    use HasFactory;

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
    public function integration()
    {
        return $this->belongsTo(Integration::class, 'ref_parent');
    }
}