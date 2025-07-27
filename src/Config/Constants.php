<?php declare(strict_types=1);
/*
 * Copyright (c) Inocean Technology (iecsp.com). All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

namespace InoceanSalesTaxesUsa\Config;

class Constants
{
    const RULE_NAME = 'Customers from U.S. - for Tax Provider';
    const USA_RULE_ID = '77b5387e345f40d28641e0b6dd278410';
    const TAX_PROVIDER_ID = '46875ffca8844b448751c635b090ea50';
    const TAXES = [        
        ['id' => 'a10512c6301e49dfaec634f169082078', 'tax_rate' => 10, 'name' => '(US) COMBINED TAX', 'position' => 0, 'tax_type' => 'COMBINED-TAX'],
        ['id' => '60cd957d3d174950874f25726c33721f', 'tax_rate' => 0, 'name' => '(US) TAX-FREE', 'position' => 0, 'tax_type' => 'TAX-FREE'],
    ];
    const DEFAULT_COUNTRY = 'US';
    const DEFAULT_STATE = 'LA';
}