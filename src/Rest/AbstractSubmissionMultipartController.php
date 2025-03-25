<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class AbstractSubmissionMultipartController
{
    use CustomControllerTrait;

    public function __construct(
        protected readonly FormalizeService $formalizeService,
        protected readonly SubmittedFileService $submittedFileService)
    {
    }

    protected function updateSubmissionFromRequest(Submission $submission, Request $request): void
    {
        $parameters = $request->request->all();

        $submissionState = $parameters['submissionState'] ?? null;
        if ($submissionState !== null) {
            $submission->setSubmissionState(intval($submissionState));
        }

        $dataFeedElement = $parameters['dataFeedElement'] ?? null;
        if ($dataFeedElement !== null) {
            $submission->setDataFeedElement($dataFeedElement);
        }

        /** @var UploadedFile[]|UploadedFile $uploaded */
        foreach ($request->files->all() as $fileAttributeName => $uploaded) {
            $this->submittedFileService->addSubmittedFilesToSubmission($fileAttributeName,
                is_array($uploaded) ? $uploaded : [$uploaded], $submission);
        }
    }
}
