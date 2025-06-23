<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Service\SubmittedFileService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsController]
class PostSubmissionController extends AbstractSubmissionController
{
    public function __construct(
        FormalizeService $formalizeService,
        SubmittedFileService $submittedFileService,
        AuthorizationService $authorizationService,
        private readonly SerializerInterface $serializer)
    {
        parent::__construct($formalizeService, $submittedFileService, $authorizationService);
    }

    public function __invoke(Request $request): Submission
    {
        $this->requireAuthentication();

        switch ($request->getContentTypeFormat()) {
            case 'form':
                $formIri = $request->request->all()['form'] ?? null;
                if ($formIri === null) {
                    FormalizeService::throwRequiredFieldMissing('form');
                }

                if (false === preg_match('/^\/formalize\/forms\/(.+)$/', $formIri, $matches)) {
                    throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Form could not be found',
                        FormalizeService::FORM_NOT_FOUND_ERROR_ID);
                }
                $formIdentifier = $matches[1];

                $form = $this->formalizeService->getForm($formIdentifier);
                $this->assertIsAuthorizedToCreateFormSubmissions($form);

                $submission = new Submission();
                $submission->setForm($form);

                $parameters = $request->request->all();
                $this->updateSubmissionFromRequest($submission, $parameters, $request->files->all());
                break;

            case 'jsonld':
                try {
                    /** @var Submission $submission */
                    $submission = $this->serializer->deserialize($request->getContent(), Submission::class, 'json');
                } catch (\Exception $exception) {
                    throw $exception;
                    throw ApiError::withDetails(Response::HTTP_BAD_REQUEST, 'Failed to serialize submission: '.
                    $exception->getMessage());
                }
                $form = $submission->getForm();
                if ($form === null) {
                    FormalizeService::throwRequiredFieldMissing('form');
                }
                $this->assertIsAuthorizedToCreateFormSubmissions($form);
                break;

            default:
                throw new UnsupportedMediaTypeHttpException($request->getContentTypeFormat());
        }

        return $this->formalizeService->addSubmission($submission);
    }

    private function assertIsAuthorizedToCreateFormSubmissions(Form $form): void
    {
        if (false ===
            $this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form)) {
            throw ApiError::withDetails(Response::HTTP_FORBIDDEN, 'forbidden');
        }
    }
}
