<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use App\Models\Configuracion\configVentanilla;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVentanilla extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_ventanillas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ventanilla_id'
    ];

    /**
     * Get the user that owns the ventanilla assignment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the ventanilla assigned to the user.
     */
    public function ventanilla()
    {
        return $this->belongsTo(configVentanilla::class, 'ventanilla_id');
    }
}
