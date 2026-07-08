<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;

class WorkflowAudit extends Model
{
    const UPDATED_AT = null;

    protected $table = 'workflow_audits';

    protected $fillable = [
        'workflow_id',
        'instancia_id',
        'user_id',
        'action',
        'payload_json',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];
}
