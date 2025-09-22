<?php

namespace Iquesters\Integration\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationIntegrationMeta extends Model
{
    protected $table = 'organisation_integration_metas';

    protected $fillable = [
        'ref_parent',
        'meta_key',
        'meta_value',
        'status',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the organisation integration this meta belongs to
     */
    public function organisationIntegration()
    {
        return $this->belongsTo(OrganisationIntegration::class, 'ref_parent');
    }
}