<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;
use Dbp\Relay\FormalizeBundle\Tests\TestEntityManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class RestTestCase extends AbstractTestCase
{
    protected const TEST_FORM_NAME = TestEntityManager::DEFAULT_FORM_NAME;

    protected static function createRequestStack(string $uri = '/formalize/forms/', string $method = 'GET'): RequestStack
    {
        $request = Request::create($uri, $method);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    protected static function getUserAttributeName(?string $formIdentifier, string $action): string
    {
        return 'DbpRelayFormalize/forms'.($formIdentifier !== null ? '/'.$formIdentifier : '').'.'.$action;
    }

    protected static function createUploadedTestFile(string $path = self::TEXT_FILE_PATH): UploadedFile
    {
        return new UploadedFile($path, basename($path));
    }

    protected function addForm(string $name = self::TEST_FORM_NAME, ?string $dataFeedSchema = null,
        bool $grantBasedSubmissionAuthorization = false, ?int $allowedSubmissionStates = null,
        ?array $actionsAllowedWhenSubmitted = null): Form
    {
        return $this->testEntityManager->addForm($name,
            dataFeedSchema: $dataFeedSchema,
            grantBasedSubmissionAuthorization: $grantBasedSubmissionAuthorization,
            allowedSubmissionStates: $allowedSubmissionStates,
            actionsAllowedWhenSubmitted: $actionsAllowedWhenSubmitted);
    }

    protected function getForm(string $identifier): ?Form
    {
        return $this->testEntityManager->getForm($identifier);
    }

    protected function addSubmission(?Form $form = null, ?string $dataFeedElement = '{}',
        ?int $submissionState = null, ?string $creatorId = null): Submission
    {
        return $this->testEntityManager->addSubmission($form, $dataFeedElement,
            submissionState: $submissionState,
            creatorId: $creatorId ?? $this->authorizationService->getUserIdentifier());
    }

    protected function getSubmission(string $identifier): ?Submission
    {
        return $this->testEntityManager->getSubmission($identifier);
    }
}
