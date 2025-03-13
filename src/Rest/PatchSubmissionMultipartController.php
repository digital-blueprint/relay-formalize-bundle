<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class PatchSubmissionMultipartController extends AbstractController
{
    use CustomControllerTrait;

    public function __construct(private readonly FormalizeService $formalizeService)
    {
    }

    /**
     * @throws ApiError
     */
    public function __invoke(string $identifier, Request $request): Submission
    {
        $this->requireAuthentication();

        $submission = $this->formalizeService->getSubmissionByIdentifier($identifier);
        $previousSubmission = clone $submission;
        $parameters = $request->request->all();

        $submissionState = $parameters['submissionState'] ?? null;
        if ($submissionState !== null) {
            $submission->setSubmissionState(intval($submissionState));
        }

        $dataFeedElement = $parameters['dataFeedElement'] ?? null;
        if ($dataFeedElement !== null) {
            $submission->setDataFeedElement($dataFeedElement);
        }

        foreach ($request->files->all() as $uploadedFileName => $uploadedFile) {
        }

        return $this->formalizeService->updateSubmission($submission, $previousSubmission);
    }
}
