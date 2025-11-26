<?php
namespace App\Common\Services;

use App\Models\AppUser; // Ajusta a tu modelo de AppUser
use App\Models\Common\Audit; // Si creaste el modelo Audit; sino, usa DB::table('audits')
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function logAudit($auditable, string $event, array $old = [], array $new = []): void
    {
        $appUser = Auth::user();

        // Encripta valores (JSON -> encrypt)
        $oldEncrypted = !empty($old) ? Crypt::encrypt(json_encode($old)) : null;
        $newEncrypted = !empty($new) ? Crypt::encrypt(json_encode($new)) : null;

        // Tags con motivo (reutilizable para cualquier modelo)
        $tags = $this->buildTags($auditable);

        DB::table('audits')->insert([
            'user_type' => $appUser ? AppUser::class : null,
            'user_id' => $appUser ? $appUser->id : null,
            'event' => $event,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'old_values' => $oldEncrypted,
            'new_values' => $newEncrypted,
            'url' => Request::fullUrl() ?? 'tinker',
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'Symfony (Tinker)',
            'tags' => !empty($tags) ? json_encode($tags) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildTags($auditable): array
    {
        $tags = [];
        $currentUserId = Auth::id();

        // Motivo para accesos no-asignados (genérico; ajusta campo si varía por modelo)
        if ($currentUserId && property_exists($auditable, 'asignado_a') && $auditable->asignado_a !== $currentUserId) {
            $tags['motivo'] = Request::input('motivo') ?? 'Acceso no autorizado sin justificación';
        }

        // Extensión para VDG/menores (genérico; asume campos booleanos en modelo)
        if (property_exists($auditable, 'es_vdg') && $auditable->es_vdg) {
            $tags['acceso_protegido'] = true;
            if (empty($tags['motivo'])) {
                $tags['motivo'] = 'Solicitud de autorización requerida';
            }
        }
        if (property_exists($auditable, 'es_menor') && $auditable->es_menor) {
            $tags['acceso_protegido'] = true;
            if (empty($tags['motivo'])) {
                $tags['motivo'] = 'Acceso a menor: verificación requerida';
            }
        }

        return $tags;
    }
}
