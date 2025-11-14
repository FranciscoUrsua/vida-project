<?php

use OwenIt\Auditing\AuditingServiceProvider as BaseAuditingServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuditingServiceProvider extends BaseAuditingServiceProvider
{
    protected function resolveUser()
    {
        return Auth::user();
    }
}
