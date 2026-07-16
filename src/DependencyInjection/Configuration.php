<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DependencyInjection;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationConfigDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DATABASE_URL = 'database_url';
    public const DEFAULT_BLOB_TYPE = 'default_blob_type';
    public const MAY_CREATE_FORM = 'MAY_CREATE_FORM';

    /**
     * @throws BlobApiError
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_formalize');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode(self::DATABASE_URL)->end()
                ->scalarNode(self::DEFAULT_BLOB_TYPE)->defaultNull()
                    ->info('Sets the blob type for all uploaded files')
                ->end()
             ->end();

        $rootNode->append(BlobApi::getConfigNodeDefinition());
        $rootNode->append($this->getAuthorizationNodeDefinition());

        return $treeBuilder;
    }

    private function getAuthorizationNodeDefinition(): NodeDefinition
    {
        return AuthorizationConfigDefinition::create()
            ->addResourcePermission(self::MAY_CREATE_FORM, 'false',
                'Returns true if the user is allowed to add the given form, false otherwise')
            ->getNodeDefinition();
    }
}
