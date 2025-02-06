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
    public const MAX_NUM_RESULTS_MAX = ResourceActionGrantService::MAX_NUM_RESULTS_MAX;
    /**
     * Form collection actions:
     */
    public const CREATE_FORMS_ACTION = 'create';

    /**
     * Form item actions:
     */
    public const READ_FORM_ACTION = 'read';
    public const UPDATE_FORM_ACTION = 'update';
    public const DELETE_FORM_ACTION = 'delete';
    public const CREATE_SUBMISSIONS_FORM_ACTION = 'create_submissions';
    public const READ_SUBMISSIONS_FORM_ACTION = 'read_submissions';
    public const UPDATE_SUBMISSIONS_FORM_ACTION = 'update_submissions';
    public const DELETE_SUBMISSIONS_FORM_ACTION = 'delete_submissions';

    /**
     * Submission item actions:
     */
    public const READ_SUBMISSION_ACTION = 'read';
    public const UPDATE_SUBMISSION_ACTION = 'update';
    public const DELETE_SUBMISSION_ACTION = 'delete';

    public const FORM_RESOURCE_CLASS = 'DbpRelayFormalizeForm';
    public const SUBMISSION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmission';

    /**
     * @var string[][]
     *
     * Caches granted form actions for the current user just for the current request to avoid requesting grants
     * from the authorization bundle multiple times for the same form
     */
    private array $grantedFormActionsCache = [];

    /**
     * @var string[][]
     *
     * Caches granted submission actions for the current user just for the current request to avoid requesting grants
     * from the authorization bundle multiple times for the same submission
     */
    private array $grantedSubmissionActionsCache = [];

    public function __construct(private readonly ResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();
    }

    /**
     * For unit testing only.
     */
    public function clearCaches(): void
    {
        $this->grantedFormActionsCache = [];
        $this->grantedSubmissionActionsCache = [];
    }

    /**
     * @return string[]
     */
    public function getGrantedFormItemActions(Form $form): array
    {
        return $this->getGrantedFormItemActionsInternal($form);
    }

    /**
     * @return string[]
     */
    public function getGrantedSubmissionItemActions(Submission $submission): array
    {
        return $this->getGrantedSubmissionItemActionsInternal($submission);
    }

    /**
     * @return string[][]
     */
    public function getGrantedFormActionsPage(array $whereActionsContainOneOf,
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_MAX): array
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
        return $this->isCurrentUserGrantedFormAction(self::UPDATE_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadForm(Form $form): bool
    {
        return $this->isCurrentUserGrantedFormAction(self::READ_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToDeleteForm(Form $form): bool
    {
        return $this->isCurrentUserGrantedFormAction(self::DELETE_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToCreateFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedFormAction(self::CREATE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToDeleteFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedFormAction(self::DELETE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToUpdateFormSubmissions(Form $form): bool
    {
        // use update form permission for now
        return $this->isCurrentUserGrantedFormAction(self::UPDATE_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedFormAction(self::READ_SUBMISSIONS_FORM_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadSubmission(Submission $submission): bool
    {
        return $this->isCurrentUserGrantedSubmissionsFormAction(self::READ_SUBMISSIONS_FORM_ACTION, $submission)
            || $this->isCurrentUserGrantedSubmissionAction(self::READ_SUBMISSION_ACTION, $submission);
    }

    public function isCurrentUserAuthorizedToUpdateSubmission(Submission $submission): bool
    {
        return $this->isCurrentUserGrantedSubmissionsFormAction(self::UPDATE_SUBMISSIONS_FORM_ACTION, $submission)
            || $this->isCurrentUserGrantedSubmissionAction(self::UPDATE_SUBMISSION_ACTION, $submission);
    }

    public function isCurrentUserAuthorizedToDeleteSubmission(Submission $submission): bool
    {
        return $this->isCurrentUserGrantedSubmissionsFormAction(self::DELETE_SUBMISSIONS_FORM_ACTION, $submission)
            || $this->isCurrentUserGrantedSubmissionAction(self::DELETE_SUBMISSION_ACTION, $submission);
    }

    /**
     * @return string[]
     */
    public function getSubmissionIdentifiersCurrentUserIsAuthorizedToRead(
        int $firstResultIndex = 0, int $maxNumResults = self::MAX_NUM_RESULTS_MAX): array
    {
        // TODO: re-work this
        return array_keys($this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
            self::SUBMISSION_RESOURCE_CLASS,
            [ResourceActionGrantService::MANAGE_ACTION, self::READ_SUBMISSION_ACTION], $firstResultIndex, $maxNumResults));
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
        $this->grantedFormActionsCache[$form->getIdentifier()] = [ResourceActionGrantService::MANAGE_ACTION];
    }

    /**
     * @throws ApiError
     */
    public function deregisterForm(Form $form): void
    {
        $this->resourceActionGrantService->deregisterResource(self::FORM_RESOURCE_CLASS, $form->getIdentifier());
        unset($this->grantedFormActionsCache[$form->getIdentifier()]);
    }

    /**
     * @throws ApiError
     */
    public function registerSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->registerResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
        $this->grantedSubmissionActionsCache[$submission->getIdentifier()] = [ResourceActionGrantService::MANAGE_ACTION];
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmission(Submission $submission): void
    {
        $this->resourceActionGrantService->deregisterResource(self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
        unset($this->grantedSubmissionActionsCache[$submission->getIdentifier()]);
    }

    /**
     * @throws ApiError
     */
    public function deregisterSubmissionsByIdentifier(mixed $formSubmissionIdentifiers): void
    {
        $this->resourceActionGrantService->deregisterResources(self::SUBMISSION_RESOURCE_CLASS, $formSubmissionIdentifiers);
        $this->grantedSubmissionActionsCache = [];
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

    /**
     * @return string[]
     */
    private function getGrantedSubmissionItemActionsInternal(Submission $submission): array
    {
        if (($grantedSubmissionItemActions = $this->grantedSubmissionActionsCache[$submission->getIdentifier()] ?? null) === null) {
            $grantedSubmissionItemActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
                self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
            $this->grantedSubmissionActionsCache[$submission->getIdentifier()] = $grantedSubmissionItemActions;
        }

        return $grantedSubmissionItemActions;
    }

    private function isCurrentUserGrantedSubmissionsFormAction(string $action, Submission $submission): bool
    {
        return $submission->isSubmitted() // drafts may only be accessed by user with submission level permissions
            && $this->isCurrentUserGrantedFormAction($action, $submission->getForm());
    }

    private function isCurrentUserGrantedSubmissionAction(string $action, Submission $submission): bool
    {
        $form = $submission->getForm();
        if ($submission->isSubmitted()) {
            if (false === $form->isAllowedSubmissionActionWhenSubmitted($action)) {
                return false;
            }
        }

        if ($form->getGrantBasedSubmissionAuthorization()) {
            return $this->doesCurrentUserHaveGrantForSubmission($action, $submission);
        } else {
            return $submission->getCreatorId() === $this->getUserIdentifier();
        }
    }

    private function isCurrentUserGrantedFormAction(string $action, Form $form): bool
    {
        return !empty(
            array_intersect($this->getGrantedFormItemActionsInternal($form), [ResourceActionGrantService::MANAGE_ACTION, $action]));
    }

    private function doesCurrentUserHaveGrantForSubmission(string $action, Submission $submission): bool
    {
        return !empty(
            array_intersect($this->getGrantedSubmissionItemActionsInternal($submission), [ResourceActionGrantService::MANAGE_ACTION, $action]));
    }
}
