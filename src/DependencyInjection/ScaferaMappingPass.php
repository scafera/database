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

        if (!$container->hasParameter('scafera.entity_dir')) {
            return;
        }

        $entityDir = $container->getParameter('scafera.entity_dir');

        if (!is_dir($entityDir)) {
            return;
        }

        $def = $container->getDefinition($driverId);
        $def->setClass(ScaferaMappingDriver::class);
        $def->setArguments([
            $entityDir,
            $container->getParameter('scafera.entity_namespace'),
        ]);
    }
}
