<?php

namespace App\Models\Calidad;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CalidadOrganigrama extends Model
{
    use HasFactory;

    protected $table = 'calidad_organigrama'; // Asegúrate de que este nombre sea correcto y en singular
    protected $fillable = ['tipo', 'nom_organico', 'cod_organico', 'observaciones', 'parent'];

    public function children()
    {
        return $this->hasMany(CalidadOrganigrama::class, 'parent')->with('children');
    }

    public function parent()
    {
        return $this->belongsTo(CalidadOrganigrama::class, 'parent');
    }

    // Relación con User a través de la tabla pivote
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organigrama_user')
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
