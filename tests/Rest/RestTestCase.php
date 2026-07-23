<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
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

    protected function addForm(string $name = self::TEST_FORM_NAME,
        ?string $dataFeedSchema = null,
        ?int $allowedSubmissionStates = null,
        ?string $roleIdentifierWhenSubmitted = AuthorizationService::READER_ROLE_IDENTIFIER,
        ?string $roleIdentifierWhenDraft = AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER,
        ?array $availableTags = AbstractTestCase::TEST_AVAILABLE_TAGS): Form
    {
        $form = $this->testEntityManager->addForm($name,
            dataFeedSchema: $dataFeedSchema,
            allowedSubmissionStates: $allowedSubmissionStates,
            roleIdentifierWhenSubmitted: $roleIdentifierWhenSubmitted,
            roleIdentifierWhenDraft: $roleIdentifierWhenDraft,
            availableTags: $availableTags);

        $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS,
            $form->getIdentifier(),
            ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE
        );

        return $form;
    }

    protected function getForm(string $identifier): ?Form
    {
        return $this->testEntityManager->getForm($identifier);
    }

    protected function addSubmission(?Form $form = null, ?string $dataFeedElement = '{}',
        ?int $submissionState = null, ?array $tags = null, ?string $creatorId = null): Submission
    {
        $currentUserIdentifier = $creatorId ?? $this->authorizationService->getUserIdentifier();

        $submission = $this->testEntityManager->addSubmission($form, $dataFeedElement,
            submissionState: $submissionState,
            tags: $tags,
            creatorId: $currentUserIdentifier
        );

        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS,
            $submission->getIdentifier(),
        );
        switch ($submission->getSubmissionState()) {
            case Submission::SUBMISSION_STATE_SUBMITTED:
                $this->authorizationTestEntityManager->addResourceToResourceGroup(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS,
                    $form->getIdentifier(),
                    $submission->getIdentifier()
                );
                if ($roleIdentifier = $form?->getRoleIdentifierWhenSubmitted()) {
                    $this->authorizationTestEntityManager->addResourceActionGrant(
                        $authorizationResource,
                        userIdentifier: $currentUserIdentifier,
                        roleIdentifier: $roleIdentifier
                    );
                }
                break;

            case Submission::SUBMISSION_STATE_DRAFT:
                $this->authorizationTestEntityManager->addResourceActionGrant(
                    $authorizationResource,
                    userIdentifier: $currentUserIdentifier,
                    roleIdentifier: $form?->getRoleIdentifierWhenDraft()
                );
                break;

            default:
                throw new \InvalidArgumentException('Invalid submission state: '.$submission->getSubmissionState());
        }

        return $submission;
    }

    protected function getSubmission(string $identifier): ?Submission
    {
        return $this->testEntityManager->getSubmission($identifier);
    }
}
