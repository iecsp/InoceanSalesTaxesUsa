<?php declare(strict_types=1);

use InoceanSalesTaxesUsa\Core\Checkout\Cart\Tax\UsaTaxProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(UsaTaxProvider::class)
        ->args([
            service(SystemConfigService::class),
            service('logger'),
        ])
        ->tag('shopware.tax.provider');
};
