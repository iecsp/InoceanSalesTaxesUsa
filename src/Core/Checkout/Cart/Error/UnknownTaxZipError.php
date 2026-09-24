<?php declare(strict_types=1);

namespace InoceanSalesTaxesUsa\Core\Checkout\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

/**
 * The rate table has no rate for the address's ZIP, so the tax on this cart is
 * a guess. The cart still renders (priced at the fallback rate) but cannot be
 * ordered: charging a guessed rate either over-collects from the customer or
 * under-collects at the merchant's expense.
 */
class UnknownTaxZipError extends Error
{
    public const KEY = 'inocean-usa-unknown-tax-zip';

    public function __construct(
        private readonly string $zipCode,
        private readonly string $state,
    ) {
        $this->message = \sprintf(
            'Sales tax cannot be determined for ZIP code "%s" (%s). Check the address; the order cannot be placed until the ZIP code is recognised.',
            $zipCode,
            $state
        );

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return self::KEY;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return ['zipcode' => $this->zipCode, 'state' => $this->state];
    }
}
