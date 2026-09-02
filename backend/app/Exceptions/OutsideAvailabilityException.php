<?php

namespace App\Exceptions;

use DomainException;

class OutsideAvailabilityException extends DomainException
{
    public function __construct()
    {
        parent::__construct('O horário solicitado está fora da janela de disponibilidade do profissional.');
    }
}
