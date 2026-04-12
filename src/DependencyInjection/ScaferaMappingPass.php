<?php

declare(strict_types=1);

namespace Scafera\Database\DependencyInjection;

use Scafera\Database\Mapping\ScaferaMappingDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** @internal Replaces Doctrine's attribute metadata driver with Scafera's mapping driver. */
final class ScaferaMappingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $driverId = 'doctrine.orm.default_attribute_metadata_driver';

        if (!$container->hasDefinition($driverId)) {
            return;
        }

        $def = $container->getDefinition($driverId);
        $def->setClass(ScaferaMappingDriver::class);
        $def->setArguments([
            '%kernel.project_dir%/src/Entity',
            'App\\Entity',
        ]);
    }
}
