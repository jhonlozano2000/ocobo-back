<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigDiviPoli extends Model
{
    use HasFactory;

    protected $table = 'config_divi_poli';
    protected $fillable = ['parent', 'codigo', 'nombre', 'tipo'];

    // Relación con el padre (División política superior)
    public function padre()
    {
        return $this->belongsTo(ConfigDiviPoli::class, 'parent');
    }

    // Relación con las divisiones políticas hijas
    public function children()
    {
        return $this->hasMany(ConfigDiviPoli::class, 'parent');
    }
}
