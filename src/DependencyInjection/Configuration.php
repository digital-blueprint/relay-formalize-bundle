<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const DATABASE_URL = 'database_url';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_formalize');

        $treeBuilder->getRootNode()
            ->children()
            ->scalarNode(self::DATABASE_URL)->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
