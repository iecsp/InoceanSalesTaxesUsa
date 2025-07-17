<?php declare(strict_types=1);

namespace InoceanSalesTaxesUsa\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TaxNameExtension extends AbstractExtension
{

    public function getFilters(): array
    {
        return [
            new TwigFilter('getTaxName', [$this, 'getTaxName']),
        ];
    }

    /** 
        * Example of US Inovice: 
        * SUBTOTAL                                         $238.94
        * --------------------------------------------------------
        * TAX BREAKDOWN:
        * California State Tax (7.25%)                    $17.32
        * Los Angeles County Tax (0.25%)                   $0.60
        * Los Angeles City Tax (0.50%)                     $1.19
        * Special Tax (0.50%)                              $1.19
        * Total Tax Rate: 8.50%                           $20.30
        * --------------------------------------------------------
        * TOTAL AMOUNT DUE                                 $259.24
    */

    public function getTaxName(string $zipCode, string $state): string
    {
        $jsonPath = __DIR__ . '/../Config/TaxRates-US.json';

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

        $rowMap = [
            'rgn' => 'Region Name',
            'cbr' => 'Combined Rate',
            'str' => 'State Rate',
            'ctr' => 'County Rate',
            'cir' => 'City Rate',
            'spr' => 'Special Rate'
        ];

        $stateCode = substr(strtoupper($state), -2);

        if (isset($taxData['states'][$stateCode][$zipCode])) {
            $zipData = $taxData['states'][$stateCode][$zipCode];
            // var_dump($zipData);
            return $zipData['cbr'];
        }

        return 'Tax';
    }

}