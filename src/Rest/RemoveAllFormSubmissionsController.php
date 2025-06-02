<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoveAllFormSubmissionsController extends AbstractController
{
    use CustomControllerTrait;

    public function __construct(
        protected readonly FormalizeService $formalizeService,
        protected readonly AuthorizationService $authorizationService)
    {
    }

    public function __invoke(Request $request): void
    {
        $this->requireAuthentication();

        $filters = $request->query->all();
        $form = $this->formalizeService->getForm(Common::getFormIdentifier($filters));

        if (!$this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form)) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        // TODO: consider adding an option to also delete drafts? very dangerous though...

        $this->formalizeService->removeAllSubmittedFormSubmissions($form->getIdentifier());
    }
}
