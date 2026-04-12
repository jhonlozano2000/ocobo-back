<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_sessions', function (Blueprint $table) {
            $table->string('device_type', 20)->nullable()->after('user_agent');
            $table->string('browser', 50)->nullable()->after('device_type');
            $table->string('operating_system', 50)->nullable()->after('browser');
            $table->string('country', 100)->nullable()->after('operating_system');
            $table->string('city', 100)->nullable()->after('country');
            $table->string('referer_url', 500)->nullable()->after('city');
            $table->string('ip_address_extra', 45)->nullable()->after('ip_address');
            $table->timestamp('logout_at')->nullable()->after('last_login_at');
            $table->boolean('is_active')->default(true)->after('logout_at');
            $table->text('metadata')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'device_type',
                'browser',
                'operating_system',
                'country',
                'city',
                'referer_url',
                'ip_address_extra',
                'logout_at',
                'is_active',
                'metadata'
            ]);
        });
    }
};