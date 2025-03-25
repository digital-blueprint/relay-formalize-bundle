<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PostSubmissionMultipartController extends AbstractSubmissionMultipartController
{
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

        $this->updateSubmissionFromRequest($submission, $request);

        return $this->formalizeService->addSubmission($submission);
    }
}
