<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtIntegrationMeta extends Model
{
    use HasFactory;

    protected $table = 'ext_integration_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by'
    ];

    public function extIntegration()
    {
        return $this->belongsTo(ExtIntegration::class, 'ref_parent');
    }
}