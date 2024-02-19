<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProvider;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FormProvider extends AbstractDataProvider
{
    /** @var FormalizeService */
    private $formalizeService;

    /** @var AuthorizationService */
    private $authorizationService;

    /** @var RequestStack */
    private $requestStack;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService, RequestStack $requestStack)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
        $this->requestStack = $requestStack;
    }

    protected function getItemById(string $id, array $filters = [], array $options = []): ?object
    {
        return $this->formalizeService->getForm($id);
    }

    protected function getPage(int $currentPageNumber, int $maxNumItemsPerPage, array $filters = [], array $options = []): array
    {
        return $this->authorizationService->getFormsUserCanRead(
            $this->formalizeService->getForms($currentPageNumber, $maxNumItemsPerPage));
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated();
    }

    /**
     * Note: In case the method is called by an internal item provider call
     * (when a form IRI is sent by the user and resolved by ApiPlatform, which is currently the case for a Submission POST)
     * we don't require read permissions to the form, i.e. we allow "post only" forms.
     */
    protected function isCurrentUserAuthorizedToAccessItem(int $operation, $item, array $filters): bool
    {
        $form = $item;
        assert($form instanceof Form);

        return $this->requestStack->getCurrentRequest()->getMethod() === Request::METHOD_POST
            || $this->authorizationService->canCurrentUserReadForm($form);
    }
}
