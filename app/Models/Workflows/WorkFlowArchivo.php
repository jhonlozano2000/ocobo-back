<?php

namespace App\Models\Workflows;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkFlowArchivo extends Model
{
    use HasFactory;

    protected $table = 'work_flow_archivos';

    protected $fillable = [
        'workflow_id',
        'archivable_type',
        'archivable_id',
        'nombre_original',
        'nombre_almacenado',
        'ruta_almacenada',
        'mime_type',
        'peso_bytes',
        'hash_sha256',
        'disk',
        'categoria',
        'uploaded_by',
    ];

    protected $casts = [
        'peso_bytes' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function archivable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
