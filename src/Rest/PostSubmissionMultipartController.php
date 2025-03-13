<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostSubmissionMultipartController extends AbstractController
{
    use CustomControllerTrait;

    public function __construct(private readonly FormalizeService $formalizeService)
    {
    }

    public function __invoke(Request $request): Submission
    {
        $this->requireAuthentication();

        $parameters = $request->request->all();

        $formIri = $parameters['form'] ?? null;
        if (false === preg_match('/^\/formalize\/forms\/(.+)$/', $formIri, $matches)) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Form could not be found',
                FormalizeService::FORM_NOT_FOUND_ERROR_ID);
        }
        $formIdentifier = $matches[1];

        $submission = new Submission();
        $submission->setForm($this->formalizeService->getForm($formIdentifier));

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

        $this->formalizeService->addSubmission($submission);

        return $submission;
    }
}
