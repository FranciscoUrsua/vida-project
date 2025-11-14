<?php

namespace App\Observers;

use App\Models\SocialUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SocialUserObserver
{
    public function retrieved(SocialUser $socialUser)
    {
        $this->auditAccess($socialUser, 'retrieved');
    }

    public function creating(SocialUser $socialUser)
    {
        $this->auditAccess($socialUser, 'created');
        $socialUser->created_by = Auth::id() ?? null;
        $socialUser->updated_by = $socialUser->created_by;
    }

    public function updating(SocialUser $socialUser)
    {
        $this->auditAccess($socialUser, 'updated');
        $socialUser->updated_by = Auth::id() ?? null;
    }

    public function deleting(SocialUser $socialUser)
    {
        $this->auditAccess($socialUser, 'deleted');
    }

    public function restoring(SocialUser $socialUser)
    {
        $this->auditAccess($socialUser, 'restored');
    }

    // Lógica privada para audit + motivo
    private function auditAccess(SocialUser $socialUser, string $event)
    {
        // Trigger manual de audit si no es automático (para retrieved)
        if ($event === 'retrieved') {
            $socialUser->audit('retrieved', $socialUser->getOriginal());
        }

        // Chequea si acceso no-asignado (ajusta lógica a tu campo 'asignado_a' o similar en SocialUser)

    }
}
