<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\PatchSubmissionController;
use Dbp\Relay\FormalizeBundle\Rest\PostSubmissionController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractSubmissionControllerTestCase extends RestTestCase
{
    protected ?PatchSubmissionController $patchSubmissionController = null;
    protected ?PostSubmissionController $postSubmissionController = null;

    protected function setUp(): void
    {
        parent::setUp();

        $userSession = new TestUserSession(self::CURRENT_USER_IDENTIFIER, isAuthenticated: true);

        /** @var SerializerInterface $serializer */
        $serializer = static::getContainer()->get('serializer');
        $this->postSubmissionController = new PostSubmissionController(
            $this->formalizeService, $this->submittedFileService, $this->authorizationService, $serializer);
        $this->postSubmissionController->__injectUserSession($userSession);

        $this->patchSubmissionController = new PatchSubmissionController(
            $this->formalizeService, $this->submittedFileService, $this->authorizationService);
        $this->patchSubmissionController->__injectUserSession($userSession);
    }

    protected function postSubmission(?string $formIdentifier, ?string $dataFeedElement = null, ?int $submissionState = null,
        array $files = [], bool $testDeprecateJsonLd = false): Submission
    {
        if ($testDeprecateJsonLd) {
            $submissionData = [];
            if ($formIdentifier !== null) {
                $submissionData['form'] = '/formalize/forms/'.$formIdentifier;
            }
            if ($dataFeedElement !== null) {
                $submissionData['dataFeedElement'] = $dataFeedElement;
            }
            if ($submissionState !== null) {
                $submissionData['submissionState'] = $submissionState;
            }
            $request = Request::create('http://formalize.net/formalize/submissions', 'POST',
                server: ['CONTENT_TYPE' => 'application/ld+json'],
                content: json_encode($submissionData));
        } else {
            $parameters = [];
            if ($formIdentifier !== null) {
                $parameters['form'] = '/formalize/forms/'.$formIdentifier;
            }
            if ($dataFeedElement !== null) {
                $parameters['dataFeedElement'] = $dataFeedElement;
            }
            if ($submissionState !== null) {
                $parameters['submissionState'] = $submissionState;
            }
            $request = Request::create('http://formalize.net/formalize/submissions', 'POST', $parameters,
                files: $files, server: ['CONTENT_TYPE' => 'multipart/form-data']);
        }

        return $this->postSubmissionController->__invoke($request);
    }

    protected function patchSubmission(string $submissionIdentifier, ?string $dataFeedElement = null, ?int $submissionState = null,
        array $files = [], array $filesToDelete = []): Submission
    {
        $parameters = [];
        if ($dataFeedElement !== null) {
            $parameters['dataFeedElement'] = $dataFeedElement;
        }
        if ($submissionState !== null) {
            $parameters['submissionState'] = $submissionState;
        }
        foreach ($filesToDelete as $fileIdentifier) {
            $parameters['submittedFiles['.$fileIdentifier.']'] = 'null';
        }
        $request = Request::create('http://formalize.net/formalize/submissions/'.$submissionIdentifier, 'POST', $parameters,
            files: $files, server: ['CONTENT_TYPE' => 'multipart/form-data']);

        return $this->patchSubmissionController->__invoke($submissionIdentifier, $request);
    }
}
