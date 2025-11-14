<?php
namespace App\Observers;

use App\Models\SocialUser;
use App\Models\AppUser; // Tu modelo de AppUser
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class SocialUserObserver
{
    // Trigger en lecturas (first/find/retrieve)
    public function retrieved(SocialUser $socialUser)
    {
        $this->logAudit($socialUser, 'retrieved', [], $socialUser->getAttributes());
    }

    // Trigger en creación
    public function creating(SocialUser $socialUser)
    {
        $socialUser->created_by = Auth::id() ?? null;
        $socialUser->updated_by = $socialUser->created_by;
    }

    public function created(SocialUser $socialUser)
    {
        $this->logAudit($socialUser, 'created', [], $socialUser->getAttributes());
    }

    // Trigger en updates
    public function updating(SocialUser $socialUser)
    {
        $socialUser->updated_by = Auth::id() ?? null;
    }

    public function updated(SocialUser $socialUser)
    {
        $old = $socialUser->getOriginal(); // Valores previos
        $new = $socialUser->getAttributes(); // Valores actuales
        $this->logAudit($socialUser, 'updated', $old, $new);
    }

    // Trigger en deletes (soft)
    public function deleting(SocialUser $socialUser)
    {
        $this->logAudit($socialUser, 'deleted', $socialUser->getAttributes(), []);
    }

    public function restored(SocialUser $socialUser)
    {
        $this->logAudit($socialUser, 'restored', [], $socialUser->getAttributes());
    }

    // Lógica privada para insert en audits
    private function logAudit(SocialUser $socialUser, string $event, array $old = [], array $new = [])
    {
        $appUser = Auth::user(); // Puede ser null en Tinker

        // Motivo opcional: Solo si acceso no-asignado (ajusta 'asignado_a' a tu campo FK en SocialUser)
        $tags = [];
        $currentUserId = Auth::id();
        if ($currentUserId && isset($socialUser->asignado_a) && $socialUser->asignado_a !== $currentUserId) {
            $tags['motivo'] = Request::input('motivo') ?? 'Acceso no autorizado sin justificación';
        }

        DB::table('audits')->insert([
            'user_type' => $appUser ? AppUser::class : null,
            'user_id' => $appUser ? $appUser->id : null,
            'event' => $event,
            'auditable_type' => SocialUser::class,
            'auditable_id' => $socialUser->id,
            'old_values' => !empty($old) ? json_encode($old) : null,
            'new_values' => !empty($new) ? json_encode($new) : null,
            'url' => Request::fullUrl() ?? 'tinker',
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'Symfony (Tinker)',
            'tags' => !empty($tags) ? json_encode($tags) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
