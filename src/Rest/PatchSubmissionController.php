<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Tools;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PatchSubmissionController extends AbstractSubmissionController
{
    private const SUBMITTED_FILES_TO_DELETE_PARAMETER = 'submittedFilesToDelete';

    /**
     * @throws ApiError
     */
    public function __invoke(string $identifier, Request $request): Submission
    {
        $this->requireAuthentication();

        if (str_starts_with($request->headers->get('Content-Type') ?? '', 'multipart/form-data; boundary')) {
            $request = Tools::fixMultipartPatchRequest($request);
        }

        $submission = $this->formalizeService->getSubmissionByIdentifier($identifier);
        $this->assertIsAuthorizedToUpdateSubmission($submission);

        $previousSubmission = clone $submission;
        $parameters = $request->request->all();
        $this->updateSubmissionFromRequest($submission, $parameters, $request->files->all());

        $submittedFilesToDelete = [];
        if ($submittedFilesParameter = $parameters['submittedFiles'] ?? null) {
            if (false === is_array($submittedFilesParameter)) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Invalid syntax: submittedFiles');
            }
            foreach ($submittedFilesParameter as $submittedFileIdentifier => $value) {
                if ($value !== 'null') {
                    throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Invalid syntax: submittedFiles');
                }
                $submittedFilesToDelete[] = $submittedFileIdentifier;
            }
        }
        $this->submittedFileService->removeSubmittedFilesFromSubmission($submittedFilesToDelete, $submission);

        return $this->formalizeService->updateSubmission($submission, $previousSubmission);
    }

    private function assertIsAuthorizedToUpdateSubmission(Submission $submission): void
    {
        if (false ===
            $this->authorizationService->isCurrentUserAuthorizedToUpdateSubmission($submission)) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'forbidden');
        }
    }
}
