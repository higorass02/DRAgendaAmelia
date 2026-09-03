<?php

namespace App\Observers;

class ProfessionalAuditObserver extends AuditObserver
{
    public function __construct()
    {
        parent::__construct('professional');
    }
}
