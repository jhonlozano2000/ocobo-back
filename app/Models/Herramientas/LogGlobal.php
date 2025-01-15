<?php

namespace App\Models\Herramientas;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogGlobal extends Model
{
    use HasFactory;

    protected $table = 'logs_globales';

    protected $fillable = ['user_id', 'accion', 'detalles', 'ip', 'user_agent'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
