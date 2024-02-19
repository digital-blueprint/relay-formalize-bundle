<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoveAllFormSubmissionsController extends AbstractController
{
    /** @var FormalizeService */
    private $formalizeService;

    /** @var AuthorizationService */
    private $authorizationService;

    public function __construct(FormalizeService $formalizeService, AuthorizationService $authorizationService)
    {
        $this->formalizeService = $formalizeService;
        $this->authorizationService = $authorizationService;
    }

    public function __invoke(Request $request)
    {
        if (!$this->authorizationService->isAuthenticated()) {
            throw new ApiError(Response::HTTP_UNAUTHORIZED, 'access denied');
        }

        $filters = $request->query->all();
        $form = $this->formalizeService->getForm(Common::getFormIdentifier($filters));

        if (!$this->authorizationService->canCurrentUserWriteForm($form)) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        $this->formalizeService->removeAllFormSubmissions($form->getIdentifier());
    }
}
