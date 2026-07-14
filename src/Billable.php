<?php

namespace Veekthoven\CashierBachs;

use Veekthoven\CashierBachs\Concerns\ManagesCustomer;
use Veekthoven\CashierBachs\Concerns\ManagesSubscriptions;
use Veekthoven\CashierBachs\Concerns\PerformsCharges;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
    use PerformsCharges;
}
