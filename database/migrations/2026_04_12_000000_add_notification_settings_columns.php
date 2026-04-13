<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_notification_settings', function (Blueprint $table) {
            $table->boolean('new_device_linked')->default(false)->after('new_browser_login');
            $table->boolean('email_notifications')->default(true)->after('new_device_linked');
            
            $table->boolean('new_for_you_email')->default(true)->after('email_notifications');
            $table->boolean('new_for_you_browser')->default(true)->after('new_for_you_email');
            $table->boolean('account_activity_email')->default(true)->after('new_for_you_browser');
            $table->boolean('account_activity_browser')->default(true)->after('account_activity_email');
            $table->boolean('new_browser_login_email')->default(false)->after('account_activity_browser');
            $table->boolean('new_browser_login_browser')->default(false)->after('new_browser_login_email');
            $table->boolean('new_device_linked_email')->default(false)->after('new_browser_login_browser');
            $table->boolean('new_device_linked_browser')->default(false)->after('new_device_linked_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_notification_settings', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};