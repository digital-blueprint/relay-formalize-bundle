<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\Serializer\EntityNormalizer;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Contracts\Service\ResetInterface;

class AuthorizationService extends AbstractAuthorizationService implements ResetInterface
{
    public const MAX_NUM_RESULTS_MAX = ResourceActionGrantService::MAX_NUM_RESULTS_MAX;

    public const MANAGE_ACTION = ResourceActionGrantService::MANAGE_ACTION;

    /**
     * Form item actions:
     */
    public const READ_FORM_ACTION = 'read';
    public const UPDATE_FORM_ACTION = 'update';
    public const DELETE_FORM_ACTION = 'delete';

    public const FORM_ITEM_ACTIONS = [
        self::READ_FORM_ACTION,
        self::UPDATE_FORM_ACTION,
        self::DELETE_FORM_ACTION,
    ];

    /**
     * Form collection actions:
     */
    public const CREATE_FORMS_ACTION = 'create';

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

    /**
     * Submission collection actions:
     *
     * Note that collection actions and item actions have the same name so that resource grouping (grant inheritance)
     * works in the authorization bundle.
     */
    public const CREATE_SUBMISSIONS_ACTION = 'create_submissions';
    public const READ_SUBMISSIONS_ACTION = self::READ_SUBMISSION_ACTION;
    public const UPDATE_SUBMISSIONS_ACTION = self::UPDATE_SUBMISSION_ACTION;
    public const DELETE_SUBMISSIONS_ACTION = self::DELETE_SUBMISSION_ACTION;

    public const SUBMISSION_COLLECTION_ACTIONS = [
        self::CREATE_SUBMISSIONS_ACTION,
        self::READ_SUBMISSIONS_ACTION,
        self::UPDATE_SUBMISSIONS_ACTION,
        self::DELETE_SUBMISSIONS_ACTION,
    ];

    public const AVAILABLE_FORM_ITEM_ACTIONS = [
        AuthorizationService::READ_FORM_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        AuthorizationService::UPDATE_FORM_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        AuthorizationService::DELETE_FORM_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
    ];

    public const AVAILABLE_FORM_COLLECTION_ACTIONS = [
        AuthorizationService::CREATE_FORMS_ACTION => [
            'en' => 'Create',
            'de' => 'Erstellen',
        ],
    ];

    public const AVAILABLE_SUBMISSION_ITEM_ACTIONS = [
        AuthorizationService::READ_SUBMISSION_ACTION => [
            'en' => 'Read',
            'de' => 'Lesen',
        ],
        AuthorizationService::UPDATE_SUBMISSION_ACTION => [
            'en' => 'Update',
            'de' => 'Aktualisieren',
        ],
        AuthorizationService::DELETE_SUBMISSION_ACTION => [
            'en' => 'Delete',
            'de' => 'Löschen',
        ],
    ];
    public const AVAILABLE_SUBMISSION_COLLECTION_ACTIONS = [];

    public const AVAILABLE_SUBMISSION_COLLECTION_ITEM_ACTIONS = [
        AuthorizationService::CREATE_SUBMISSIONS_ACTION => [
            'en' => 'Create Submissions',
            'de' => 'Einreichungen erstellen',
        ],
        AuthorizationService::READ_SUBMISSION_ACTION => [
            'en' => 'Read Submissions',
            'de' => 'Einreichungen lesen',
        ],
        AuthorizationService::UPDATE_SUBMISSION_ACTION => [
            'en' => 'Update Submissions',
            'de' => 'Einreichungen aktualisieren',
        ],
        AuthorizationService::DELETE_SUBMISSION_ACTION => [
            'en' => 'Delete Submissions',
            'de' => 'Einreichungen löschen',
        ],
    ];

    public const AVAILABLE_SUBMISSION_COLLECTION_COLLECTION_ACTIONS = [];

    public const FORM_RESOURCE_CLASS = 'DbpRelayFormalizeForm';
    public const SUBMISSION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmission';
    public const SUBMISSION_COLLECTION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmissionCollection';

    /**
     * Tag actions (are not stored in the authorization bundle, but derived from granted form/submission actions):
     */
    public const READ_TAGS_ACTION = 'read_tags';
    public const ADD_TAGS_ACTION = 'add_tags';
    public const REMOVE_TAGS_ACTION = 'remove_tags';

    public const TAG_ACTIONS = [
        self::READ_TAGS_ACTION,
        self::ADD_TAGS_ACTION,
        self::REMOVE_TAGS_ACTION,
    ];

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
     * Caches granted (form) submission collection actions for the current user just for the current request to avoid requesting grants
     * from the authorization bundle multiple times for the same form
     */
    private array $grantedSubmissionCollectionActionsCache = [];

    /**
     * @var string[][]
     *
     * Caches granted submission actions for the current user just for the current request to avoid requesting grants
     * from the authorization bundle multiple times for the same submission
     */
    private array $grantedSubmissionActionsCache = [];

    public static function setAvailableResourceClassActions(ResourceActionGrantService $resourceActionGrantService): void
    {
        $resourceActionGrantService->setAvailableResourceClassActions(
            self::FORM_RESOURCE_CLASS,
            self::AVAILABLE_FORM_ITEM_ACTIONS,
            self::AVAILABLE_FORM_COLLECTION_ACTIONS
        );
        $resourceActionGrantService->setAvailableResourceClassActions(
            self::SUBMISSION_RESOURCE_CLASS,
            self::AVAILABLE_SUBMISSION_ITEM_ACTIONS,
            self::AVAILABLE_SUBMISSION_COLLECTION_ACTIONS
        );
        $resourceActionGrantService->setAvailableResourceClassActions(
            self::SUBMISSION_COLLECTION_RESOURCE_CLASS,
            self::AVAILABLE_SUBMISSION_COLLECTION_ITEM_ACTIONS,
            self::AVAILABLE_SUBMISSION_COLLECTION_COLLECTION_ACTIONS
        );
    }

    public function __construct(
        private readonly ResourceActionGrantService $resourceActionGrantService,
        private readonly EntityNormalizer $entityNormalizer,
        private bool $debug = false)
    {
        parent::__construct();
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * For testing only.
     */
    public function reset(): void
    {
        $this->grantedFormActionsCache = [];
        $this->grantedSubmissionActionsCache = [];
        $this->grantedSubmissionCollectionActionsCache = [];
        $this->entityNormalizer->reset();
    }

    /**
     * @return string[]
     */
    public function getGrantedFormItemActions(Form $form): array
    {
        return $this->getGrantedFormItemActionsCached($form);
    }

    /**
     * @return string[]
     */
    public function getGrantedSubmissionCollectionActions(Form $form): array
    {
        return $this->getGrantedSubmissionCollectionActionsCached($form);
    }

    /**
     * Returns the list of granted submission item actions that derive from the granted actions
     * of the current user for the form.
     *
     * @return string[]
     */
    public function getGrantedSubmissionItemActionsFormLevel(Form $form): array
    {
        $grantedSubmissionCollectionActions = $this->getGrantedSubmissionCollectionActionsCached($form);
        if (in_array(self::MANAGE_ACTION, $grantedSubmissionCollectionActions, true)) {
            // NOTE: As an authz design decision, we grant manage submissions permissions to form managers
            // i.e. they can also re-share submissions (this might be subject to further discussion)
            $grantedSubmissionItemActions = $form->getGrantBasedSubmissionAuthorization() ?
                [self::MANAGE_ACTION] : self::SUBMISSION_ITEM_ACTIONS;
        } else {
            $grantedSubmissionItemActions = array_intersect($grantedSubmissionCollectionActions, self::SUBMISSION_ITEM_ACTIONS);
        }

        return array_merge(
            $grantedSubmissionItemActions,
            $this->calculateFormLevelTagPermissions($grantedSubmissionCollectionActions));
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
    public function getGrantedFormItemActionsCollection(?string $whereIsGrantedAction,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        return $this->getGrantedItemActionsCollectionForCurrentUser(
            self::FORM_RESOURCE_CLASS, $whereIsGrantedAction, $firstResultIndex, $maxNumResults);
    }

    public function getGrantedSubmissionCollectionItemActionsCollection(?string $whereIsGrantedAction = null,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        return $this->getGrantedItemActionsCollectionForCurrentUser(
            self::SUBMISSION_COLLECTION_RESOURCE_CLASS, $whereIsGrantedAction, $firstResultIndex, $maxNumResults);
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
        return $this->isCurrentUserGrantedSubmissionCollectionAction(self::CREATE_SUBMISSIONS_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToDeleteFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedSubmissionCollectionAction(self::DELETE_SUBMISSIONS_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToUpdateFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedSubmissionCollectionAction(self::UPDATE_SUBMISSIONS_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedSubmissionCollectionAction(self::READ_SUBMISSIONS_ACTION, $form);
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
        $this->resourceActionGrantService->addResourceActionGrant(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $formManagerUserIdentifier
        );
        $this->grantedFormActionsCache[$form->getIdentifier()] = [ResourceActionGrantService::MANAGE_ACTION];

        $this->resourceActionGrantService->addResourceActionGrant(
            self::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $formManagerUserIdentifier
        );
        $this->grantedSubmissionCollectionActionsCache[$form->getIdentifier()] = [ResourceActionGrantService::MANAGE_ACTION];
    }

    /**
     * @throws ApiError
     */
    public function deregisterForm(Form $form): void
    {
        $this->resourceActionGrantService->removeGrantsForResource(
            self::FORM_RESOURCE_CLASS, $form->getIdentifier());
        unset($this->grantedFormActionsCache[$form->getIdentifier()]);
        $this->resourceActionGrantService->removeGrantsForResource(
            self::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier());
        unset($this->grantedSubmissionCollectionActionsCache[$form->getIdentifier()]);
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionAdded(Submission $submission): void
    {
        if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
            if ($submission->isDraft()) {
                $this->resourceActionGrantService->addResourceActionGrant(
                    self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
                    ResourceActionGrantService::MANAGE_ACTION, $this->getUserIdentifier());
                $this->grantedSubmissionActionsCache[$submission->getIdentifier()] =
                    $this->getGrantedSubmissionItemActionsInternal($submission, [self::MANAGE_ACTION]);
            }
        }
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionRemoved(string $identifier): void
    {
        $this->resourceActionGrantService->removeGrantsForResource(
            self::SUBMISSION_RESOURCE_CLASS, $identifier);
        unset($this->grantedSubmissionActionsCache[$identifier]);
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionsRemoved(array $submissionIdentifiers): void
    {
        $this->resourceActionGrantService->removeGrantsForResources(
            self::SUBMISSION_RESOURCE_CLASS, $submissionIdentifiers);
        // usually all form submissions are removed at once, so just clear the cache:
        $this->grantedSubmissionActionsCache = [];
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionSubmitted(Submission $submission, bool $wasDraft): void
    {
        if ($submission->getForm()->getGrantBasedSubmissionAuthorization()) {
            if ($wasDraft) { // submission was posted as a draft before
                // remove draft submission grants
                $this->resourceActionGrantService->removeGrantsForResource(
                    self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
            }
            $grantedSubmissionItemActions = $submission->getForm()->getAllowedActionsWhenSubmitted();
            foreach ($grantedSubmissionItemActions as $allowedAction) {
                $this->resourceActionGrantService->addResourceActionGrant(
                    self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier(),
                    $allowedAction, $this->getUserIdentifier());
            }
            $this->resourceActionGrantService->addResourceToGroupResource(
                self::SUBMISSION_COLLECTION_RESOURCE_CLASS, $submission->getForm()->getIdentifier(),
                self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());

            $this->grantedSubmissionActionsCache[$submission->getIdentifier()] =
                $this->getGrantedSubmissionItemActionsInternal($submission, $grantedSubmissionItemActions);
        }
    }

    public function showRestrictedFormSubmissionOrFormAttributesIfGranted(
        ?Form $formWhoseSubmissionAttributesToShow = null): void
    {
        if ($formWhoseSubmissionAttributesToShow !== null) { // submission request (set tags visibility)
            // since we always require a form for the GET submission collection request, i.e.,
            // all returned submissions are from the same form,
            // we can show this output group on submission class level for the request:
            $this->entityNormalizer->showOutputGroupsForEntityClassIf(
                Submission::class,
                ['FormalizeSubmission:output:tags'],
                function () use ($formWhoseSubmissionAttributesToShow): bool {
                    return
                        $formWhoseSubmissionAttributesToShow->getTagPermissionsForSubmitters() !== Form::TAG_PERMISSIONS_NONE
                        || $this->isCurrentUserAuthorizedToReadFormSubmissions($formWhoseSubmissionAttributesToShow);
                }
            );
        } else { // form request (set availableTags visibility)
            $this->entityNormalizer->showOutputGroupsForEntityInstanceIf(
                Form::class,
                ['FormalizeForm:output:availableTags'],
                function (Form $form): bool {
                    return
                        $form->getTagPermissionsForSubmitters() !== Form::TAG_PERMISSIONS_NONE
                        || $this->isCurrentUserAuthorizedToReadFormSubmissions($form)
                        || $this->isCurrentUserAuthorizedToUpdateForm($form);
                }
            );
        }
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

    public function getGrantedItemActionsCollectionForCurrentUser(string $resourceClass, ?string $whereIsGrantedAction,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        if ($firstResultIndex === 0 && $maxNumResults === null) { // gimme all
            $currentPageStartIndex = 0;
            $maxNumItemsPerPage = 1024;
            $resultItems = [];
            do {
                $pageItems = $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser(
                    $resourceClass, $whereIsGrantedAction,
                    $currentPageStartIndex, $maxNumItemsPerPage);
                $resultItems = array_merge($resultItems, $pageItems);
                $currentPageStartIndex += $maxNumItemsPerPage;
            } while (count($pageItems) >= $maxNumItemsPerPage);

            return $resultItems;
        }

        return $this->resourceActionGrantService->getGrantedItemActionsPageForCurrentUser($resourceClass,
            $whereIsGrantedAction, $firstResultIndex, $maxNumResults ?? self::MAX_NUM_RESULTS_MAX);
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
     * @return string[]
     */
    private function getGrantedSubmissionCollectionActionsCached(Form $form): array
    {
        if (($grantedSubmissionCollectionActions = $this->grantedSubmissionCollectionActionsCache[$form->getIdentifier()] ?? null) === null) {
            $grantedSubmissionCollectionActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
                self::SUBMISSION_COLLECTION_RESOURCE_CLASS, $form->getIdentifier());
            if (in_array(self::MANAGE_ACTION, $grantedSubmissionCollectionActions, true)) {
                // manage action implies all others. So if granted, remove all others:
                $grantedSubmissionCollectionActions = [self::MANAGE_ACTION];
            }
            $this->grantedSubmissionCollectionActionsCache[$form->getIdentifier()] = $grantedSubmissionCollectionActions;
        }

        return $grantedSubmissionCollectionActions;
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

        // if we already have manage submission rights, there is nothing to win
        // (also tag-permission-wise)
        if (false === in_array(self::MANAGE_ACTION, $grantedSubmissionItemActions, true)) {
            $grantedSubmissionItemActions = array_values(array_unique(
                array_merge(
                    $grantedSubmissionItemActions,
                    $this->getGrantedSubmissionItemActionsSubmissionLevel(
                        $submission, $submissionItemActionsCurrentUserHasAGrantFor
                    )
                )));
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
        } elseif ($this->getUserIdentifier() === $submission->getCreatorId()) { // creator-based submission authorization
            $grantedSubmissionItemActionsSubmissionLevel = match ($submission->getSubmissionState()) {
                Submission::SUBMISSION_STATE_DRAFT => self::SUBMISSION_ITEM_ACTIONS,
                Submission::SUBMISSION_STATE_SUBMITTED => $submission->getForm()->getAllowedActionsWhenSubmitted(),
                default => [],
            };
        } else {
            $grantedSubmissionItemActionsSubmissionLevel = [];
        }

        return array_merge(
            $grantedSubmissionItemActionsSubmissionLevel,
            $this->calculateSubmissionLevelTagPermissions(
                $submission->getForm(),
                $grantedSubmissionItemActionsSubmissionLevel));
    }

    private function isCurrentUserGrantedSubmissionAction(string $action, Submission $submission): bool
    {
        $grantedSubmissionItemActions = $this->getGrantedSubmissionItemActionsCached($submission);

        return in_array(self::MANAGE_ACTION, $grantedSubmissionItemActions, true)
            || in_array($action, $grantedSubmissionItemActions, true);
    }

    private function isCurrentUserGrantedSubmissionCollectionAction(string $action, Form $form): bool
    {
        $grantedSubmissionCollectionActions = $this->getGrantedSubmissionCollectionActionsCached($form);

        return in_array(self::MANAGE_ACTION, $grantedSubmissionCollectionActions, true)
            || in_array($action, $grantedSubmissionCollectionActions, true);
    }

    private function isCurrentUserGrantedFormAction(string $action, Form $form): bool
    {
        $grantedFormItemActions = $this->getGrantedFormItemActionsCached($form);

        return in_array(self::MANAGE_ACTION, $grantedFormItemActions, true)
            || in_array($action, $grantedFormItemActions, true);
    }

    /**
     * @return string[]
     */
    private function getSubmissionItemActionsCurrentUserHasAGrantFor(Submission $submission): array
    {
        return $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
    }

    private function calculateFormLevelTagPermissions(array $grantedSubmissionCollectionActions): array
    {
        $formLevelTagPermissions = [];
        if (in_array(AuthorizationService::UPDATE_SUBMISSIONS_ACTION, $grantedSubmissionCollectionActions, true)
            || in_array(ResourceActionGrantService::MANAGE_ACTION, $grantedSubmissionCollectionActions, true)) {
            $formLevelTagPermissions = [self::READ_TAGS_ACTION, self::ADD_TAGS_ACTION, self::REMOVE_TAGS_ACTION];
        } elseif (in_array(AuthorizationService::READ_SUBMISSIONS_ACTION, $grantedSubmissionCollectionActions, true)) {
            $formLevelTagPermissions = [self::READ_TAGS_ACTION];
        }

        return $formLevelTagPermissions;
    }

    private function calculateSubmissionLevelTagPermissions(Form $form, array $grantedSubmissionLevelActions): array
    {
        $maxTagPermissionsForSubmitters = match ($form->getTagPermissionsForSubmitters()) {
            Form::TAG_PERMISSIONS_NONE => [],
            Form::TAG_PERMISSIONS_READ => [self::READ_TAGS_ACTION],
            Form::TAG_PERMISSIONS_READ_ADD => [self::READ_TAGS_ACTION, self::ADD_TAGS_ACTION],
            Form::TAG_PERMISSIONS_READ_ADD_REMOVE => [self::READ_TAGS_ACTION, self::ADD_TAGS_ACTION, self::REMOVE_TAGS_ACTION],
        };

        $maxTagPermissionsForCurrentUser = [];
        if (in_array(AuthorizationService::MANAGE_ACTION, $grantedSubmissionLevelActions, true)) {
            $maxTagPermissionsForCurrentUser = [self::READ_TAGS_ACTION, self::ADD_TAGS_ACTION, self::REMOVE_TAGS_ACTION];
        } else {
            if (in_array(AuthorizationService::UPDATE_SUBMISSION_ACTION, $grantedSubmissionLevelActions, true)) {
                $maxTagPermissionsForCurrentUser[] = self::ADD_TAGS_ACTION;
                $maxTagPermissionsForCurrentUser[] = self::REMOVE_TAGS_ACTION;
            }
            if (in_array(AuthorizationService::READ_SUBMISSION_ACTION, $grantedSubmissionLevelActions, true)) {
                $maxTagPermissionsForCurrentUser[] = self::READ_TAGS_ACTION;
            }
        }

        return array_values(array_intersect($maxTagPermissionsForSubmitters, $maxTagPermissionsForCurrentUser));
    }

    private static function tryGetCorrespondingSubmissionItemAction(string $formSubmissionsAction): ?string
    {
        return match ($formSubmissionsAction) {
            self::READ_SUBMISSIONS_ACTION => self::READ_SUBMISSION_ACTION,
            self::UPDATE_SUBMISSIONS_ACTION => self::UPDATE_SUBMISSION_ACTION,
            self::DELETE_SUBMISSIONS_ACTION => self::DELETE_SUBMISSION_ACTION,
            default => null,
        };
    }

    private static function tryGetCorrespondingSubmissionCollectionAction(string $formSubmissionsAction): ?string
    {
        return match ($formSubmissionsAction) {
            self::READ_SUBMISSIONS_ACTION => self::READ_SUBMISSION_ACTION,
            self::UPDATE_SUBMISSIONS_ACTION => self::UPDATE_SUBMISSION_ACTION,
            self::DELETE_SUBMISSIONS_ACTION => self::DELETE_SUBMISSION_ACTION,
            default => null,
        };
    }
}
