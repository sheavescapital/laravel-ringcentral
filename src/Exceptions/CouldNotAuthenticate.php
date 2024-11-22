<?php

namespace SheavesCapital\RingCentral\Exceptions;

use Exception;

final class CouldNotAuthenticate extends Exception {
    public static function loginFailed(): static {
        return new self('Failed to log in');
    }
}
