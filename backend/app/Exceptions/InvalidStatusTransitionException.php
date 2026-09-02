<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use DomainException;

class InvalidStatusTransitionException extends DomainException
{
    public static function make(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self("Não é possível transicionar de \"{$from->label()}\" para \"{$to->label()}\".");
    }
}
