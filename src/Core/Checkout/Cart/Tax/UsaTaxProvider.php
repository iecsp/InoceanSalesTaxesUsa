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
use Shopware\Core\Framework\Struct\ArrayEntity;
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
        $deliveryTaxes = [];
        $aggregatedCartTaxes = [];
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
            $originalTaxRate = $lineItem->getPrice()->getCalculatedTaxes()->first()?->getTaxRate() ?? $this->getTaxRateByType('TAX-FREE');

            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[1]['id']) {
                $taxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
            } else if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[0]['id']) {
                $taxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
            } else {
                $taxRates = ['TAX' => $originalTaxRate];
            }

            $price = $lineItem->getPrice()->getTotalPrice();
            $calculatedTaxes = [];
            $lineItemTaxInfo = [];

            foreach ($taxRates as $taxName => $taxRate) {
                $tax = $price * $taxRate / 100;
                $calculatedTax = new CalculatedTax($tax, $taxRate, $price);
                $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
                $calculatedTaxes[] = $calculatedTax;

                if (!isset($aggregatedCartTaxes[$taxName])) {
                    $aggregatedCartTaxes[$taxName] = ['rate' => $taxRate, 'tax' => 0, 'price' => 0];
                }
                $aggregatedCartTaxes[$taxName]['tax'] += $tax;
                $aggregatedCartTaxes[$taxName]['price'] += $price;
                $lineItemTaxInfo[] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => $tax];
            }
            $payload = $lineItem->getPayload();
            $payload['inoceanUsaTaxInfo'] = $lineItemTaxInfo;
            $lineItem->setPayload($payload);

            $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new UsaCalculatedTaxCollection($calculatedTaxes);
        }

        if ($freightTaxable) {

            $aggregatedShippingTaxesPayload = [];
            $delivery = $cart->getDeliveries()->first();

            foreach ($delivery->getPositions() as $position) {
                $shippingTotalPrice = $delivery->getShippingCosts()->getTotalPrice();
                if ($shippingTotalPrice <= 0) {
                    continue;
                }

                $taxId = $delivery->getShippingMethod()->getTaxId();

                if ($taxId === Constants::TAXES[1]['id']) {
                    $deliveryTaxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
                } elseif ($taxId === Constants::TAXES[0]['id']) {
                    $deliveryTaxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
                } else {
                    $deliveryTaxRates = ['TAX' => $originalDeliveryTaxRate];
                }

                $calculatedDeliveryTaxes = [];
                foreach ($deliveryTaxRates as $deliveryTaxName => $deliveryTaxRate) {
                    $deliveryTaxedPrice = $shippingTotalPrice * $deliveryTaxRate / 100;
                    $calculatedDeliveryTax = new CalculatedTax($deliveryTaxedPrice, $deliveryTaxRate, $shippingTotalPrice);
                    $calculatedDeliveryTax->addExtension('taxName', new ArrayEntity(['name' => $deliveryTaxName]));
                    $calculatedDeliveryTaxes[] = $calculatedDeliveryTax;

                    if (!isset($aggregatedCartTaxes[$deliveryTaxName])) {
                        $aggregatedCartTaxes[$deliveryTaxName] = ['rate' => $deliveryTaxRate, 'tax' => 0, 'price' => 0];
                    }
                    $aggregatedCartTaxes[$deliveryTaxName]['tax'] += $deliveryTaxedPrice;
                    $aggregatedCartTaxes[$deliveryTaxName]['price'] += $shippingTotalPrice;

                    if (!isset($aggregatedShippingTaxesPayload[$deliveryTaxName])) {
                        $aggregatedShippingTaxesPayload[$deliveryTaxName] = ['name' => $deliveryTaxName, 'rate' => $deliveryTaxRate, 'tax' => 0];
                    }
                    $aggregatedShippingTaxesPayload[$deliveryTaxName]['tax'] = $deliveryTaxedPrice;
                }

                if (!empty($calculatedDeliveryTaxes)) {
                    $deliveryTaxes[$position->getIdentifier()] = new UsaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }
            }

            if (!empty($aggregatedShippingTaxesPayload)) {
                $payload = $cart->getLineItems()->first()->getPayload();
                $payload['inoceanShippingTaxInfo'] = array_values($aggregatedShippingTaxesPayload);
                $cart->getLineItems()->first()->setPayload($payload);
            }
        }

        $finalCartTaxes = new UsaCalculatedTaxCollection();
        foreach ($aggregatedCartTaxes as $taxName => $data) {
            $calculatedTax = new CalculatedTax($data['tax'], $data['rate'], $data['price']);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $taxName]));
            $finalCartTaxes->add($calculatedTax);
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            // new UsaCalculatedTaxCollection($finalCartTaxes)
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

        if (!isset($taxData['states'][$stateCode][$zipCode])) {
            return [];
        }

        $zipData = $taxData['states'][$stateCode][$zipCode];

        $rateMap = [
            'cbr' => 'CombinedRate',
            'str' => 'StateRate',
            'ctr' => 'CountyRate',
            'cir' => 'CityRate',
            'spr' => 'SpecialRate',
        ];

        if ($showTaxBreakdown) {
            $result = [];
            foreach (['str', 'ctr', 'cir', 'spr'] as $key) {
                if (isset($zipData[$key])) {
                    $result[$rateMap[$key]] = (float)$zipData[$key] * 100;
                }
            }
            return $result;
        } else {
            return [
                $rateMap['cbr'] => isset($zipData['cbr']) ? (float)$zipData['cbr'] * 100 : 0.0
            ];
        }
    }

    private function getDefaultRateByTaxType(string $type): int {
        foreach (Constants::TAXES as $tax) {
            if ($tax['tax_type'] === $type) {
                return $tax['tax_rate'];
            }
        }
        return 0;
    }

}
