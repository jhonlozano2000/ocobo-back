<?php

namespace App\Models\ControlAcceso;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users_notification_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'new_for_you',
        'account_activity',
        'new_browser_login',
        'new_device_linked',
        'email_notifications',
        'new_for_you_email',
        'new_for_you_browser',
        'account_activity_email',
        'account_activity_browser',
        'new_browser_login_email',
        'new_browser_login_browser',
        'new_device_linked_email',
        'new_device_linked_browser',
        'vencimientos',
        'vencimientos_email',
        'vencimientos_browser',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'new_for_you' => 'boolean',
        'account_activity' => 'boolean',
        'new_browser_login' => 'boolean',
        'new_device_linked' => 'boolean',
        'email_notifications' => 'boolean',
        'new_for_you_email' => 'boolean',
        'new_for_you_browser' => 'boolean',
        'account_activity_email' => 'boolean',
        'account_activity_browser' => 'boolean',
        'new_browser_login_email' => 'boolean',
        'new_browser_login_browser' => 'boolean',
        'new_device_linked_email' => 'boolean',
        'new_device_linked_browser' => 'boolean',
        'vencimientos' => 'boolean',
        'vencimientos_email' => 'boolean',
        'vencimientos_browser' => 'boolean',
    ];

    /**
     * Categorías que el usuario puede configurar, con los canales que ofrece cada una y su valor por
     * defecto (el mismo de la tabla). app = campana, email = correo, browser = aviso emergente.
     * new_device_linked y email_notifications no se usan: OCOBO no registra dispositivos.
     */
    public const CATEGORIAS = [
        'new_for_you' => ['app' => true, 'email' => false, 'browser' => true],
        'vencimientos' => ['app' => true, 'email' => true, 'browser' => true],
        'account_activity' => ['app' => true, 'browser' => true],
        'new_browser_login' => ['app' => true, 'email' => false, 'browser' => false],
    ];

    public static function columna(string $categoria, string $canal): string
    {
        return $canal === 'app' ? $categoria : "{$categoria}_{$canal}";
    }

    /**
     * Sin preferencias guardadas rige el valor por defecto; un canal que la categoría no ofrece está apagado.
     */
    public static function canalActivo(?self $settings, string $categoria, string $canal): bool
    {
        if (! array_key_exists($canal, self::CATEGORIAS[$categoria] ?? [])) {
            return false;
        }

        return $settings ? (bool) $settings->{self::columna($categoria, $canal)} : self::CATEGORIAS[$categoria][$canal];
    }

    /**
     * @return array<string, array<string, bool>> categoría => canal => activo
     */
    public function formulario(): array
    {
        $formulario = [];
        foreach (self::CATEGORIAS as $categoria => $canales) {
            foreach (array_keys($canales) as $canal) {
                $formulario[$categoria][$canal] = self::canalActivo($this, $categoria, $canal);
            }
        }

        return $formulario;
    }

    /**
     * Columnas a guardar a partir del formulario (solo las categorías y canales enviados).
     */
    public static function columnasDesde(array $formulario): array
    {
        $columnas = [];
        foreach (self::CATEGORIAS as $categoria => $canales) {
            foreach (array_keys($canales) as $canal) {
                if (isset($formulario[$categoria][$canal])) {
                    $columnas[self::columna($categoria, $canal)] = (bool) $formulario[$categoria][$canal];
                }
            }
        }

        return $columnas;
    }

    /**
     * Get the user that owns the notification settings.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notification settings for a specific user.
     *
     * @return static|null
     */
    public static function forUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Check if a specific notification type is enabled.
     */
    public function isEnabled(string $type): bool
    {
        return $this->{$type} ?? false;
    }

    /**
     * Enable a specific notification type.
     */
    public function enable(string $type): bool
    {
        return $this->update([$type => true]);
    }

    /**
     * Disable a specific notification type.
     */
    public function disable(string $type): bool
    {
        return $this->update([$type => false]);
    }
}
