<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

class PasswordChangeRequiredException extends ValidationException
{
    public const CODE = 'PASSWORD_CHANGE_REQUIRED';

    public static function make(): self
    {
        return self::withMessages([
            'password' => ['Change your password before signing in.'],
        ]);
    }
}
