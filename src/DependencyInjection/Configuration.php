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
    public const MAY_UPDATE_FORM = 'MAY_UPDATE_FORM';
    public const MAY_DELETE_FORM = 'MAY_DELETE_FORM';
    public const MAY_READ_FORM = 'MAY_READ_FORM';
    public const MAY_CREATE_FORM_SUBMISSIONS = 'MAY_CREATE_FORM_SUBMISSIONS';
    public const MAY_UPDATE_FORM_SUBMISSIONS = 'MAY_UPDATE_FORM_SUBMISSIONS';
    public const MAY_DELETE_FORM_SUBMISSIONS = 'MAY_DELETE_FORM_SUBMISSIONS';
    public const MAY_READ_FORM_SUBMISSIONS = 'MAY_READ_FORM_SUBMISSIONS';

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
            ->addResourcePermission(self::MAY_UPDATE_FORM, 'false',
                'Returns true if the user is allowed to update the given form, false otherwise')
            ->addResourcePermission(self::MAY_DELETE_FORM, 'false',
                'Returns true if the user is allowed to delete the given form, false otherwise')
            ->addResourcePermission(self::MAY_READ_FORM, 'false',
                'Returns true if the user is allowed to read the given form, false otherwise')
            ->addResourcePermission(self::MAY_CREATE_FORM_SUBMISSIONS, 'false',
                'Returns true if the user is allowed to add a submission to the given form, false otherwise')
            ->addResourcePermission(self::MAY_UPDATE_FORM_SUBMISSIONS, 'false',
                'Returns true if the user is allowed to update all form submissions, false otherwise')
            ->addResourcePermission(self::MAY_DELETE_FORM_SUBMISSIONS, 'false',
                'Returns true if the user is allowed to delete all form submissions, false otherwise')
            ->addResourcePermission(self::MAY_READ_FORM_SUBMISSIONS, 'false',
                'Returns true if the user is allowed to read all form submissions, false otherwise')
            ->getNodeDefinition();
    }
}
