<?php

namespace App\Enums;

enum SurveillanceSessionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Aborted = 'aborted';

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Aborted;
    }
}
