<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AbstractSubmissionController
{
    use CustomControllerTrait;

    public function __construct(
        protected readonly FormalizeService $formalizeService,
        protected readonly SubmittedFileService $submittedFileService,
        protected readonly AuthorizationService $authorizationService)
    {
    }

    protected function updateSubmissionFromRequest(Submission $submission, array &$parameters, array $files): void
    {
        if (isset($parameters['submissionState'])) {
            $submission->setSubmissionState(intval($parameters['submissionState']));
            unset($parameters['submissionState']);
        }

        if (isset($parameters['dataFeedElement'])) {
            $submission->setDataFeedElement($parameters['dataFeedElement']);
            unset($parameters['dataFeedElement']);
        }

        if (isset($parameters['tags'])) {
            // TODO: validate tags
            $submission->setTags($parameters['tags']);
            unset($parameters['tags']);
        }

        /** @var UploadedFile[]|UploadedFile $uploaded */
        foreach ($files as $fileAttributeName => $uploaded) {
            $this->submittedFileService->addSubmittedFilesToSubmission($fileAttributeName,
                is_array($uploaded) ? $uploaded : [$uploaded], $submission);
        }
    }
}
