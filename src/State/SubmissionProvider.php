<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dbp\Relay\CoreBundle\Helpers\ArrayFullPaginator;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SubmissionProvider extends AbstractController implements ProviderInterface
{
    /**
     * @var FormalizeService
     */
    private $api;

    public function __construct(FormalizeService $api)
    {
        $this->api = $api;
    }

    /**
     * @return Submission|iterable<Submission>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->denyAccessUnlessGranted('ROLE_SCOPE_FORMALIZE');

        if ($operation instanceof CollectionOperationInterface) {
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
        } else {
            $id = $uriVariables['identifier'];

            return $this->api->getSubmissionByIdentifier($id);
        }
    }
}
