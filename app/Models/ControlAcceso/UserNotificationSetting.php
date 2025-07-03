<?php

namespace App\Models\ControlAcceso;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    use HasFactory;

    protected $table = 'users_notification_settings';

    protected $fillable = ['user_id', 'new_for_you', 'account_activity', 'new_browser_login'];
}
