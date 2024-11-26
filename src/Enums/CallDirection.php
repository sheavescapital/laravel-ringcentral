<?php

namespace SheavesCapital\RingCentral\Enums;

enum CallDirection: string {
    case INBOUND = 'Inbound';
    case OUTBOUND = 'Outbound';

    public function label(): string {
        return match ($this) {
            default => $this->value,
        };
    }
}
