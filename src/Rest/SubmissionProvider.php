<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

/**
 * @extends AbstractDataProvider<Submission>
 */
class SubmissionProvider extends AbstractDataProvider
{
    /**
     * @var FormalizeService
     */
    private $formalizeService;

    /** @var AuthorizationService */
    private $authorizationService;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getSubmissionByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->formalizeService->getSubmissionsByForm(Common::getFormIdentifier($filters),
            $currentPageNumber, $maxNumItemsPerPage);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($submission->getForm());
    }

    /**
     * @throws ApiError
     */
    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        $form = $this->formalizeService->getForm(Common::getFormIdentifier($filters));

        return $this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form);
    }
}
