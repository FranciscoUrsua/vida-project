<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'audits'; // Asumiendo config('audit.drivers.database.table', 'audits')
        $morphPrefix = 'user'; // Asumiendo config
        Schema::create($table, function (Blueprint $table) use ($morphPrefix) {
            $table->bigIncrements('id');
            $table->string($morphPrefix . '_type')->nullable();
            $table->unsignedBigInteger($morphPrefix . '_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
            $table->index([$morphPrefix . '_id', $morphPrefix . '_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
