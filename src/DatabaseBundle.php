<?php

declare(strict_types=1);

namespace Scafera\Database;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class DatabaseBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'url' => '%env(DATABASE_URL)%',
            ],
            'orm' => [
                'auto_mapping' => false,
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => '%kernel.project_dir%/src/Entity',
                        'prefix' => 'App\Entity',
                        'alias' => 'App',
                    ],
                ],
            ],
        ]);

        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'App\\Migrations' => '%kernel.project_dir%/support/migrations',
            ],
            'services' => [
                \Doctrine\Migrations\Version\MigrationFactory::class => Migration\MigrationFactory::class,
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(EntityStore::class)
                ->autowire()
                ->public()
            ->set(Transaction::class)
                ->autowire()
                ->public()
            ->set(EventListener\UnflushedWriteDetector::class)
                ->autowire()
                ->tag('kernel.event_subscriber')
            ->set(Validator\DatabaseUrlValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\SchemaDriftValidator::class)
                ->autowire()
                ->tag('scafera.validator')
            ->set(Validator\AuditableInitValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\RepositoryDisciplineValidator::class)
                ->tag('scafera.validator')
            ->set(Validator\SeederNamingValidator::class)
                ->tag('scafera.validator')

            ;

        // Any SeederInterface implementation gets tagged — works across all bundles
        $builder->registerForAutoconfiguration(SeederInterface::class)
            ->addTag('scafera.seeder');

        // Auto-discover seeders from support/seeds/
        $seedDir = $builder->getParameter('kernel.project_dir') . '/support/seeds/';

        if (is_dir($seedDir)) {
            $container->services()
                ->load('App\\Seed\\', $seedDir . '*.php')
                    ->autowire()
                    ->autoconfigure();
        }

        // Alias Doctrine migrations DependencyFactory for autowiring
        $container->services()
            ->alias(\Doctrine\Migrations\DependencyFactory::class, 'doctrine.migrations.dependency_factory');

        // Scafera migration factory (replaces Doctrine's default)
        $container->services()
            ->set(Migration\MigrationFactory::class)
                ->autowire();

        // Mapping driver (used by schema inspection commands)
        $container->services()
            ->set(Mapping\ScaferaMappingDriver::class)
                ->args([
                    '%kernel.project_dir%/src/Entity',
                    'App\\Entity',
                ]);

        // Schema diff inspector (used by diff command and validator)
        $container->services()
            ->set(Schema\SchemaDiffInspector::class)
                ->autowire();

        // Database CLI commands
        $container->services()
            ->set(Command\MigrateCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\MigrateCreateCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\MigrateDiffCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\MigrateStatusCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\MigrateRollbackCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\MigrateDropCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\ResetCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\SeedCommand::class)
                ->autowire()
                ->arg('$seeders', tagged_iterator('scafera.seeder'))
                ->tag('console.command')
            ->set(Command\SchemaListCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\SchemaShowCommand::class)
                ->autowire()
                ->tag('console.command')
            ->set(Command\SchemaDiffCommand::class)
                ->autowire()
                ->tag('console.command');
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DependencyInjection\ScaferaMappingPass());
        $container->addCompilerPass(new DependencyInjection\DoctrineBoundaryPass());
    }
}
