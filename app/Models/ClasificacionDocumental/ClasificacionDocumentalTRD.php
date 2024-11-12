<?php

namespace App\Models\ClasificacionDocumental;

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
    ];

    public function children()
    {
        return $this->hasMany(ClasificacionDocumentalTRD::class, 'parent')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(ClasificacionDocumentalTRD::class, 'parent');
    }

    // Relación con User a través de la tabla pivote
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_register')
            ->withPivot('start_date', 'end_date')
            ->withTimestamps();
    }

    // Obtener los usuarios actuales que ocupan este cargo
    public function currentUsers()
    {
        return $this->users()->wherePivot('end_date', null)->get();
    }

    // Obtener el historial de usuarios que han ocupado este cargo
    public function usersHistory()
    {
        return $this->users()->withPivot('start_date', 'end_date')->get();
    }
}
