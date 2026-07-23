<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Authorization;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\AuthorizationBundle\Entity\GrantedActions;
use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\Authorization\Serializer\EntityNormalizer;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

class AuthorizationService extends AbstractAuthorizationService implements ResetInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const MAX_NUM_RESULTS_MAX = ResourceActionGrantService::MAX_NUM_RESULTS_MAX;

    public const MANAGE_ACTION = ResourceActionGrantService::MANAGE_ACTION;

    /**
     * Form item actions:
     */
    public const READ_FORM_ACTION = 'read';
    public const UPDATE_FORM_ACTION = 'update';
    public const DELETE_FORM_ACTION = 'delete';
    public const CREATE_SUBMISSIONS_FORM_ACTION = 'create_submissions';

    public const FORM_ITEM_ACTIONS = [
        self::READ_FORM_ACTION,
        self::UPDATE_FORM_ACTION,
        self::DELETE_FORM_ACTION,
        self::CREATE_SUBMISSIONS_FORM_ACTION,
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
        AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION => [
            'en' => 'Create Submissions',
            'de' => 'Einreichungen erstellen',
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

    public const FORM_RESOURCE_CLASS = 'DbpRelayFormalizeForm';
    public const SUBMISSION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmission';
    public const SUBMISSION_COLLECTION_RESOURCE_CLASS = 'DbpRelayFormalizeSubmissionCollection';

    public const SUBMITTER_ROLE_IDENTIFIER = '019f7f84-756b-7b00-a893-14784a068d9d';
    public const READER_ROLE_IDENTIFIER = '019f7f84-759e-7712-a997-5e91ab07e1f6';
    public const EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER = '019f7f84-75a4-7244-b827-78695f09442c';
    public const EDITOR_WITH_DELETE_ROLE_IDENTIFIER = '019f7f84-75a1-76ed-a40d-970977b0bcf6';

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

    public static function ensureAvailableResourceClassActions(ResourceActionGrantService $resourceActionGrantService): void
    {
        $resourceActionGrantService->addOrUpdateAvailableResourceClassActions(
            self::FORM_RESOURCE_CLASS,
            self::AVAILABLE_FORM_ITEM_ACTIONS,
            self::AVAILABLE_FORM_COLLECTION_ACTIONS
        );
        $resourceActionGrantService->addOrUpdateAvailableResourceClassActions(
            self::SUBMISSION_RESOURCE_CLASS,
            self::AVAILABLE_SUBMISSION_ITEM_ACTIONS,
            self::AVAILABLE_SUBMISSION_COLLECTION_ACTIONS
        );
    }

    public static function ensureRoles(ResourceActionGrantService $resourceActionGrantService): void
    {
        $resourceActionGrantService->addOrUpdateRole(
            [
                'en' => 'Submitter',
                'de' => 'Einreicher',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::FORM_RESOURCE_CLASS, AuthorizationService::READ_FORM_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::FORM_RESOURCE_CLASS, AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
            identifier: AuthorizationService::SUBMITTER_ROLE_IDENTIFIER
        );
        $resourceActionGrantService->addOrUpdateRole(
            [
                'en' => 'Reader',
                'de' => 'Leser',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::READ_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
            identifier: AuthorizationService::READER_ROLE_IDENTIFIER
        );
        $resourceActionGrantService->addOrUpdateRole(
            [
                'en' => 'Editor (without delete)',
                'de' => 'Bearbeiter (ohne Löschen)',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::READ_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::UPDATE_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
            identifier: AuthorizationService::EDITOR_WITHOUT_DELETE_ROLE_IDENTIFIER
        );
        $resourceActionGrantService->addOrUpdateRole(
            [
                'en' => 'Editor (with delete)',
                'de' => 'Bearbeiter (mit Löschen)',
            ],
            [
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::READ_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::UPDATE_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
                ResourceActionGrantService::createRoleAction(
                    AuthorizationService::SUBMISSION_RESOURCE_CLASS, AuthorizationService::DELETE_SUBMISSION_ACTION, ResourceActionGrantService::ITEM_ACTION_TYPE),
            ],
            identifier: AuthorizationService::EDITOR_WITH_DELETE_ROLE_IDENTIFIER
        );
    }

    public function __construct(
        private readonly ResourceActionGrantService $resourceActionGrantService,
        private readonly EntityNormalizer $entityNormalizer,
        private bool $debug = false)
    {
        parent::__construct();
    }

    /**
     * @throws \Throwable
     */
    public function validateConfiguration(): void
    {
        $this->isGrantedResourcePermission(Configuration::MAY_CREATE_FORM, new Form());
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
        return $this->getGrantedFormActionsCached($form);
    }

    /**
     * @return string[]
     */
    public function getGrantedSubmissionGroupActions(Form $form): array
    {
        return $this->getGrantedSubmissionGroupActionsCached($form);
    }

    /**
     * @return string[]
     */
    public function getGrantedSubmissionItemActions(Submission $submission): array
    {
        return $this->getGrantedSubmissionItemActionsCached($submission);
    }

    /**
     * If $firstResultIndex is 0 and $maxNumResults null, all results are returned.
     *
     * @return array<string, array<int, string>>
     */
    public function getGrantedFormItemActionsCollectionWhereCurrentUserIsAuthorizedToRead(
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        return self::toGrantedActionsArray(
            $this->getGrantedItemActionsCollectionForCurrentUser(
                self::FORM_RESOURCE_CLASS,
                whereIsGrantedAction: self::READ_FORM_ACTION,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults
            )
        );
    }

    /**
     * Returns a mapping of submission identifiers to the submission actions that the current user has
     * (submission-level) grants for, where the granted actions contain a read grant.
     *
     * @return array[] Array key: submission identifier Array value: Set of actions the current user has grants for
     */
    public function getGrantedSubmissionItemActionsCollectionWhereCurrentUserIsAuthorizedToRead(): array
    {
        return self::toGrantedActionsArray(
            $this->getGrantedItemActionsCollectionForCurrentUser(
                self::SUBMISSION_RESOURCE_CLASS,
                whereIsGrantedAction: self::READ_SUBMISSION_ACTION
            )
        );
    }

    /**
     * If $firstResultIndex is 0 and $maxNumResults null, all results are returned.
     *
     * @return array<string, array<int, string>>
     */
    public function getGrantedSubmissionGroupItemActionsCollection(?string $whereIsGrantedAction = null,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        return self::toGrantedActionsArray(
            $this->getGrantedItemActionsCollectionForCurrentUser(
                self::SUBMISSION_RESOURCE_CLASS,
                resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE,
                whereIsGrantedAction: $whereIsGrantedAction,
                firstResultIndex: $firstResultIndex,
                maxNumResults: $maxNumResults
            )
        );
    }

    public function isCurrentUserAuthorizedToAddForm(Form $form): bool
    {
        return $this->resourceActionGrantService->isCurrentUserGranted(
            self::FORM_RESOURCE_CLASS,
            ResourceActionGrantService::COLLECTION_RESOURCE_IDENTIFIER,
            self::CREATE_FORMS_ACTION)
            || $this->isGrantedResourcePermission(Configuration::MAY_CREATE_FORM, $form);
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
        return $this->isCurrentUserGrantedSubmissionGroupAction(self::DELETE_SUBMISSION_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToUpdateFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedSubmissionGroupAction(self::UPDATE_SUBMISSION_ACTION, $form);
    }

    public function isCurrentUserAuthorizedToReadFormSubmissions(Form $form): bool
    {
        return $this->isCurrentUserGrantedSubmissionGroupAction(self::READ_SUBMISSION_ACTION, $form);
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
     * @throws ApiError
     */
    public function registerForm(Form $form, ?string $formManagerUserIdentifier = null): void
    {
        $formIdentifier = $form->getIdentifier();

        // form item:
        $this->resourceActionGrantService->addResourceActionGrant(
            self::FORM_RESOURCE_CLASS,
            $formIdentifier,
            action: ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: $formManagerUserIdentifier
        );
        // submission group item representing the group of all submissions of this form:
        $this->resourceActionGrantService->addResourceActionGrant(
            self::SUBMISSION_RESOURCE_CLASS,
            $formIdentifier,
            resourceType: ResourceActionGrantService::RESOURCE_GROUP_RESOURCE_TYPE,
            action: ResourceActionGrantService::MANAGE_ACTION,
            userIdentifier: $formManagerUserIdentifier
        );

        $this->grantedFormActionsCache[$formIdentifier] = [ResourceActionGrantService::MANAGE_ACTION];
        $this->grantedSubmissionCollectionActionsCache[$formIdentifier] = [ResourceActionGrantService::MANAGE_ACTION];
    }

    /**
     * @throws ApiError
     */
    public function deregisterForm(Form $form): void
    {
        $this->resourceActionGrantService->removeResource(
            resourceIdentifier: $form->getIdentifier(),
            resourceType: null // remove both form item and submission group item grants
        );
        unset($this->grantedFormActionsCache[$form->getIdentifier()]);
        unset($this->grantedSubmissionCollectionActionsCache[$form->getIdentifier()]);
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionAdded(Submission $submission): void
    {
        if ($submission->isDraft()) {
            if (null === ($userIdentifier = $this->getUserIdentifier())) {
                throw ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                    'Using drafts requires a unique user identifier');
            }
            $this->resourceActionGrantService->addResourceActionGrant(
                self::SUBMISSION_RESOURCE_CLASS,
                $submission->getIdentifier(),
                roleIdentifier: $submission->getForm()->getRoleIdentifierWhenDraft(),
                userIdentifier: $userIdentifier);
        }
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionRemoved(string $identifier): void
    {
        $this->resourceActionGrantService->removeResource(
            self::SUBMISSION_RESOURCE_CLASS, $identifier);
        unset($this->grantedSubmissionActionsCache[$identifier]);
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionsRemoved(array $submissionIdentifiers): void
    {
        $this->resourceActionGrantService->removeResources(
            self::SUBMISSION_RESOURCE_CLASS, $submissionIdentifiers);
        // usually all form submissions are removed at once, so just clear the cache:
        $this->grantedSubmissionActionsCache = [];
    }

    /**
     * @throws ApiError
     */
    public function onSubmissionSubmitted(Submission $submission, bool $wasDraft): void
    {
        if ($wasDraft) {
            // submission was posted as a draft before -> remove draft submission grants
            $this->resourceActionGrantService->removeGrantsForResource(
                self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier());
        }
        $this->resourceActionGrantService->addResourceToGroupResource(
            self::SUBMISSION_RESOURCE_CLASS,
            resourceGroupResourceIdentifier: $submission->getForm()->getIdentifier(),
            resourceIdentifier: $submission->getIdentifier());

        if (($roleIdentifier = $submission->getForm()->getRoleIdentifierWhenSubmitted())
            && ($userIdentifier = $this->getUserIdentifier())) {
            $this->resourceActionGrantService->addResourceActionGrant(
                self::SUBMISSION_RESOURCE_CLASS,
                $submission->getIdentifier(),
                roleIdentifier: $roleIdentifier,
                userIdentifier: $userIdentifier
            );
        }
        // NOTE: don't cache granted submission actions,
        // since the user might already have rights stemming from the form's
        // submission group item grants
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
    private function getGrantedSubmissionGroupActionsCached(Form $form): array
    {
        $formIdentifier = $form->getIdentifier();
        if (null ===
            ($grantedFormItemActions = $this->grantedSubmissionCollectionActionsCache[$formIdentifier] ?? null)) {
            $this->cacheGrantedFormActions($formIdentifier);
            $grantedFormItemActions = $this->grantedSubmissionCollectionActionsCache[$formIdentifier];
        }

        return $grantedFormItemActions;
    }

    /**
     * @return string[]
     */
    private function getGrantedFormActionsCached(Form $form): array
    {
        $formIdentifier = $form->getIdentifier();
        if (null ===
            ($grantedFormItemActions = $this->grantedFormActionsCache[$formIdentifier] ?? null)) {
            $this->cacheGrantedFormActions($formIdentifier);
            $grantedFormItemActions = $this->grantedFormActionsCache[$formIdentifier];
        }

        return $grantedFormItemActions;
    }

    private function cacheGrantedFormActions(string $formIdentifier): void
    {
        $this->grantedFormActionsCache[$formIdentifier] = [];
        $this->grantedSubmissionCollectionActionsCache[$formIdentifier] = [];

        foreach ($this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            resourceIdentifier: $formIdentifier,
            resourceType: null) as $grantedActions) {
            $grantedFormItemActions = $grantedActions->getActions();
            if (in_array(self::MANAGE_ACTION, $grantedFormItemActions, true)) {
                // manage action implies all others. So if granted, remove all others:
                $grantedFormItemActions = [self::MANAGE_ACTION];
            }

            switch ($grantedActions->getResourceClass()) {
                case self::FORM_RESOURCE_CLASS:
                    $this->grantedFormActionsCache[$formIdentifier] = $grantedFormItemActions;
                    break;
                case self::SUBMISSION_RESOURCE_CLASS:
                    $this->grantedSubmissionCollectionActionsCache[$formIdentifier] = $grantedFormItemActions;
                    break;
                default:
                    $this->logger->warning('unexpected resource class for form identifier', [
                        $formIdentifier,
                        $grantedActions->getResourceClass(),
                    ]);
                    assert(false);
            }
        }
    }

    /**
     * @return GrantedActions[]
     */
    private function getGrantedItemActionsCollectionForCurrentUser(
        string $resourceClass,
        int $resourceType = ResourceActionGrantService::RESOURCE_RESOURCE_TYPE,
        ?string $whereIsGrantedAction = null,
        int $firstResultIndex = 0, ?int $maxNumResults = null): array
    {
        if ($firstResultIndex === 0 && $maxNumResults === null) { // i.e. gimme all
            $currentPageStartIndex = 0;
            $maxNumItemsPerPage = 1024;
            $resultItems = [];
            do {
                $pageItems =
                    $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
                        $resourceClass,
                        resourceType: $resourceType,
                        whereIsGrantedAction: $whereIsGrantedAction,
                        firstResultIndex: $currentPageStartIndex,
                        maxNumResults: $maxNumItemsPerPage);
                $resultItems = array_merge($resultItems, $pageItems);
                $currentPageStartIndex += $maxNumItemsPerPage;
            } while (count($pageItems) >= $maxNumItemsPerPage);

            return $resultItems;
        }

        return $this->resourceActionGrantService->getGrantedActionsCollectionForCurrentUser(
            $resourceClass,
            whereIsGrantedAction: $whereIsGrantedAction,
            firstResultIndex: $firstResultIndex,
            maxNumResults: $maxNumResults ?? self::MAX_NUM_RESULTS_MAX);
    }

    /**
     * @return string[]
     */
    private function getGrantedSubmissionItemActionsCached(Submission $submission): array
    {
        if (($grantedSubmissionItemActions =
                $this->grantedSubmissionActionsCache[$submission->getIdentifier()] ?? null) === null) {
            $grantedSubmissionItemActions = $this->resourceActionGrantService->getGrantedActionsForCurrentUser(
                self::SUBMISSION_RESOURCE_CLASS, $submission->getIdentifier())?->getActions() ?? [];
            $this->grantedSubmissionActionsCache[$submission->getIdentifier()] = $grantedSubmissionItemActions;
        }

        return $grantedSubmissionItemActions;
    }

    private function isCurrentUserGrantedSubmissionAction(string $action, Submission $submission): bool
    {
        $grantedSubmissionItemActions = $this->getGrantedSubmissionItemActionsCached($submission);

        return in_array(self::MANAGE_ACTION, $grantedSubmissionItemActions, true)
            || in_array($action, $grantedSubmissionItemActions, true);
    }

    private function isCurrentUserGrantedSubmissionGroupAction(string $action, Form $form): bool
    {
        $grantedSubmissionCollectionActions = $this->getGrantedSubmissionGroupActionsCached($form);

        return in_array(self::MANAGE_ACTION, $grantedSubmissionCollectionActions, true)
            || in_array($action, $grantedSubmissionCollectionActions, true);
    }

    private function isCurrentUserGrantedFormAction(string $action, Form $form): bool
    {
        $grantedFormItemActions = $this->getGrantedFormActionsCached($form);

        return in_array(self::MANAGE_ACTION, $grantedFormItemActions, true)
            || in_array($action, $grantedFormItemActions, true);
    }

    /**
     * @param GrantedActions[] $grantedActionsEntities
     *
     * @return array<string, array<int, string>>
     */
    private static function toGrantedActionsArray(array $grantedActionsEntities): array
    {
        $grantedActionsArray = [];
        foreach ($grantedActionsEntities as $grantedActionsEntity) {
            $grantedActionsArray[$grantedActionsEntity->getResourceIdentifier()] = $grantedActionsEntity->getActions();
        }

        return $grantedActionsArray;
    }
}
