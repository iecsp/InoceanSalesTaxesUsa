<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
// use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax\Struct\UsaCalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use InoceanSalesTaxesUsa\Config\Constants;

class UsaTaxProvider extends AbstractTaxProvider
{

    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $lineItemTaxes = [];
        $finalCartTaxes = [];

        $showTaxBreakdown = $this->systemConfigService->get('InoceanSalesTaxesUsa.config.TaxBreakdown');
        $freightTaxable = $this->systemConfigService->get('InoceanSalesTaxesUsa.config.FreightTaxable');

        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $state = $address->getCountryState()->getShortCode() ?? Constants::DEFAULT_STATE;
        $zipCode = $address->getZipcode() ?? "00000";

        foreach ($cart->getLineItems() as $lineItem) {
            $originalTaxRate = $lineItem->getPrice()->getCalculatedTaxes()->first()?->getTaxRate() ?? $this->getTaxRateByName('TAX-FREE');

            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[1]['id']) {
                $taxRates = [$this->getTaxRateByName('US TAX-FREE')];
            } else if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[0]['id']) {
                $taxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
            } else {
                $taxRates = [$originalTaxRate];
            }

            $price = $lineItem->getPrice()->getTotalPrice();
            $calculatedTaxes = [];

            foreach ($taxRates as $taxRate) {
                $tax = $price * $taxRate / 100;
                $calculatedTax = new CalculatedTax($tax, $taxRate, $price);
                $calculatedTaxes[] = $calculatedTax;
                $finalCartTaxes[] = $calculatedTax;
            }

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new UsaCalculatedTaxCollection($calculatedTaxes);
        }

        $deliveryTaxes = [];
        $shippingTotalPrice = $cart->getShippingCosts()->getTotalPrice() ?? 0;

        if ($freightTaxable) {
            foreach ($cart->getDeliveries() as $delivery) {
                $calculatedDeliveryTaxes = [];
                foreach ($delivery->getPositions() as $position) {
                    $shippingMethod = $delivery->getShippingMethod();
                    $taxId = $shippingMethod->getTaxId();
                    $originalDeliveryTaxRate = $shippingMethod->getTax()?->getTaxRate() ?? $this->getTaxRateByName('US TAX-FREE');

                    if ($taxId === Constants::TAXES[1]['id']) {
                        $deliveryTaxRates = [$this->getTaxRateByName('US TAX-FREE')];
                    } elseif ($taxId === Constants::TAXES[0]['id']) {
                        $deliveryTaxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
                    } else {
                        $deliveryTaxRates = [$originalDeliveryTaxRate];
                    }

                    foreach ($deliveryTaxRates as $deliveryTaxRate) {
                        $deliveryTaxedPrice = $shippingTotalPrice * $deliveryTaxRate / 100;
                        $finalDeliveryTaxRate = $deliveryTaxRate;

                        $calculatedDeliveryTax = new CalculatedTax($deliveryTaxedPrice, $finalDeliveryTaxRate, $shippingTotalPrice);
                        $calculatedDeliveryTaxes[] = $calculatedDeliveryTax;
                        $finalCartTaxes[] = $calculatedDeliveryTax;
                    }
                    $deliveryTaxes[$position->getIdentifier()] = new UsaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }
            }
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            new UsaCalculatedTaxCollection($finalCartTaxes)
        );
    }

    private function getTaxRatesByZipCode(string $zipCode, string $state, bool $showTaxBreakdown): array
    {
        $jsonPath = __DIR__ . '/../../../../Config/TaxRates-US.json';

        if (!file_exists($jsonPath)) {
            return [];
        }

        $jsonContent = file_get_contents($jsonPath);
        if ($jsonContent === false) {
            return [];
        }

        $taxData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $stateCode = substr(strtoupper($state), -2);

        if (isset($taxData['states'][$stateCode][$zipCode])) {
            $zipData = $taxData['states'][$stateCode][$zipCode];

            if ($showTaxBreakdown) {
                $rateValues =  array_slice(array_values($zipData), 2, 4);
            } else {
                $rateValues = [$zipData['cbr']];
            }

            $rates = array_map('floatval', $rateValues);

            return array_map(static fn ($rate) => $rate * 100, $rates);
        }

        return [];
    }

    private function getTaxRateByName(string $name): int {
        foreach (Constants::TAXES as $tax) {
            if ($tax['name'] === $name) {
                return $tax['tax_rate'];
            }
        }
        return 0;
    }

}
