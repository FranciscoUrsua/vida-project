<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a la tabla ciudadanos los campos necesarios para la capa de anonimización.
 *
 * Incluye: identificación documental (cifrada), flags de colectivos especiales
 * y campos de geolocalización de PSH. Estos campos están documentados en
 * docs/anonimizacion.md §§ 3.3, 6.5 y docs/geocodificacion.md § 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            // Documento de identidad — cifrado en aplicación
            $table->text('documento_identidad')->nullable()->after('email')
                ->comment('DNI / NIE / Pasaporte — cifrado');

            // Colectivos especiales con tratamiento diferenciado en anonimización
            $table->boolean('es_vvg')->default(false)->after('documento_identidad')
                ->comment('Víctima de violencia de género — supresión total de dirección');
            $table->boolean('es_psh')->default(false)->after('es_vvg')
                ->comment('Persona sin hogar — sin dirección postal, usa zona_intervencion');
            $table->boolean('colectivo_extra_protegido')->default(false)->after('es_psh')
                ->comment('Colectivo con supresión anticipada de colectivo_principal en Nivel 3');

            // Colectivo de atención principal (cuasi-identificador en k-anonimato)
            $table->string('colectivo_principal', 100)->nullable()->after('colectivo_extra_protegido');

            // Zona de intervención — cuasi-identificador sustituto para PSH
            $table->string('zona_intervencion', 100)->nullable()->after('colectivo_principal');

            // Coordenadas de pernocta habitual (PSH) — independientes del modelo de dirección postal
            $table->decimal('pernocta_lat', 10, 7)->nullable()->after('zona_intervencion');
            $table->decimal('pernocta_lng', 10, 7)->nullable()->after('pernocta_lat');
        });
    }

    public function down(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->dropColumn([
                'documento_identidad',
                'es_vvg',
                'es_psh',
                'colectivo_extra_protegido',
                'colectivo_principal',
                'zona_intervencion',
                'pernocta_lat',
                'pernocta_lng',
            ]);
        });
    }
};
