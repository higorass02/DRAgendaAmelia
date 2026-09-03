<?php

namespace App\Observers;

class PatientAuditObserver extends AuditObserver
{
    public function __construct()
    {
        parent::__construct('patient');
    }
}
