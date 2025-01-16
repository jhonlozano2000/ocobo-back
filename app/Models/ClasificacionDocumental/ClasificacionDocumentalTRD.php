<?php

namespace App\Models\ClasificacionDocumental;

use App\Models\Calidad\CalidadOrganigrama;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClasificacionDocumentalTRD extends Model
{
    use HasFactory;

    protected $table = 'clasificacion_documental_trd';

    protected $fillable = [
        'tipo',
        'cod',
        'nom',
        'a_g',
        'a_c',
        'ct',
        'e',
        'm_d',
        's',
        'procedimiento',
        'parent',
        'dependencia_id',
        'user_register',
        'estado'
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent');
    }

    public function dependencia()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'dependencia_id');
    }

    public function scopeSeries($query)
    {
        return $query->where('tipo', 'Serie')->whereNull('parent');
    }
}
