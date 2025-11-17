<?php
namespace App\Common\Observers;

use App\Common\Traits\Auditable;

class BaseAuditableObserver
{
    public function __construct()
    {
        // No lógica base; hereda en observers específicos si hace falta
    }
}
