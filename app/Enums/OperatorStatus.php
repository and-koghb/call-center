<?php

namespace App\Enums;

enum OperatorStatus: int
{
    case AVAILABLE = 1;
    case BUSY = 2;
    case BREAK = 3;
    case OFFLINE = 4;

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => __('Available'),
            self::BUSY => __('Busy'),
            self::BREAK => __('On Break'),
            self::OFFLINE => __('Offline'),
        };
    }
}
