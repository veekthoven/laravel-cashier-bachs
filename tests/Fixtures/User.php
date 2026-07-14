<?php

namespace Veekthoven\CashierBachs\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Veekthoven\CashierBachs\Billable;

class User extends Authenticatable
{
    use Billable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }
}
