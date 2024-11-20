<?php

namespace Coxlr\RingCentral\Exceptions;

use Exception;

final class CouldNotAuthenticate extends Exception {
    public static function operatorLoginFailed(): static {
        return new self('Failed to log in operator extension');
    }

    public static function adminLoginFailed(): static {
        return new static('Failed to log in admin extension');
    }
}
