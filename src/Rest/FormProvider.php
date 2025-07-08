<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends AbstractDataProvider<Form>
 */
class FormProvider extends AbstractDataProvider
{
    private bool $isAuthorizationSuspended = false;

    public function __construct(
        protected readonly FormalizeService $formalizeService,
        protected readonly AuthorizationService $authorizationService,
        protected readonly RequestStack $requestStack)
    {
        parent::__construct();
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getForm($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        $maxNumResults = min($maxNumItemsPerPage, ResourceActionGrantService::MAX_NUM_RESULTS_MAX);
        $firstResultIndex = Pagination::getFirstItemIndex($currentPageNumber, $maxNumResults);

        if (($value = $filters[FormalizeService::WHERE_MAY_READ_SUBMISSIONS_FILTER] ?? null) !== null) {
            $filters[FormalizeService::WHERE_MAY_READ_SUBMISSIONS_FILTER] =
                filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $this->formalizeService->getFormsCurrentUserIsAuthorizedToRead(
            $firstResultIndex, $maxNumItemsPerPage, $filters);
    }

    /**
     * Note: In case this is an internal get item call, i.e. when a form IRI is sent by the client and resolved by ApiPlatform
     * (which is currently the case for a submission POST),
     * we don't require read permissions to the form, i.e. we allow "post only" forms.
     */
    protected function isCurrentUserAuthorizedToAccessItem(int $operation, mixed $item, array $filters): bool
    {
        $form = $item;
        assert($form instanceof Form);

        return $this->isAuthorizationSuspended
            || $this->authorizationService->isCurrentUserAuthorizedToReadForm($form);
    }

    public function setIsAuthorizationSuspended(bool $isSuspended): void
    {
        $this->isAuthorizationSuspended = $isSuspended;
    }
}
