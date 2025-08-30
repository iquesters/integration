<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'uid',
        'name',
        'small_name',
        'nature',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * One Integration has many meta records.
     */
    public function metas()
    {
        return $this->hasMany(IntegrationMeta::class, 'ref_parent');
    }
}