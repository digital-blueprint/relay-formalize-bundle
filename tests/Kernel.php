<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Dbp\Relay\AuthorizationBundle\DbpRelayAuthorizationBundle;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration as AuthorizationConfiguration;
use Dbp\Relay\CoreBundle\DbpRelayCoreBundle;
use Dbp\Relay\FormalizeBundle\DbpRelayFormalizeBundle;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Nelmio\CorsBundle\NelmioCorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new NelmioCorsBundle();
        yield new MonologBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new ApiPlatformBundle();
        yield new DbpRelayFormalizeBundle();
        yield new DbpRelayAuthorizationBundle();
        yield new DbpRelayCoreBundle();
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
        $routes->import('@DbpRelayCoreBundle/Resources/config/routing.yaml');
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader)
    {
        $container->import('@DbpRelayCoreBundle/Resources/config/services_test.yaml');
        $container->extension('framework', [
            'test' => true,
            'secret' => '',
            'annotations' => false,
        ]);

        $container->extension('dbp_relay_formalize', [
            Configuration::DATABASE_URL => 'sqlite:///:memory:',
        ]);

        $container->extension('dbp_relay_authorization', self::getAuthorizationTestConfig());
    }

    public static function getAuthorizationTestConfig(): array
    {
        return [
            AuthorizationConfiguration::DATABASE_URL => 'sqlite:///:memory:',
            AuthorizationConfiguration::CREATE_GROUPS_POLICY => 'user.get("MAY_CREATE_GROUPS")',
            AuthorizationConfiguration::RESOURCE_CLASSES => [
                [
                    AuthorizationConfiguration::IDENTIFIER => 'DbpRelayFormalizeForm',
                    AuthorizationConfiguration::MANAGE_RESOURCE_COLLECTION_POLICY => 'user.get("MAY_CREATE_FORMS")',
                ],
            ],
        ];
    }
}
