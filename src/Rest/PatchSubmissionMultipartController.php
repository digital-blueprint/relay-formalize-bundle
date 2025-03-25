<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Tools;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PatchSubmissionMultipartController extends AbstractSubmissionMultipartController
{
    private const SUBMITTED_FILES_TO_DELETE_PARAMETER = 'submittedFilesToDelete';

    /**
     * @throws ApiError
     */
    public function __invoke(string $identifier, Request $request): Submission
    {
        $this->requireAuthentication();

        $request = Tools::fixMultipartPatchRequest($request);

        $submission = $this->formalizeService->getSubmissionByIdentifier($identifier);
        $previousSubmission = clone $submission;

        $this->updateSubmissionFromRequest($submission, $request);

        $submittedFilesToDelete = [];
        if ($submittedFilesToDeleteString = $request->request->all()[self::SUBMITTED_FILES_TO_DELETE_PARAMETER] ?? null) {
            $submittedFilesToDelete = explode(',', $submittedFilesToDeleteString);
        }
        $this->submittedFileService->removeSubmittedFilesFromSubmission($submittedFilesToDelete, $submission);

        return $this->formalizeService->updateSubmission($submission, $previousSubmission);
    }
}
