<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\DataProvider;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Dbp\Relay\CoreBundle\Helpers\ArrayFullPaginator;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SubmissionCollectionDataProvider extends AbstractController implements CollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private $api;

    public function __construct(FormalizeService $api)
    {
        $this->api = $api;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Submission::class === $resourceClass;
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): ArrayFullPaginator
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        // TODO: Enable ROLE_SCOPE_FORMALIZE
//        $this->denyAccessUnlessGranted('ROLE_SCOPE_FORMALIZE');

        $perPage = 10000;
        $page = 1;

        $filters = $context['filters'] ?? [];
        if (isset($filters['page'])) {
            $page = (int) $filters['page'];
        }
        if (isset($filters['perPage'])) {
            $perPage = (int) $filters['perPage'];
        }

        return new ArrayFullPaginator($this->api->getSubmissions(), $page, $perPage);
    }
}
