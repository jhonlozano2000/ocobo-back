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
        'new_browser_login'
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
    ];

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
     * @param int $userId
     * @return static|null
     */
    public static function forUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Check if a specific notification type is enabled.
     *
     * @param string $type
     * @return bool
     */
    public function isEnabled(string $type): bool
    {
        return $this->{$type} ?? false;
    }

    /**
     * Enable a specific notification type.
     *
     * @param string $type
     * @return bool
     */
    public function enable(string $type): bool
    {
        return $this->update([$type => true]);
    }

    /**
     * Disable a specific notification type.
     *
     * @param string $type
     * @return bool
     */
    public function disable(string $type): bool
    {
        return $this->update([$type => false]);
    }
}
