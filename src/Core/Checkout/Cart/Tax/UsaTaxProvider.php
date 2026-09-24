<?php declare(strict_types=1);

namespace InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Framework\Struct\ArrayEntity;
use InoceanSalesTaxesUsa\Core\Checkout\Cart\Error\UnknownTaxZipError;
use InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax\Struct\UsaCalculatedTaxCollection;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use InoceanSalesTaxesUsa\Config\Constants;

class UsaTaxProvider extends AbstractTaxProvider
{

    /**
     * Name given to a band that stands for several same-rate bands merged
     * together. It has a snippet (`salesTaxUsa.checkout.taxes.taxTAX`), unlike
     * a joined name such as "StateRate+CityRate" would.
     */
    private const MERGED_BAND_NAME = 'TAX';

    private SystemConfigService $systemConfigService;

    /** @var array<string, mixed>|null */
    private ?array $zipIndex = null;

    /** Set by getTaxRatesByZipCode() when it had to fall back to a guessed rate. */
    private bool $ratesUnknown = false;

    public function __construct(
        SystemConfigService $systemConfigService,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->systemConfigService = $systemConfigService;
    }

    public function provide(Cart $cart, SalesChannelContext $context): TaxProviderResult
    {
        $lineItemTaxes = [];
        $deliveryTaxes = [];
        $aggregatedCartTaxes = [];
        $finalCartTaxes = [];
        $this->ratesUnknown = false;

        $showTaxBreakdown = $this->systemConfigService->get('InoceanSalesTaxesUsa.config.TaxBreakdown') ?? 1;
        $freightTaxable = $this->systemConfigService->get('InoceanSalesTaxesUsa.config.FreightTaxable') ?? 1;
        $taxDecimals = $this->systemConfigService->get('InoceanSalesTaxesUsa.config.TaxDecimals') ?? 2;
        
        $address = $context->getShippingLocation()->getAddress();
        if (!$address || strtoupper($address->getCountry()?->getIso()) !== Constants::DEFAULT_COUNTRY) {
            return new TaxProviderResult([]);
        }

        $zipCode = substr((string) $address->getZipcode(), 0, 5);

        // An address without a state used to fatal here and take the whole cart
        // down. The ZIP identifies the state on its own.
        $state = $address->getCountryState()?->getShortCode()
            ?? $this->stateForZip($zipCode)
            ?? Constants::DEFAULT_STATE;

        // Promotions are taxed in a second pass, at the rates of the lines they
        // discounted — see applyPromotionTaxes().
        $ratesByLineId = [];
        $priceByLineId = [];
        $promotionLineItems = [];

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE) {
                $promotionLineItems[] = $lineItem;

                continue;
            }

            $originalTaxRate = $lineItem->getPrice()->getCalculatedTaxes()->first()?->getTaxRate() ?? $this->getDefaultRateByTaxType('TAX-FREE');

            if ($lineItem->getPayloadValue('taxId') === Constants::TAXES[1]['id']) {
                $taxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
            } elseif ($lineItem->getPayloadValue('taxId') === Constants::TAXES[0]['id']) {
                $taxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
            } else {
                $taxRates = ['TAX' => $originalTaxRate];
            }

            $price = $lineItem->getPrice()->getTotalPrice();

            // A 0% band is nothing a discount could be reversed against.
            $ratesByLineId[$lineItem->getId()] = array_filter($taxRates, static fn ($rate): bool => (float) $rate > 0.0);
            $priceByLineId[$lineItem->getId()] = $price;

            $bands = [];
            foreach ($taxRates as $taxName => $taxRate) {
                $bands[] = ['name' => $taxName, 'rate' => $taxRate, 'tax' => round($price * $taxRate / 100, $taxDecimals), 'price' => $price];
            }
            $this->applyBands($lineItem, $bands, $aggregatedCartTaxes, $lineItemTaxes);
        }

        foreach ($promotionLineItems as $lineItem) {
            $this->applyPromotionTaxes($lineItem, $ratesByLineId, $priceByLineId, (int) $taxDecimals, $aggregatedCartTaxes, $lineItemTaxes);
        }

        if ($freightTaxable) {
            $delivery = $cart->getDeliveries()->first();
        
            if ($delivery && $delivery->getShippingCosts()->getTotalPrice() > 0) {
                $shippingTotalPrice = $delivery->getShippingCosts()->getTotalPrice();
                $taxId = $delivery->getShippingMethod()->getTaxId();
                $aggregatedShippingTaxesPayload = [];
                $deliveryTaxRates = [];
                $calculatedDeliveryTaxes = [];

                if ($taxId === Constants::TAXES[0]['id']) {
                    $deliveryTaxRates = $this->getTaxRatesByZipCode($zipCode, $state, $showTaxBreakdown);
                } else {
                    $deliveryTaxRates = ['TAX-FREE' => $this->getDefaultRateByTaxType('TAX-FREE')];
                }
        
                $deliveryBands = [];
                foreach ($deliveryTaxRates as $deliveryTaxName => $deliveryTaxRate) {
                    $deliveryTaxAmount = round($shippingTotalPrice * $deliveryTaxRate / 100, $taxDecimals);
                    $deliveryBands[] = ['name' => $deliveryTaxName, 'rate' => $deliveryTaxRate, 'tax' => $deliveryTaxAmount, 'price' => $shippingTotalPrice];
                    $this->aggregate($aggregatedCartTaxes, $deliveryTaxName, $deliveryTaxRate, $deliveryTaxAmount, $shippingTotalPrice, 'delivery');

                    $aggregatedShippingTaxesPayload[$deliveryTaxName] = [
                        'name' => $deliveryTaxName, 
                        'rate' => $deliveryTaxRate, 
                        'tax' => $deliveryTaxAmount
                    ];
                }
        
                $calculatedDeliveryTaxes = $this->mergedTaxes($deliveryBands);

                if (!empty($calculatedDeliveryTaxes) && $delivery->getPositions()->first()) {
                    $deliveryTaxes[$delivery->getPositions()->first()->getIdentifier()] = new UsaCalculatedTaxCollection($calculatedDeliveryTaxes);
                }
        
                if (!empty($aggregatedShippingTaxesPayload) && $cart->getLineItems()->first()) {
                    $payload = $cart->getLineItems()->first()->getPayload();
                    $payload['inoceanShippingTaxInfo'] = array_values($aggregatedShippingTaxesPayload);
                    $cart->getLineItems()->first()->setPayload($payload);
                }
            }
        }

        // A ZIP the table does not know used to be taxed at the flat fallback
        // rate without a word. The cart still prices (so it can be shown and
        // corrected) but the order is blocked: a guessed rate either
        // over-collects from the customer or under-collects at the merchant's
        // expense.
        if ($this->ratesUnknown) {
            $cart->addErrors(new UnknownTaxZipError($zipCode, $state));
            $this->logger?->error('USA tax: no rate for this ZIP code; order placement is blocked until the address is corrected.', [
                'zipcode' => $zipCode,
                'state' => $state,
                'salesChannelId' => $context->getSalesChannelId(),
            ]);
        }

        $finalCartTaxes = new UsaCalculatedTaxCollection();
        
        foreach ($aggregatedCartTaxes as $data) {
            $calculatedTax = new CalculatedTax($data['tax'], $data['rate'], $data['price']);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $data['name']]));
            $finalCartTaxes->add($calculatedTax);
        }

        return new TaxProviderResult(
            $lineItemTaxes,
            $deliveryTaxes,
            new UsaCalculatedTaxCollection($finalCartTaxes)
        );
    }

    /**
     * Records a line's bands: itemised on the payload (for display and
     * documents), merged by rate on the line's taxes and the cart's.
     *
     * @param list<array{name: string, rate: float|int, tax: float, price: float}> $bands
     * @param array<string, array{name: string, rate: float|int, tax: float, price: float, lines: array<string, true>}> $aggregatedCartTaxes
     * @param array<string, UsaCalculatedTaxCollection> $lineItemTaxes
     */
    private function applyBands(LineItem $lineItem, array $bands, array &$aggregatedCartTaxes, array &$lineItemTaxes): void
    {
        $lineItemTaxInfo = [];
        foreach ($bands as $band) {
            $lineItemTaxInfo[] = ['name' => $band['name'], 'rate' => $band['rate'], 'tax' => $band['tax']];
            $this->aggregate($aggregatedCartTaxes, $band['name'], $band['rate'], $band['tax'], $band['price'], $lineItem->getId());
        }

        $payload = $lineItem->getPayload();
        $payload['inoceanUsaTaxInfo'] = $lineItemTaxInfo;
        $lineItem->setPayload($payload);

        $lineItemTaxes[$lineItem->getUniqueIdentifier()] = new UsaCalculatedTaxCollection($this->mergedTaxes($bands));
    }

    /**
     * One CalculatedTax per RATE, never per band.
     *
     * Shopware keys a stored order's taxes by rate when it reads them back
     * (CartPriceFieldSerializer / CalculatedPriceFieldSerializer build a
     * CalculatedTaxCollection, whose add() overwrites on the same rate). Two
     * same-rate bands — e.g. a 4% state and a 4% county tax, common in NY and
     * TX — therefore lost one of them on every loaded order: refunds, reports
     * and reprints all came out short. The amounts summed are the already
     * rounded per-band amounts, so the total charged does not move.
     *
     * @param list<array{name: string, rate: float|int, tax: float, price: float}> $bands
     *
     * @return list<CalculatedTax>
     */
    private function mergedTaxes(array $bands): array
    {
        $byRate = [];
        foreach ($bands as $band) {
            $key = (string) (float) $band['rate'];
            if (!isset($byRate[$key])) {
                $byRate[$key] = $band;

                continue;
            }
            $byRate[$key]['tax'] += $band['tax'];
            $byRate[$key]['name'] = self::MERGED_BAND_NAME;
        }

        $taxes = [];
        foreach ($byRate as $band) {
            $calculatedTax = new CalculatedTax($band['tax'], $band['rate'], $band['price']);
            $calculatedTax->addExtension('taxName', new ArrayEntity(['name' => $band['name']]));
            $taxes[] = $calculatedTax;
        }

        return $taxes;
    }

    /**
     * Cart-level totals, keyed by rate for the same reason as mergedTaxes().
     * A rate's base (`price`) counts each line once, however many same-rate
     * bands that line carries.
     *
     * @param array<string, array{name: string, rate: float|int, tax: float, price: float, lines: array<string, true>}> $aggregatedCartTaxes
     */
    private function aggregate(array &$aggregatedCartTaxes, string $taxName, float|int $taxRate, float $tax, float $price, string $lineKey): void
    {
        $key = (string) (float) $taxRate;
        $aggregatedCartTaxes[$key] ??= ['name' => $taxName, 'rate' => $taxRate, 'tax' => 0.0, 'price' => 0.0, 'lines' => []];
        $entry = &$aggregatedCartTaxes[$key];

        if ($entry['name'] !== $taxName) {
            $entry['name'] = self::MERGED_BAND_NAME;
        }
        $entry['tax'] += $tax;
        if (!isset($entry['lines'][$lineKey])) {
            $entry['lines'][$lineKey] = true;
            $entry['price'] += $price;
        }
    }

    /**
     * A promotion is a reduction of things already sold, so it is taxed at the
     * rates of the lines it reduced — never at the flat rate of the tax entity
     * Shopware priced it with ((US) COMBINED TAX = 10%), which under-collected
     * tax on every discounted order in a ZIP whose real rate is lower.
     *
     * The promotion's own `composition` payload says which lines the discount
     * came off and by how much; failing that, the discount is split by price
     * across the taxed lines. Nothing taxed to attribute it to means no tax at
     * all — reversing tax there would invent a liability.
     *
     * @param array<string, array<string, float|int>> $ratesByLineId
     * @param array<string, float> $priceByLineId
     * @param array<string, array{name: string, rate: float|int, tax: float, price: float, lines: array<string, true>}> $aggregatedCartTaxes
     * @param array<string, UsaCalculatedTaxCollection> $lineItemTaxes
     */
    private function applyPromotionTaxes(
        LineItem $lineItem,
        array $ratesByLineId,
        array $priceByLineId,
        int $taxDecimals,
        array &$aggregatedCartTaxes,
        array &$lineItemTaxes
    ): void {
        $promotionPrice = $lineItem->getPrice()?->getTotalPrice() ?? 0.0;
        $composition = $lineItem->getPayloadValue('composition');

        $weights = [];
        foreach (\is_array($composition) ? $composition : [] as $entry) {
            $id = \is_array($entry) ? ($entry['id'] ?? null) : null;
            $discount = \is_array($entry) ? abs((float) ($entry['discount'] ?? 0.0)) : 0.0;
            if (\is_string($id) && ($ratesByLineId[$id] ?? []) !== [] && $discount > 0.0) {
                $weights[$id] = ($weights[$id] ?? 0.0) + $discount;
            }
        }
        if ($weights === []) {
            foreach ($priceByLineId as $id => $price) {
                if (($ratesByLineId[$id] ?? []) !== [] && $price > 0.0) {
                    $weights[$id] = $price;
                }
            }
        }

        $totalWeight = array_sum($weights);
        $bases = [];
        foreach ($totalWeight > 0.0 ? $weights : [] as $id => $weight) {
            $share = $promotionPrice * ($weight / $totalWeight);
            foreach ($ratesByLineId[$id] as $taxName => $taxRate) {
                $key = $taxName . '|' . $taxRate;
                $bases[$key] ??= ['name' => (string) $taxName, 'rate' => $taxRate, 'price' => 0.0];
                $bases[$key]['price'] += $share;
            }
        }

        $bands = [];
        foreach ($bases as $base) {
            $bands[] = ['name' => $base['name'], 'rate' => $base['rate'], 'tax' => round($base['price'] * $base['rate'] / 100, $taxDecimals), 'price' => $base['price']];
        }
        $this->applyBands($lineItem, $bands, $aggregatedCartTaxes, $lineItemTaxes);
    }

    /**
     * The state a ZIP belongs to, from the rate table — for addresses that
     * carry a ZIP but no state.
     */
    private function stateForZip(string $zipCode): ?string
    {
        if ($zipCode === '') {
            return null;
        }

        if ($this->zipIndex === null) {
            $this->zipIndex = [];
            $content = @file_get_contents(__DIR__ . '/../../../../Config/TaxRates-US.json');
            $data = \is_string($content) ? json_decode($content, true) : null;
            foreach (\is_array($data) ? ($data['states'] ?? []) : [] as $stateCode => $zips) {
                foreach (array_keys($zips) as $zip) {
                    $this->zipIndex[(string) $zip] ??= (string) $stateCode;
                }
            }
        }

        return $this->zipIndex[$zipCode] ?? null;
    }

    private function getTaxRatesByZipCode(string $zipCode, string $state, bool $showTaxBreakdown): array
    {
        $jsonPath = __DIR__ . '/../../../../Config/TaxRates-US.json';

        $rateMapping = [
            'cbr' => 'CombinedRate',
            'str' => 'StateRate',
            'ctr' => 'CountyRate',
            'cir' => 'CityRate',
            'spr' => 'SpecialRate',
        ];

        $defaultRate = $this->getDefaultRateByTaxType('COMBINED-TAX') ?? 10;

        if (!file_exists($jsonPath)) {
            $this->ratesUnknown = true;

            return [
                $rateMapping['cbr'] => $defaultRate
            ];
        }

        $jsonContent = file_get_contents($jsonPath);
        if ($jsonContent === false) {
            $this->ratesUnknown = true;

            return [
                $rateMapping['cbr'] => $defaultRate
            ];
        }

        $taxData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->ratesUnknown = true;

            return [
                $rateMapping['cbr'] => $defaultRate
            ];
        }

        $stateCode = substr(strtoupper($state), -2);

        if (!isset($taxData['states'][$stateCode]) || !isset($taxData['states'][$stateCode][$zipCode])) {
            $this->ratesUnknown = true;

            return [
                $rateMapping['cbr'] => $defaultRate
            ];
        }

        $zipData = $taxData['states'][$stateCode][$zipCode];

        if ($showTaxBreakdown) {
            $result = [];
            foreach (['str', 'ctr', 'cir', 'spr'] as $key) {
                if (isset($zipData[$key])) {
                    $result[$rateMapping[$key]] = (float)$zipData[$key] * 100;
                }
            }
            return $result;
        } else {
            return [
                $rateMapping['cbr'] => isset($zipData['cbr']) ? (float)$zipData['cbr'] * 100 : 0.0
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
