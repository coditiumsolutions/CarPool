<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Base for API form requests: a failed authorize() returns a clear 403 message
 * instead of Laravel's generic "This action is unauthorized."
 */
abstract class ApiFormRequest extends FormRequest
{
    /** Override per request with a message the user can act on. */
    protected function authorizationMessage(): string
    {
        return 'You are not allowed to do this.';
    }

    protected function failedAuthorization(): void
    {
        throw new HttpException(403, $this->authorizationMessage());
    }
}
