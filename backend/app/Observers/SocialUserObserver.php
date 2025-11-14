<?php

namespace App\Observers;

use App\Models\SocialUser;
use Illuminate\Support\Facades\Auth;

class SocialUserObserver
{
    public function creating(SocialUser $socialUser)
    {
        $socialUser->audits->push([
            'event' => 'created',
            'user_type' => get_class(Auth::user()),
            'user_id' => Auth::id(),
            'old_values' => null,
            'new_values' => $socialUser->toArray(),
        ]);
    }

    public function updating(SocialUser $socialUser)
    {
        $old = $socialUser->getOriginal();
        $new = $socialUser->toArray();
        $socialUser->audits->push([
            'event' => 'updated',
            'user_type' => get_class(Auth::user()),
            'user_id' => Auth::id(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    public function deleting(SocialUser $socialUser)
    {
        $socialUser->audits->push([
            'event' => 'deleted',
            'user_type' => get_class(Auth::user()),
            'user_id' => Auth::id(),
            'old_values' => $socialUser->toArray(),
            'new_values' => null,
        ]);
    }

    public function restored(SocialUser $socialUser)
    {
        $socialUser->audits->push([
            'event' => 'restored',
            'user_type' => get_class(Auth::user()),
            'user_id' => Auth::id(),
            'old_values' => null,
            'new_values' => $socialUser->toArray(),
        ]);
    }
}
