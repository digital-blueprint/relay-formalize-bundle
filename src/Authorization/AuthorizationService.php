<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Authorization;

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
        $formActions = $this->resourceActionGrantService->getGrantedResourceItemActions(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier());

        return $formActions !== null ? $formActions->getActions() : [];
    }

    public function isCurrentUserAuthorizedToCreateForms(): bool
    {
        return $this->resourceActionGrantService->hasGrantedResourceCollectionActions(
            self::FORM_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION, self::CREATE_FORMS_ACTION]);
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
        return array_map(function ($resourceActions) {
            return $resourceActions->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActionsPage(
            self::FORM_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION, self::READ_FORM_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @return string[]
     */
    public function getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults): array
    {
        return array_map(function ($resourceActions) {
            return $resourceActions->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActionsPage(
            self::SUBMISSION_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @return string[]
     */
    public function getFormIdentifiersCurrentUserIsAuthorizedToReadSubmissionsOf(int $firstResultIndex, int $maxNumResults): array
    {
        return array_map(function ($resourceActions) {
            return $resourceActions->getResourceIdentifier();
        }, $this->resourceActionGrantService->getGrantedResourceItemActionsPage(
            self::FORM_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION, self::READ_SUBMISSIONS_FORM_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @throws ApiError
     */
    public function registerForm(Form $form): void
    {
        $this->resourceActionGrantService->registerResource(self::FORM_RESOURCE_CLASS, $form->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterForm(Form $form): void
    {
        $this->resourceActionGrantService->deregisterResource(self::FORM_RESOURCE_CLASS, $form->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function registerSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->registerResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->deregisterResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmissionsByIdentifier(mixed $formSubmissionIdentifiers): void
    {
        $this->resourceActionGrantService->deregisterResources(self::SUBMISSION_RESOURCE_CLASS, $formSubmissionIdentifiers);
    }

    private function isCurrentUserAuthorizedToManageOr(string $action, Form $form): bool
    {
        return $this->resourceActionGrantService->hasGrantedResourceItemActions(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            [ResourceActionGrantService::MANAGE_ACTION, $action]);
    }
}
