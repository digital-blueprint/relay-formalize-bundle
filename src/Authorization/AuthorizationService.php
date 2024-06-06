<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceAction;
use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;

class AuthorizationService extends AbstractAuthorizationService
{
    public const CREATE_FORMS_ACTION = 'create';
    public const READ_FORM_ACTION = 'read';
    public const UPDATE_FORM_ACTION = 'update';
    public const DELETE_FORM_ACTION = 'delete';
    public const CREATE_SUBMISSIONS_FORM_ACTION = 'create_submissions';
    public const READ_SUBMISSIONS_FORM_ACTION = 'read_submissions';
    public const UPDATE_SUBMISSIONS_FORM_ACTION = 'update_submissions';
    public const DELETE_SUBMISSIONS_FORM_ACTION = 'delete_submissions';
    public const FORM_RESOURCE_CLASS = 'DbpRelayFormalizeForm';
    public const SUBMISSION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmission';

    private ResourceActionGrantService $resourceActionGrantService;

    public function __construct(ResourceActionGrantService $resourceActionGrantService)
    {
        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    /**
     * For unit testing only.
     */
    public function setResourceActionGrantService(ResourceActionGrantService $resourceActionGrantService): void
    {
        $this->resourceActionGrantService = $resourceActionGrantService;
    }

    /**
     * @param Form|null $form if null, the Form collection actions are returned
     *
     * @return string[]
     */
    public function getFormActionsCurrentUserIsAuthorizedToPerform(?Form $form): array
    {
        return self::extractActions(
            $this->resourceActionGrantService->getGrantedResourceItemActions(
                self::FORM_RESOURCE_CLASS, $form->getIdentifier(), null));
    }

    public function isCurrentUserAuthorizedToCreateForms(): bool
    {
        return count($this->resourceActionGrantService->getGrantedResourceCollectionActions(
            self::FORM_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION, self::CREATE_FORMS_ACTION], 0, 1)) > 0;
    }

    public function isCurrentUserAuthorizedToUpdateForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::UPDATE_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::READ_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToDeleteForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::DELETE_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToCreateFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::CREATE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToDeleteFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::DELETE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToUpdateFormSubmissions(Form $form): bool
    {
        // use update form permission for now
        return $this->isCurrentUserAuthorizedToManageOr(self::UPDATE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserAuthorizedToManageOr(self::READ_SUBMISSIONS_FORM_ACTION, $form);
    }

    /**
     * @return string[]
     */
    public function getFormIdentifiersCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults): array
    {
        return array_map(function ($resourceAction) {
            return $resourceAction->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActions(
            self::FORM_RESOURCE_CLASS, null,
            [ResourceActionGrantService::MANAGE_ACTION, self::READ_FORM_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @return string[]
     */
    public function getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults): array
    {
        return array_map(function ($resourceAction) {
            return $resourceAction->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActions(
            self::SUBMISSION_RESOURCE_CLASS, null,
            [ResourceActionGrantService::MANAGE_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @return string[]
     */
    public function getFormIdentifiersCurrentUserIsAuthorizedToReadSubmissionsOf(int $firstResultIndex, int $maxNumResults): array
    {
        return array_map(function ($resourceAction) {
            return $resourceAction->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActions(
            self::FORM_RESOURCE_CLASS, null,
            [ResourceActionGrantService::MANAGE_ACTION, self::READ_SUBMISSIONS_FORM_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @throws ApiError
     */
    public function registerForm(Form $form): void
    {
        $this->resourceActionGrantService->addResource(self::FORM_RESOURCE_CLASS, $form->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterForm(Form $form): void
    {
        $this->resourceActionGrantService->removeResource(self::FORM_RESOURCE_CLASS, $form->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function registerSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->addResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->removeResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmissionsByIdentifier(mixed $formSubmissionIdentifiers): void
    {
        $this->resourceActionGrantService->removeResources(self::SUBMISSION_RESOURCE_CLASS, $formSubmissionIdentifiers);
    }

    /**
     * @param ResourceAction[] $resourceActions
     *
     * @return string[]
     */
    private static function extractActions(array $resourceActions): array
    {
        return array_map(function (ResourceAction $resourceAction) {
            return $resourceAction->getAction();
        }, $resourceActions);
    }

    private function isCurrentUserAuthorizedToManageOr(string $action, Form $form): bool
    {
        return count($this->resourceActionGrantService->getGrantedResourceItemActions(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION, $action], 0, 1)) > 0;
    }
}
