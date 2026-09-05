<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ventanilla_email_radicados');
    }

    public function down(): void
    {
        // No revertir — la tabla se eliminó permanentemente.
    }
};
