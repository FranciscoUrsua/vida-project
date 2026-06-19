<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_fichas_diagnostico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                  ->constrained('planes_intervencion')
                  ->cascadeOnDelete();
            $table->foreignId('ficha_id')
                  ->constrained('fichas')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'ficha_id']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_fichas_diagnostico');
    }
};
