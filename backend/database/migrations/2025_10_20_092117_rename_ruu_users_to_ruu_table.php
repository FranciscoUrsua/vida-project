<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ruu_users', 'ruu');
    }

    public function down(): void
    {
        Schema::rename('ruu', 'ruu_users');
    }
};
