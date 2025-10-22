<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('users', 'app_users');
    }

    public function down(): void
    {
        Schema::rename('app_users', 'users');
    }
};
