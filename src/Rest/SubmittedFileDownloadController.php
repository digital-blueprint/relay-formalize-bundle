<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class SubmittedFileDownloadController extends AbstractController
{
    use CustomControllerTrait;

    public function __construct(
        private readonly SubmittedFileService $submittedFileService,
        private readonly AuthorizationService $authorizationService)
    {
    }

    public function __invoke(string $identifier): Response
    {
        $this->requireAuthentication();

        if (false === $this->authorizationService->isCurrentUserAuthorizedToReadSubmission(
            $this->submittedFileService->getSubmittedFileByIdentifier($identifier)->getSubmission())) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'forbidden');
        }

        return $this->submittedFileService->getBinarySubmittedFileResponse($identifier);
    }
}
