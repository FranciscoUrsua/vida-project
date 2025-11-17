<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt; // Para decrypt en PHP
use Illuminate\Database\Eloquent\Builder; // Para scope

class Audit extends Model
{
    protected $table = 'audits';
    protected $guarded = []; // Permite mass-assignment para audits

    // Scope: Filtra y prepara para desencriptación (no desencripta aquí, solo flag)
    public function scopeWithDecryptedValues(Builder $query, bool $authorized = false): Builder
    {
        // Si no autorizado, devuelve sin cambios (valores encriptados)
        if (!$authorized) {
            return $query->select('*'); // O select sin decrypted fields
        }

        // Si autorizado, selecciona todo (desencriptaremos en accessors post-load)
        return $query->select('*');
    }

    // Accessor para old_values desencriptado (solo si autorizado, pero chequea en runtime)
    public function getOldValuesDecryptedAttribute(): ?array
    {
        if (!$this->old_values) {
            return null;
        }

        // Chequea autorización en runtime (e.g., via Auth o policy; ajusta a tu lógica)
        $authorized = auth()->user()?->can('viewAudits') ?? false; // Asume policy 'viewAudits'
        if (!$authorized) {
            return ['error' => 'Acceso denegado: autorización requerida']; // O null/throw
        }

        try {
            $decrypted = Crypt::decrypt($this->old_values);
            return json_decode($decrypted, true) ?: [];
        } catch (\Exception $e) {
            // Loggea error (clave inválida o corrupta)
            \Log::error('Error desencriptando old_values en audit ID ' . $this->id . ': ' . $e->getMessage());
            return ['error' => 'Datos corruptos o clave inválida'];
        }
    }

    // Accessor para new_values desencriptado (similar)
    public function getNewValuesDecryptedAttribute(): ?array
    {
        if (!$this->new_values) {
            return null;
        }

        $authorized = auth()->user()?->can('viewAudits') ?? false;
        if (!$authorized) {
            return ['error' => 'Acceso denegado: autorización requerida'];
        }

        try {
            $decrypted = Crypt::decrypt($this->new_values);
            return json_decode($decrypted, true) ?: [];
        } catch (\Exception $e) {
            \Log::error('Error desencriptando new_values en audit ID ' . $this->id . ': ' . $e->getMessage());
            return ['error' => 'Datos corruptos o clave inválida'];
        }
    }

    // Relación inversa para auditable (e.g., SocialUser)
    public function auditable()
    {
        return $this->morphTo();
    }
}
