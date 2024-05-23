<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

/**
 * @extends AbstractDataProvider<Submission>
 */
class SubmissionProvider extends AbstractDataProvider
{
    private const GET_ALL_SUBMISSIONS_QUERY_PARAMETER = 'getAll';

    private FormalizeService $formalizeService;
    private AuthorizationService $authorizationService;
    private ?Form $cachedForm = null;

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
        $formIdentifier = Common::getFormIdentifier($filters);

        if (self::areAllFormSubmissionsRequested($filters)) {
            $submissions = $this->formalizeService->getSubmissionsByForm($formIdentifier,
                $currentPageNumber, $maxNumItemsPerPage);
        } elseif ($this->getForm($formIdentifier)->getSubmissionLevelAuthorization()) {
            // TODO: define a page size limit in authorization, request max page size and re-request if a full page is returned
            $readGrantedSubmissionIdentifiers =
                $this->authorizationService->getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(1, 1024);
            $formSubmissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm($formIdentifier);

            $readGrantedFormSubmissionIdentifiers = array_intersect($readGrantedSubmissionIdentifiers, $formSubmissionIdentifiers);
            $submissionIdentifiers = array_slice($readGrantedFormSubmissionIdentifiers,
                Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage), $maxNumItemsPerPage);

            $submissions = $this->formalizeService->getSubmissionsByIdentifiers($submissionIdentifiers, 1, $maxNumItemsPerPage);
        } else {
            $submissions = [];
        }

        return $submissions;
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        return $this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($item->getForm());
    }

    /**
     * @throws ApiError
     */
    protected function isCurrentUserAuthorizedToGetCollection(array $filters): bool
    {
        return
            !self::areAllFormSubmissionsRequested($filters)
            || $this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions(
                $this->getForm(Common::getFormIdentifier($filters)));
    }

    /**
     * @throws ApiError
     */
    private function getForm(string $formIdentifier): Form
    {
        if ($this->cachedForm === null || $this->cachedForm->getIdentifier() !== $formIdentifier) {
            $this->cachedForm = $this->formalizeService->getForm($formIdentifier);
        }

        return $this->cachedForm;
    }

    private static function areAllFormSubmissionsRequested(array $filters): bool
    {
        return isset($filters[self::GET_ALL_SUBMISSIONS_QUERY_PARAMETER]);
    }
}
