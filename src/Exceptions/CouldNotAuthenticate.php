<?php

namespace Coxlr\RingCentral\Exceptions;

use Exception;

final class CouldNotAuthenticate extends Exception {
    public static function adminLoginFailed(): static {
        return new self('Failed to log in admin extension');
    }
}
