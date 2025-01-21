<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\CustomControllerTrait;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class CreateSubmissionController extends AbstractController
{
    use CustomControllerTrait;

    public function __construct(private readonly FormalizeService $formalizeService)
    {
    }

    public function __invoke(Request $request): Submission
    {
        $this->requireAuthentication();

        $parameters = $request->request->all();
        $formIdentifier = Common::getFormIdentifier($parameters);
        unset($parameters[Common::FORM_IDENTIFIER_PARAMETER]);

        $submission = new Submission();
        $submission->setForm($this->formalizeService->getForm($formIdentifier));
        try {
            $submission->setDataFeedElement(json_encode($parameters, flags: JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        foreach ($request->files->all() as $uploadedFileName => $uploadedFile) {
        }

        $this->formalizeService->addSubmission($submission);

        return $submission;
    }
}
