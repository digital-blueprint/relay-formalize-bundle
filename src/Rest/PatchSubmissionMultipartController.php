<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Kekos\MultipartFormDataParser\Parser;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

class PatchSubmissionMultipartController extends AbstractSubmissionMultipartController
{
    private const SUBMITTED_FILES_TO_DELETE_PARAMETER = 'submittedFilesToDelete';

    /**
     * @throws ApiError
     */
    public function __invoke(string $identifier, Request $request): Submission
    {
        $this->requireAuthentication();

        $request = self::convertPatchRequest($request);

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

    private static function convertPatchRequest(Request $request): Request
    {
        $uploaded_file_factory = new Psr17Factory();
        $stream_factory = $uploaded_file_factory;

        $bridgeFactory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($bridgeFactory, $bridgeFactory, $bridgeFactory, $bridgeFactory);

        // convert to psr7 to decorate request with multipart formdata
        $psrRequest = $psrHttpFactory->createRequest($request);
        $parser = Parser::createFromRequest($psrRequest, $uploaded_file_factory, $stream_factory);
        $psrRequest = $parser->decorateRequest($psrRequest);

        // convert back to symfony request to get a symfony uploadedFile instead of a PSR-7 uploadedFile
        $httpFoundationFactory = new HttpFoundationFactory();

        return $httpFoundationFactory->createRequest($psrRequest);
    }
}
