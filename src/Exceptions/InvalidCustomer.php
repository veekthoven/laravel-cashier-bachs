<?php

namespace Veekthoven\CashierBachs\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

/** @phpstan-consistent-constructor */
class InvalidCustomer extends Exception
{
    /**
     * Create a new exception instance.
     */
    public static function notYetCreated(Model $owner): static
    {
        return new static(class_basename($owner).' is not a Bachs customer yet. See the createAsBachsCustomer method.');
    }
}
