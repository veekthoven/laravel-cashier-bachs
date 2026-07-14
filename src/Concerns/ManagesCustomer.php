<?php

namespace Veekthoven\CashierBachs\Concerns;

use Veekthoven\CashierBachs\Cashier;
use Veekthoven\CashierBachs\Exceptions\CustomerAlreadyCreated;
use Veekthoven\CashierBachs\Exceptions\InvalidCustomer;

trait ManagesCustomer
{
    /**
     * Retrieve the Bachs customer ID.
     */
    public function bachsId(): ?string
    {
        return $this->bachs_id;
    }

    /**
     * Determine if the billable model has a Bachs customer ID.
     */
    public function hasBachsId(): bool
    {
        return ! is_null($this->bachs_id);
    }

    /**
     * Ensure the billable model has a Bachs customer ID.
     *
     * @throws InvalidCustomer
     */
    protected function assertCustomerExists(): void
    {
        if (! $this->hasBachsId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a Bachs customer for the billable model.
     *
     * @throws CustomerAlreadyCreated
     */
    public function createAsBachsCustomer(array $options = []): array
    {
        if ($this->hasBachsId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        if (! array_key_exists('email', $options) && $email = $this->bachsEmail()) {
            $options['email'] = $email;
        }

        if (! array_key_exists('name', $options) && $name = $this->bachsName()) {
            $options['name'] = $name;
        }

        if (! array_key_exists('phone_number', $options) && $phone = $this->bachsPhone()) {
            $options['phone_number'] = $phone;
        }

        $customer = Cashier::api()->createCustomer($options);

        $this->bachs_id = $customer['customer_id'];

        $this->save();

        return $customer;
    }

    /**
     * Get the Bachs customer for the billable model, creating one if necessary.
     */
    public function createOrGetBachsCustomer(array $options = []): array
    {
        if ($this->hasBachsId()) {
            return $this->asBachsCustomer();
        }

        return $this->createAsBachsCustomer($options);
    }

    /**
     * Get the Bachs customer ID, creating the customer if necessary.
     */
    public function bachsIdOrCreate(array $options = []): string
    {
        if ($this->hasBachsId()) {
            return $this->bachs_id;
        }

        return $this->createAsBachsCustomer($options)['customer_id'];
    }

    /**
     * Get the Bachs customer for the billable model as a raw API payload.
     *
     * @throws InvalidCustomer
     */
    public function asBachsCustomer(): array
    {
        $this->assertCustomerExists();

        return Cashier::api()->getCustomer($this->bachs_id);
    }

    /**
     * Update the underlying Bachs customer for the billable model.
     *
     * @throws InvalidCustomer
     */
    public function updateBachsCustomer(array $options = []): array
    {
        $this->assertCustomerExists();

        return Cashier::api()->updateCustomer($this->bachs_id, $options);
    }

    /**
     * Sync the billable model's attributes to the Bachs customer record.
     */
    public function syncBachsCustomerDetails(): array
    {
        return $this->updateBachsCustomer(array_filter([
            'email' => $this->bachsEmail(),
            'name' => $this->bachsName(),
            'phone_number' => $this->bachsPhone(),
        ], fn ($value) => ! is_null($value)));
    }

    /**
     * Get the email address used to create the customer in Bachs.
     */
    public function bachsEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the name used to create the customer in Bachs.
     */
    public function bachsName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the phone number used to create the customer in Bachs.
     */
    public function bachsPhone(): ?string
    {
        return $this->phone ?? null;
    }
}
