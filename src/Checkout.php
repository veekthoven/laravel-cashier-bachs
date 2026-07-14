<?php

namespace Veekthoven\CashierBachs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use JsonSerializable;

class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * Create a new checkout instance from a Bachs checkout session payload.
     */
    public function __construct(protected array $session) {}

    /**
     * Get the checkout identifier.
     */
    public function id(): ?string
    {
        return $this->session['checkout_id'] ?? null;
    }

    /**
     * Get the hosted checkout URL the customer should be redirected to.
     */
    public function url(): ?string
    {
        return $this->session['checkout_url'] ?? null;
    }

    /**
     * Get the raw checkout session payload.
     */
    public function asBachsCheckoutSession(): array
    {
        return $this->session;
    }

    /**
     * Redirect the customer to the hosted checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return redirect($this->url());
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        return $this->redirect();
    }

    /**
     * Dynamically access properties of the underlying checkout session.
     */
    public function __get(string $key): mixed
    {
        return $this->session[$key] ?? null;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->session;
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
