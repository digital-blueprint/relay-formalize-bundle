<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Tools;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsController]
class PatchSubmissionMultipartController extends AbstractSubmissionMultipartController
{
    private const SUBMITTED_FILES_TO_DELETE_PARAMETER = 'submittedFilesToDelete';

    private ?KernelInterface $kernel = null;

    #[Required]
    public function __injectKernel(KernelInterface $kernel): void
    {
        $this->kernel = $kernel;
    }

    /**
     * @throws ApiError
     */
    public function __invoke(string $identifier, Request $request): Submission
    {
        $this->requireAuthentication();

        if ($this->kernel?->getEnvironment() !== 'test') {
            $request = Tools::fixMultipartPatchRequest($request);
        }

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
