<?php

namespace Veekthoven\CashierBachs\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/** @phpstan-consistent-constructor */
class BachsApiError extends Exception
{
    /**
     * The stable, machine-readable Bachs error code.
     */
    public ?string $errorCode = null;

    /**
     * Field-level validation errors, when present.
     */
    public array $errors = [];

    /**
     * The request ID returned by Bachs, useful when contacting support.
     */
    public ?string $requestId = null;

    /**
     * Create a new exception from a failed Bachs API response.
     */
    public static function fromResponse(Response $response): static
    {
        $exception = new static(
            $response->json('detail') ?? 'The Bachs API request failed.',
            $response->status()
        );

        $exception->errorCode = $response->json('error_code');
        $exception->errors = $response->json('errors') ?? [];
        $exception->requestId = $response->header('x-request-id') ?: null;

        return $exception;
    }
}
