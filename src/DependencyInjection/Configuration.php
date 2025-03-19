<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DATABASE_URL = 'database_url';
    public const SUBMITTED_FILES_BUCKET_ID = 'submitted_files_bucket_id';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_formalize');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode(self::DATABASE_URL)->end()
            ->scalarNode(self::SUBMITTED_FILES_BUCKET_ID)->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
