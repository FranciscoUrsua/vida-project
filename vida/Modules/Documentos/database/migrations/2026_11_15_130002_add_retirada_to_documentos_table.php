<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retirada lógica de documentos (fase 4, paso 4; docs/roles-consulta-basica-tramitacion.md §3.3).
 *
 * Un documento retirado pasa al estado `retirado` y guarda quién lo retiró, cuándo y por
 * qué. No se borra: sigue en el historial y en la auditoría, pero no se lista ni se sirve.
 */
return new class extends Migration
{
    /** Estados admitidos tras la migración (EstadoDocumento). */
    private const ESTADOS = ['pendiente_validacion', 'vigente', 'rechazado', 'destruido', 'retirado'];

    /** Estados admitidos antes de la migración. */
    private const ESTADOS_ANTES = ['pendiente_validacion', 'vigente', 'rechazado', 'destruido'];

    /**
     * Añade el estado `retirado` y las columnas de la retirada.
     *
     * @return void
     */
    public function up(): void
    {
        $this->restriccionEstado(self::ESTADOS);

        Schema::table('documentos', function (Blueprint $table): void {
            $table->timestamp('retirado_en')->nullable()->after('estado');
            $table->foreignId('retirado_por_id')->nullable()->after('retirado_en')->constrained('users');
            $table->text('motivo_retirada')->nullable()->after('retirado_por_id');
        });
    }

    /**
     * Quita las columnas y el estado (aborta si hay documentos retirados).
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::table('documentos')->where('estado', 'retirado')->exists()) {
            throw new RuntimeException('Hay documentos retirados: no se puede deshacer la migración sin perder la retirada.');
        }

        Schema::table('documentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retirado_por_id');
            $table->dropColumn(['retirado_en', 'motivo_retirada']);
        });

        $this->restriccionEstado(self::ESTADOS_ANTES);
    }

    /**
     * Rehace la restricción CHECK que Laravel crea para la columna enum en PostgreSQL.
     *
     * @param list<string> $estados Valores admitidos.
     *
     * @return void
     */
    private function restriccionEstado(array $estados): void
    {
        $valores = implode(', ', array_map(fn (string $e): string => "'{$e}'::character varying", $estados));

        DB::statement('ALTER TABLE documentos DROP CONSTRAINT IF EXISTS documentos_estado_check');
        DB::statement("ALTER TABLE documentos ADD CONSTRAINT documentos_estado_check CHECK (estado::text = ANY (ARRAY[{$valores}]::text[]))");
    }
};
