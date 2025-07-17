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

    // "us_tax_rates": {
    //     "last_updated": "2025-07-16",
    //     "states": {
    //         "ME": {
    //             "state_name": "ME",
    //             "state_rate": "0.055",
    //             "zip_codes": {
    //                 "3579":   {
    //                     "tax_region_name": "MAINE",
    //                     "estimated_combined_rate": "0.055",
    //                     "state_rate": "0.055",
    //                     "estimated_county_rate": "0.0",
    //                     "estimated_city_rate": "0.0",
    //                     "estimated_special_rate": "0.0",
    //                     "risk_level": "Low"
    //                 }
    //             }
    //         }
    //     }
    // }
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
    public function getTaxName(float $value, string $language = 'fr'): string
    {
        
        $taxNames['fr'] = [
            0 => 'Sans taxe',
            5 => 'TPS',
            6 => 'TVP',
            7 => 'TVP',
            9.975 => 'TVQ',
            13 => 'TVH',
            14 => 'TVH',
            15 => 'TVH',
        ];

        $taxNames['en'] = [
            0 => 'NO-TAX',
            5 => 'GST',
            6 => 'PST',
            7 => 'PST',
            9.975 => 'QST',
            13 => 'HST',
            14 => 'HST',
            15 => 'HST',
        ];

        return $taxNames[$language][$value] ?? $taxNames['en'][$value] ?? 'Tax';
    }

}