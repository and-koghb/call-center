<?php

namespace App\Enums;

enum ClientStatus: int
{
    case ACTIVE = 1;
    case INACTIVE = 2;
    case BLOCKED = 3;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
            self::BLOCKED => __('Blocked'),
        };
    }
}
