<?php declare(strict_types=1);

namespace InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

class UsaCalculatedTaxCollection extends CalculatedTaxCollection
{
    /**
     * @param CalculatedTax[] $elements
     */
    public function __construct(iterable $elements = [])
    {
        // Do not call parent constructor, as it uses the tax rate as a key
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add($element): void
    {
        // Simply append the element, allowing duplicate tax rates
        $this->elements[] = $element;
    }

    protected function getElementKey(CalculatedTax $element): string
    {
        // Return a unique key for each element to prevent overwriting
        return spl_object_hash($element);
    }
}
