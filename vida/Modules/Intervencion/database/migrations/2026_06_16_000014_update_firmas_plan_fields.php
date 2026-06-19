<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            if (Schema::hasColumn('firmas_plan', 'firma_ciudadano')) {
                $table->dropColumn('firma_ciudadano');
            }
            if (Schema::hasColumn('firmas_plan', 'firma_profesional')) {
                $table->dropColumn('firma_profesional');
            }

            if (! Schema::hasColumn('firmas_plan', 'profesional_firmado')) {
                $table->boolean('profesional_firmado')->default(false)->after('version');
            }
            if (! Schema::hasColumn('firmas_plan', 'profesional_firmado_en')) {
                $table->timestamp('profesional_firmado_en')->nullable()->after('profesional_firmado');
            }
            if (! Schema::hasColumn('firmas_plan', 'ciudadano_firmado')) {
                $table->boolean('ciudadano_firmado')->default(false)->after('profesional_firmado_en');
            }
            if (! Schema::hasColumn('firmas_plan', 'ciudadano_firmado_en')) {
                $table->timestamp('ciudadano_firmado_en')->nullable()->after('ciudadano_firmado');
            }
            if (! Schema::hasColumn('firmas_plan', 'observaciones_seguimiento')) {
                $table->text('observaciones_seguimiento')->nullable()->after('ciudadano_firmado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->dropColumn([
                'profesional_firmado',
                'profesional_firmado_en',
                'ciudadano_firmado',
                'ciudadano_firmado_en',
                'observaciones_seguimiento',
            ]);
            $table->text('firma_ciudadano')->nullable();
            $table->text('firma_profesional')->nullable();
        });
    }
};
