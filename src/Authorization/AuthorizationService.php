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

    public const MANAGE_ACTION = ResourceActionGrantService::MANAGE_ACTION;

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

    public const FORM_ITEM_ACTIONS = [
        self::READ_FORM_ACTION,
        self::UPDATE_FORM_ACTION,
        self::DELETE_FORM_ACTION,
        self::CREATE_SUBMISSIONS_FORM_ACTION,
        self::READ_SUBMISSIONS_FORM_ACTION,
        self::UPDATE_SUBMISSIONS_FORM_ACTION,
        self::DELETE_SUBMISSIONS_FORM_ACTION,
    ];

    /**
     * Submission item actions:
     */
    public const READ_SUBMISSION_ACTION = 'read';
    public const UPDATE_SUBMISSION_ACTION = 'update';
    public const DELETE_SUBMISSION_ACTION = 'delete';

    public const SUBMISSION_ITEM_ACTIONS = [
        self::READ_SUBMISSION_ACTION,
        self::UPDATE_SUBMISSION_ACTION,
        self::DELETE_SUBMISSION_ACTION,
    ];

    public const FORM_RESOURCE_CLASS = 'DbpRelayFormalizeForm';
    public const SUBMISSION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmission';

    private const SUBMISSION_ITEM_ACTIONS_INCLUDING_MANAGE = [...self::SUBMISSION_ITEM_ACTIONS, self::MANAGE_ACTION];

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

    public function __construct(
        private readonly ResourceActionGrantService $resourceActionGrantService,
        private bool $debug = false)
    {
        parent::__construct();
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
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
        return $this->getGrantedFormItemActionsCached($form);
    }

    /**
     * Returns the list of granted submission item actions that derive from the granted actions
     * of the current user for the form.
     *
     * @return string[]
     */
    public function getGrantedSubmissionItemActionsFormLevel(Form $form): array
    {
        $grantedSubmissionItemActions = [];
        foreach ($this->getGrantedFormItemActionsCached($form) as $grantedFormItemAction) {
            // NOTE: As an authz design decision, we grant manage submissions permissions to form managers
            // i.e. they can also re-share submissions (this might be subject to further discussion)
            if ($grantedFormItemAction === self::MANAGE_ACTION) {
                $grantedSubmissionItemActions = [self::MANAGE_ACTION];
                break;
            }
            if ($correspondingSubmissionAction =
                self::tryGetCorrespondingSubmissionItemAction($grantedFormItemAction)) {
                $grantedSubmissionItemActions[] = $correspondingSubmissionAction;
            }
        }

        return $grantedSubmissionItemActions;
    }

    /**
     * @return string[]
     */
    public function getGrantedSubmissionItemActions(Submission $submission,
        ?array $submissionItemActionsCurrentUserHasAGrantFor = null): array
    {
        return $this->getGrantedSubmissionItemActionsCached($submission,
            $submissionItemActionsCurrentUserHasAGrantFor);
    }

    /**
     * If $firstResultIndex is 0 and $maxNumResults null, all results are returned.
     *
     * @return string[][]
     */
    public function getGrantedFormItemActionsCollection(string $whereIsGrantedAction,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        if ($firstResultIndex === 0 && $maxNumResults === null) {
            $currentPageStartIndex = 0;
            $maxNumItemsPerPage = 1024;
            $resultItems = [];
            do {
                $pageItems = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
                    self::FORM_RESOURCE_CLASS, $whereIsGrantedAction,
                    $currentPageStartIndex, $maxNumItemsPerPage);
                $resultItems = array_merge($resultItems, $pageItems);
                $currentPageStartIndex += $maxNumItemsPerPage;
            } while (count($pageItems) >= $maxNumItemsPerPage);

            return $resultItems;
        } else {
            return $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(self::FORM_RESOURCE_CLASS,
                $whereIsGrantedAction, $firstResultIndex, $maxNumResults ?? self::MAX_NUM_RESULTS_MAX);
        }
    }

    public function isCurrentUserAuthorizedToCreateForms(): bool
    {
        return $this->resourceActionGrantService->isCurrentUserGrantedCollectionAction(
            self::FORM_RESOURCE_CLASS, self::CREATE_FORMS_ACTION);
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
        return $this->isCurrentUserGrantedSubmissionAction(self::READ_SUBMISSION_ACTION, $submission);
    }

    public function isCurrentUserAuthorizedToUpdateSubmission(Submission $submission): bool
    {
        return $this->isCurrentUserGrantedSubmissionAction(self::UPDATE_SUBMISSION_ACTION, $submission);
    }

    public function isCurrentUserAuthorizedToDeleteSubmission(Submission $submission): bool
    {
        return $this->isCurrentUserGrantedSubmissionAction(self::DELETE_SUBMISSION_ACTION, $submission);
    }

    /**
     * Returns a mapping of submission identifiers to the submission actions that the current user has
     * (submission-level) grants for, where the granted actions contain a read grant.
     *
     * @return array[] Array key: submission identifier Array value: Set of actions the current user has grants for
     */
    public function getGrantedSubmissionItemActionCollectionCurrentUserHasAReadGrantFor(): array
    {
        return $this->getGrantedSubmissionItemActionCollection(self::READ_SUBMISSION_ACTION);
    }

    /**
     * Returns a mapping of submission identifiers to the submission actions that the current user has
     * (submission-level) grants for, where the granted actions contain a read grant.
     *
     * @return array[] Array key: submission identifier Array value: Set of actions the current user has grants for
     */
    public function getGrantedSubmissionItemActionCollection(?string $whereIsGrantedAction = null): array
    {
        $submissionItemActions = [];
        $currentPageStartIndex = 0;
        do {
            $submissionItemActionsPage =
                $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
                    self::SUBMISSION_RESOURCE_CLASS,
                    $whereIsGrantedAction,
                    $currentPageStartIndex,
                    AuthorizationService::MAX_NUM_RESULTS_MAX);

            $submissionItemActions = array_merge(
                $submissionItemActions,
                $submissionItemActionsPage);
            $currentPageStartIndex += AuthorizationService::MAX_NUM_RESULTS_MAX;
        } while (count($submissionItemActionsPage) === AuthorizationService::MAX_NUM_RESULTS_MAX);

        return $submissionItemActions;
    }

    /**
     * @throws ApiError
     */
    public function registerForm(Form $form, ?string $formManagerUserIdentifier = null): void
    {
        $this->resourceActionGrantService->registerResource(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier(), $formManagerUserIdentifier);
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
        $this->grantedSubmissionActionsCache[$submission->getIdentifier()] =
            $this->getGrantedSubmissionItemActionsInternal($submission, [self::MANAGE_ACTION]);
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
    private function getGrantedFormItemActionsCached(Form $form): array
    {
        if (($grantedFormItemActions = $this->grantedFormActionsCache[$form->getIdentifier()] ?? null) === null) {
            $grantedFormItemActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
                self::FORM_RESOURCE_CLASS, $form->getIdentifier());
            if (in_array(self::MANAGE_ACTION, $grantedFormItemActions, true)) {
                // manage action implies all others. So if granted, remove all others:
                $grantedFormItemActions = [self::MANAGE_ACTION];
            }
            $this->grantedFormActionsCache[$form->getIdentifier()] = $grantedFormItemActions;
        }

        return $grantedFormItemActions;
    }

    /**
     * @return string[]
     */
    private function getGrantedSubmissionItemActionsCached(
        Submission $submission, ?array $submissionItemActionsCurrentUserHasAGrantFor = null): array
    {
        if (($grantedSubmissionItemActions =
                $this->grantedSubmissionActionsCache[$submission->getIdentifier()] ?? null) === null) {
            $grantedSubmissionItemActions = $this->getGrantedSubmissionItemActionsInternal(
                $submission, $submissionItemActionsCurrentUserHasAGrantFor);
            $this->grantedSubmissionActionsCache[$submission->getIdentifier()] = $grantedSubmissionItemActions;
        }

        return $grantedSubmissionItemActions;
    }

    /**
     * @param string[]|null $submissionItemActionsCurrentUserHasAGrantFor
     *
     * @return string[]
     */
    private function getGrantedSubmissionItemActionsInternal(Submission $submission,
        ?array $submissionItemActionsCurrentUserHasAGrantFor = null): array
    {
        // drafts may only be accessed by user with submission level permissions
        $grantedSubmissionItemActions = $submission->isDraft() ?
            [] : $this->getGrantedSubmissionItemActionsFormLevel($submission->getForm());

        // if we already have manage submission rights, ther is nothing to win
        if ($grantedSubmissionItemActions !== [self::MANAGE_ACTION]) {
            $grantedSubmissionItemActionsSubmissionLevel =
                $this->getGrantedSubmissionItemActionsSubmissionLevel(
                    $submission, $submissionItemActionsCurrentUserHasAGrantFor);
            if (in_array(self::MANAGE_ACTION, $grantedSubmissionItemActionsSubmissionLevel, true)) {
                $grantedSubmissionItemActions = [self::MANAGE_ACTION];
            } else {
                $grantedSubmissionItemActions = array_merge($grantedSubmissionItemActions,
                    $grantedSubmissionItemActionsSubmissionLevel);
            }
        }

        return $grantedSubmissionItemActions;
    }

    /**
     * @param string[]|null $submissionItemActionsCurrentUserHasAGrantFor
     *
     * @return string[]
     */
    public function getGrantedSubmissionItemActionsSubmissionLevel(Submission $submission,
        ?array $submissionItemActionsCurrentUserHasAGrantFor = null): array
    {
        if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
            $grantedSubmissionItemActionsSubmissionLevel = $submissionItemActionsCurrentUserHasAGrantFor ??
                $this->getSubmissionItemActionsCurrentUserHasAGrantFor($submission);
            if (in_array(self::MANAGE_ACTION, $grantedSubmissionItemActionsSubmissionLevel, true)) {
                // manage action implies all others. So if granted, remove all others:
                $grantedSubmissionItemActionsSubmissionLevel = [self::MANAGE_ACTION];
            }
            if ($submission->isSubmitted()) {
                // in case just one of the arrays contains the manage action,
                // we need to add all actions implied by the manage action beforehand in order not to lose any actions
                $doIntersect = true;
                $allowedActionsWhenSubmitted = $submission->getForm()->getAllowedActionsWhenSubmitted();
                if ($grantedSubmissionItemActionsSubmissionLevel === [self::MANAGE_ACTION]) {
                    if ($allowedActionsWhenSubmitted === [self::MANAGE_ACTION]) {
                        $doIntersect = false; // result is [self::MANAGE_ACTION]
                    } else {
                        $grantedSubmissionItemActionsSubmissionLevel = self::SUBMISSION_ITEM_ACTIONS_INCLUDING_MANAGE;
                    }
                } elseif ($allowedActionsWhenSubmitted === [self::MANAGE_ACTION]) {
                    $allowedActionsWhenSubmitted = self::SUBMISSION_ITEM_ACTIONS_INCLUDING_MANAGE;
                }
                if ($doIntersect) {
                    $grantedSubmissionItemActionsSubmissionLevel = array_intersect(
                        $grantedSubmissionItemActionsSubmissionLevel,
                        $allowedActionsWhenSubmitted
                    );
                }
            }
        } elseif ($this->getUserIdentifier() === $submission->getCreatorId()) { // creator-based submission authorization
            $grantedSubmissionItemActionsSubmissionLevel = match ($submission->getSubmissionState()) {
                Submission::SUBMISSION_STATE_DRAFT => self::SUBMISSION_ITEM_ACTIONS,
                Submission::SUBMISSION_STATE_SUBMITTED => $submission->getForm()->getAllowedActionsWhenSubmitted(),
                default => [],
            };
        } else {
            $grantedSubmissionItemActionsSubmissionLevel = [];
        }

        return $grantedSubmissionItemActionsSubmissionLevel;
    }

    private function isCurrentUserGrantedFormASubmissionsAction(string $action, Submission $submission): bool
    {
        return $submission->isSubmitted() // drafts may only be accessed by user with submission level permissions
            && $this->isCurrentUserGrantedFormAction($action, $submission->getForm());
    }

    private function isCurrentUserGrantedSubmissionAction(string $action, Submission $submission): bool
    {
        $grantedSubmissionItemActions = $this->getGrantedSubmissionItemActionsCached($submission);

        return in_array(self::MANAGE_ACTION, $grantedSubmissionItemActions, true)
            || in_array($action, $grantedSubmissionItemActions, true);
    }

    private function isCurrentUserGrantedFormAction(string $action, Form $form): bool
    {
        $grantedFormItemActions = $this->getGrantedFormItemActionsCached($form);

        return in_array(self::MANAGE_ACTION, $grantedFormItemActions, true)
            || in_array($action, $grantedFormItemActions, true);
    }

    private function doesCurrentUserHaveGrantForSubmission(string $action, Submission $submission): bool
    {
        return !empty(
            array_intersect(
                $this->getSubmissionItemActionsCurrentUserHasAGrantFor($submission),
                [ResourceActionGrantService::MANAGE_ACTION, $action]
            )
        );
    }

    /**
     * @return string[]
     */
    private function getSubmissionItemActionsCurrentUserHasAGrantFor(Submission $submission): array
    {
        return $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    private static function tryGetCorrespondingSubmissionItemAction(string $formSubmissionsAction): ?string
    {
        return match ($formSubmissionsAction) {
            self::READ_SUBMISSIONS_FORM_ACTION => self::READ_SUBMISSION_ACTION,
            self::UPDATE_SUBMISSIONS_FORM_ACTION => self::UPDATE_SUBMISSION_ACTION,
            self::DELETE_SUBMISSIONS_FORM_ACTION => self::DELETE_SUBMISSION_ACTION,
            default => null,
        };
    }
}
