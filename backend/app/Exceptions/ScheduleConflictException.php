<?php

namespace App\Exceptions;

use DomainException;

class ScheduleConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('O profissional já tem uma consulta agendada ou confirmada nesse intervalo.');
    }
}
