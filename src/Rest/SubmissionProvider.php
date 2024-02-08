<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

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
        return $this->formalizeService->getSubmissions($currentPageNumber, $maxNumItemsPerPage, $filters);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
        //            && ($this->getCurrentUserAttribute('SCOPE_FORMALIZE')
        //                || $this->getCurrentUserAttribute('ROLE_FORMALIZE_TEST_USER')
        //                || $this->getCurrentUserAttribute('ROLE_DEVELOPER'));
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->canCurrentUserReadSubmissionsOfForm($submission->getForm());
    }

    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        // maybe required a form identifier in the future?
        $formIdentifier = $filters['formIdentifier'] ?? null;
        if ($formIdentifier !== null) {
            $form = $this->formalizeService->getForm($formIdentifier);

            return $this->authorizationService->canCurrentUserReadSubmissionsOfForm($form);
        }

        return false; // disallow get all submissions (currently used by the formalize frontend though)
    }
}
