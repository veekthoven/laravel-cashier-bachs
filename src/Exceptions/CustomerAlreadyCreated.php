<?php

namespace Veekthoven\CashierBachs\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

/** @phpstan-consistent-constructor */
class CustomerAlreadyCreated extends Exception
{
    /**
     * Create a new exception instance.
     */
    public static function exists(Model $owner): static
    {
        return new static(class_basename($owner)." is already a Bachs customer with ID {$owner->getAttribute('bachs_id')}.");
    }
}
