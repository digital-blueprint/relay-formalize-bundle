<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DependencyInjection;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DATABASE_URL = 'database_url';
    public const DEFAULT_BLOB_TYPE = 'default_blob_type';

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

        return $treeBuilder;
    }
}
