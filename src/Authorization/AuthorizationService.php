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

    /**
     * @var string[][]
     *
     * Caches granted form actions for the current user just for the current request to avoid duplicate requests
     * for authorization check and getting the granted actions for the response
     */
    private array $grantedFormActionsCache = [];

    public function __construct(private readonly ResourceActionGrantService $resourceActionGrantService)
    {
    }

    /**
     * For unit testing only.
     */
    public function clearCaches(): void
    {
        $this->grantedFormActionsCache = [];
    }

    /**
     * @return string[]
     */
    public function getGrantedFormItemActions(Form $form): array
    {
        return $this->getGrantedFormItemActionsInternal($form);
    }

    /**
     * @return string[][]
     */
    public function getGrantedFormActionsPage(array $whereActionsContainOneOf, int $firstResultIndex, int $maxNumResults): array
    {
        return $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(self::FORM_RESOURCE_CLASS,
            $whereActionsContainOneOf, $firstResultIndex, $maxNumResults);
    }

    public function isCurrentUserAuthorizedToCreateForms(): bool
    {
        return $this->resourceActionGrantService->isCurrentUserGrantedAnyOfCollectionActions(
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
    public function getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(int $firstResultIndex, int $maxNumResults): array
    {
        return array_keys($this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::SUBMISSION_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION], $firstResultIndex, $maxNumResults));
    }

    /**
     * @return string[]
     */
    public function getFormIdentifiersCurrentUserIsAuthorizedToReadSubmissionsOf(int $firstResultIndex, int $maxNumResults): array
    {
        return array_keys($this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
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
        if (isset($this->grantedFormActionsCache[$form->getIdentifier()])) {
            unset($this->grantedFormActionsCache[$form->getIdentifier()]);
        }
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

    /**
     * @return string[]
     */
    private function getGrantedFormItemActionsInternal(Form $form): array
    {
        if (($grantedFormItemActions = $this->grantedFormActionsCache[$form->getIdentifier()] ?? null) === null) {
            $grantedFormItemActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
                self::FORM_RESOURCE_CLASS, $form->getIdentifier());
            $this->grantedFormActionsCache[$form->getIdentifier()] = $grantedFormItemActions;
        }

        return $grantedFormItemActions;
    }

    private function isCurrentUserAuthorizedToManageOr(string $action, Form $form): bool
    {
        return !empty(
            array_intersect($this->getGrantedFormItemActionsInternal($form), [ResourceActionGrantService::MANAGE_ACTION, $action]));
    }
}
