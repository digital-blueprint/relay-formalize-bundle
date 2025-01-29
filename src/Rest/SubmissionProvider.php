<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

/**
 * @extends AbstractDataProvider<Submission>
 */
class SubmissionProvider extends AbstractDataProvider
{
    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getSubmissionByIdentifier($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $maxNumResults = min($maxNumItemsPerPage, ResourceActionGrantService::MAX_NUM_RESULTS_MAX);
        $firstResultIndex = Pagination::getFirstItemIndex($currentPageNumber, $maxNumResults);

        $formIdentifier = Common::tryGetFormIdentifier($filters);
        if ($formIdentifier !== null) {
            $submissions = $this->getSubmissionsByForm($formIdentifier, $filters, $firstResultIndex, $maxNumResults);
        } else {
            // first we fill pages with submissions from forms where the user has read_submissions grants for,
            // second with single submissions the user is authorized to read
            $formIdentifiersMayReadSubmissionsOf =
                $this->authorizationService->getFormIdentifiersCurrentUserIsAuthorizedToReadSubmissionsOf(
                    0, ResourceActionGrantService::MAX_NUM_RESULTS_MAX);
            $submissions = $this->formalizeService->getSubmissionsByForms($formIdentifiersMayReadSubmissionsOf,
                $filters, $firstResultIndex, $maxNumResults);
            $numPageItemsFromFormsWhereGrantedReadSubmissions = count($submissions);

            // as long as we get full pages we're done, otherwise we get single submissions the user has read grants for
            if ($numPageItemsFromFormsWhereGrantedReadSubmissions < $maxNumResults) {
                $readGrantedSubmissionIdentifiers =
                    $this->authorizationService->getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(
                        0, ResourceActionGrantService::MAX_NUM_RESULTS_MAX);

                if ($numPageItemsFromFormsWhereGrantedReadSubmissions === 0) { // single submissions only page
                    $startIndex = $firstResultIndex -
                        $this->formalizeService->getCountSubmissionsByForms($formIdentifiersMayReadSubmissionsOf, $filters);
                    $submissions = $this->formalizeService->getSubmissionsByIdentifiers(
                        $readGrantedSubmissionIdentifiers, $formIdentifiersMayReadSubmissionsOf, $filters, $startIndex, $maxNumResults);
                } else { // mixed page
                    $submissions = array_merge($submissions, $this->formalizeService->getSubmissionsByIdentifiers(
                        $readGrantedSubmissionIdentifiers, $formIdentifiersMayReadSubmissionsOf, $filters,
                        0, $maxNumResults - $numPageItemsFromFormsWhereGrantedReadSubmissions));
                }
            }
        }

        return $submissions;
    }

    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        $submission = $item;
        assert($submission instanceof Submission);

        return $this->authorizationService->isCurrentUserAuthorizedToReadSubmission($submission);
    }

    private function getSubmissionsByForm(string $formIdentifier, array $filters, int $firstResultIndex, int $maxNumResults): array
    {
        $formSubmissions = [];
        $form = $this->formalizeService->tryGetForm($formIdentifier);
        if ($form !== null) {
            if ($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form)) {
                $formSubmissions = $this->formalizeService->getSubmissionsByForm($formIdentifier, $filters,
                    $firstResultIndex, $maxNumResults);
            } elseif ($form->getGrantBasedSubmissionAuthorization()) {
                $readGrantedSubmissionIdentifiers = [];
                do {
                    $identifierPage =
                        $this->authorizationService->getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(
                            0, ResourceActionGrantService::MAX_NUM_RESULTS_MAX);
                    $readGrantedSubmissionIdentifiers = array_merge($readGrantedSubmissionIdentifiers, $identifierPage);
                } while (count($identifierPage) === ResourceActionGrantService::MAX_NUM_RESULTS_MAX);

                $formSubmissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm($formIdentifier);
                $readGrantedFormSubmissionIdentifiers = array_intersect($readGrantedSubmissionIdentifiers, $formSubmissionIdentifiers);
                $submissionIdentifiers = array_slice($readGrantedFormSubmissionIdentifiers, $firstResultIndex, $maxNumResults);

                $formSubmissions = $this->formalizeService->getSubmissionsByIdentifiers($submissionIdentifiers,
                    null, $filters, 0, $maxNumResults);
            }
        }

        return $formSubmissions;
    }
}
