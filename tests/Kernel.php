<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests;

use Dbp\Relay\AuthorizationBundle\DbpRelayAuthorizationBundle;
use Dbp\Relay\AuthorizationBundle\DependencyInjection\Configuration as AuthorizationConfiguration;
use Dbp\Relay\BlobBundle\DbpRelayBlobBundle;
use Dbp\Relay\CoreBundle\TestUtils\CoreTestKernelTrait;
use Dbp\Relay\FormalizeBundle\DbpRelayFormalizeBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use CoreTestKernelTrait;

    protected function registerAdditionalBundles(): iterable
    {
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new DbpRelayFormalizeBundle();
        yield new DbpRelayAuthorizationBundle();
        yield new DbpRelayBlobBundle();
    }

    protected function configureAdditionalContainer(ContainerConfigurator $container): void
    {
        $container->extension('dbp_relay_formalize', TestUtils::getTestConfig());
        $container->extension('dbp_relay_authorization', self::getAuthorizationTestConfig());
        $container->extension('dbp_relay_blob', TestUtils::getBlobTestConfig());
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
