<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Doctrine\DoctrineConfiguration;
use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayFormalizeExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    public const FORMALIZE_ENTITY_MANAGER_ID = 'dbp_relay_formalize_bundle';
    public const FORMALIZE_DB_CONNECTION_ID = 'dbp_relay_formalize_bundle';

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $definition = $container->getDefinition(SubmittedFileService::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);

        $this->addResourceClassDirectory($container, __DIR__.'/../Entity');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        DoctrineConfiguration::prependEntityManagerConfig($container, self::FORMALIZE_ENTITY_MANAGER_ID,
            $config[Configuration::DATABASE_URL] ?? '',
            __DIR__.'/../Entity',
            'Dbp\Relay\FormalizeBundle\Entity',
            self::FORMALIZE_DB_CONNECTION_ID);
        DoctrineConfiguration::prependMigrationsConfig($container,
            __DIR__.'/../Migrations',
            'Dbp\Relay\FormalizeBundle\Migrations');
    }
}
