<?php

namespace App\Enums;

enum CallStatus: int
{
    case NEW = 1;
    case PROCESSING = 2;
    case FAILED_TELEPHONY = 3;
    case ASSIGNED = 4;
    case CONNECTING = 5;
    case IN_PROGRESS = 6;
    case SUCCESS = 7;
    case MISSED = 8;
    case REJECTED = 9;

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('New'),
            self::PROCESSING => __('Processing'),
            self::FAILED_TELEPHONY => __('Failed Telephony'),
            self::ASSIGNED => __('Assigned'),
            self::CONNECTING => __('Connecting'),
            self::IN_PROGRESS => __('In Progress'),
            self::SUCCESS => __('Success'),
            self::MISSED => __('Missed'),
            self::REJECTED => __('Rejected'),
        };
    }
}
