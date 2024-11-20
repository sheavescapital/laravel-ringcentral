<?php

namespace Coxlr\RingCentral\Exceptions;

use Exception;

final class CouldNotSendMessage extends Exception {
    public static function toNumberNotProvided(): static {
        return new self('To number not provided');
    }

    public static function textNotProvided(): static {
        return new static('Message text not provided');
    }
}
