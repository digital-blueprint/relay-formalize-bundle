<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Table(name: 'formalize_forms')]
#[ORM\Entity]
#[ApiResource(
    shortName: 'FormalizeForm',
    types: ['https://schema.org/Dataset'],
    operations: [
        new Get(
            uriTemplate: '/formalize/forms/{identifier}',
            openapi: new Operation(
                tags: ['Formalize']
            ),
            provider: FormProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/formalize/forms',
            openapi: new Operation(
                tags: ['Formalize']
            ),
            normalizationContext: [
                'groups' => ['FormalizeForm:output'],
                'jsonld_embed_context' => true,
                'preserve_empty_objects' => true,
            ],
            provider: FormProvider::class,
            parameters: [
                FormalizeService::WHERE_MAY_READ_SUBMISSIONS_FILTER => new QueryParameter(
                    schema: [
                        'type' => 'boolean',
                    ],
                    description: 'Only return forms where the user either has form-level read submission permissions,
                    or there is at least one submission the user may read.',
                ),
            ],
        ),
        new Post(
            uriTemplate: '/formalize/forms',
            openapi: new Operation(
                tags: ['Formalize'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name'],
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'example' => 'My Form',
                                    ],
                                    'availableTags' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                        ],
                                        'example' => <<<END
                                            [
                                               {
                                                  "identifier": "tag 1",
                                                  "backgroundColor": "green",
                                                  "name": {"en": "Tag 1", "de": "Etikett 1"}
                                               },
                                               {
                                                  "identifier": "tag 2",
                                                  "backgroundColor": "#92a8d1",
                                                  "name": {"en": "Tag 2", "de": "Etikett 2"}
                                               }
                                            ]
                                            END,
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            ),
            denormalizationContext: [
                'groups' => ['FormalizeForm:input', 'FormalizeForm:add'],
            ],
            processor: FormProcessor::class,
        ),
        new Patch(
            uriTemplate: '/formalize/forms/{identifier}',
            inputFormats: [
                'json' => ['application/merge-patch+json'],
            ],
            openapi: new Operation(
                tags: ['Formalize'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'application/merge-patch+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'example' => 'My Patched Form',
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            ),
            provider: FormProvider::class,
            processor: FormProcessor::class,
        ),
        new Delete(
            uriTemplate: '/formalize/forms/{identifier}',
            openapi: new Operation(
                tags: ['Formalize']
            ),
            provider: FormProvider::class,
            processor: FormProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['FormalizeForm:output', 'FormalizeForm:get_item_only_output'],
        'jsonld_embed_context' => true,
        'preserve_empty_objects' => true,
    ],
    denormalizationContext: [
        'groups' => ['FormalizeForm:input'],
    ],
)]
class Form
{
    public const TAG_PERMISSIONS_NONE = 0;
    public const TAG_PERMISSIONS_READ = 1;
    public const TAG_PERMISSIONS_READ_ADD = 2;
    public const TAG_PERMISSIONS_READ_ADD_REMOVE = 3;

    public const TAG_PERMISSIONS = [
        self::TAG_PERMISSIONS_NONE,
        self::TAG_PERMISSIONS_READ,
        self::TAG_PERMISSIONS_READ_ADD,
        self::TAG_PERMISSIONS_READ_ADD_REMOVE,
    ];

    public const READ_SUBMISSION_ACTION_FLAG = 0b0001;
    public const UPDATE_SUBMISSION_ACTION_FLAG = 0b0010;
    public const DELETE_SUBMISSION_ACTION_FLAG = 0b0100;
    public const MANAGE_ACTION_FLAG = 0b1000;

    public const AVAILABLE_TAG_IDENTIFIER_KEY = 'identifier';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['FormalizeForm:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'name', type: 'string', length: 256)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?string $name = null;

    #[ORM\Column(name: 'date_created', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:output'])]
    private ?\DateTime $dateCreated = null;

    #[ORM\Column(name: 'creator_id', type: 'string', length: 50, nullable: true)]
    private ?string $creatorId = null;

    #[ORM\Column(name: 'data_feed_schema', type: 'text', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?string $dataFeedSchema = null;

    #[ORM\Column(name: 'availability_starts', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityStarts = null;

    #[ORM\Column(name: 'availability_ends', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityEnds = null;

    /**
     * If true, authorization decisions are based on grants (managed by the authorization bundle).
     * When new submissions are registered, the creator is issued a manage grant and may thus issue grants for the submission to other user.
     * If false (-> created-based submission authorization), authorization decisions or based on the creatorId of the submission.
     */
    #[ORM\Column(name: 'grant_based_submission_authorization', type: 'boolean', nullable: false, options: ['default' => false])]
    #[Groups(['FormalizeForm:add', 'FormalizeForm:output'])]
    private bool $grantBasedSubmissionAuthorization = false;

    #[ORM\Column(name: 'allowed_submission_states', type: 'smallint', nullable: false, options: ['default' => Submission::SUBMISSION_STATE_SUBMITTED])]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private int $allowedSubmissionStates = Submission::SUBMISSION_STATE_SUBMITTED;

    #[ORM\Column(name: 'allowed_actions_when_submitted', type: 'smallint', nullable: false, options: ['default' => 0])]
    private int $allowedActionsWhenSubmitted = 0;

    #[ORM\Column(name: 'tag_permissions_for_submitters', type: 'smallint', nullable: false, options: ['default' => self::TAG_PERMISSIONS_READ])]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private int $tagPermissionsForSubmitters = self::TAG_PERMISSIONS_READ;

    #[ORM\Column(name: 'max_num_submissions_per_creator', type: 'smallint', nullable: false, options: ['default' => 10])]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private int $maxNumSubmissionsPerCreator = 10;

    /**
     * @var array<int, array<string, string>>|null
     */
    #[ORM\Column(name: 'available_tags', type: 'json', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output:availableTags'])]
    private ?array $availableTags = null;

    #[Groups(['FormalizeForm:output'])]
    private array $grantedActions = [];

    #[Groups(['FormalizeForm:get_item_only_output'])]
    private int $numSubmissionsByCurrentUser = 0;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setDateCreated(?\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    public function getDataFeedSchema(): ?string
    {
        return $this->dataFeedSchema;
    }

    public function setDataFeedSchema(?string $dataFeedSchema): void
    {
        $this->dataFeedSchema = $dataFeedSchema;
    }

    public function getAvailabilityStarts(): ?\DateTime
    {
        return $this->availabilityStarts;
    }

    public function setAvailabilityStarts(?\DateTime $availabilityStarts): void
    {
        $this->availabilityStarts = $availabilityStarts;
    }

    public function getAvailabilityEnds(): ?\DateTime
    {
        return $this->availabilityEnds;
    }

    public function setAvailabilityEnds(?\DateTime $availabilityEnds): void
    {
        $this->availabilityEnds = $availabilityEnds;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    public function getGrantBasedSubmissionAuthorization(): bool
    {
        return $this->grantBasedSubmissionAuthorization;
    }

    public function setGrantBasedSubmissionAuthorization(bool $grantBasedSubmissionAuthorization): void
    {
        $this->grantBasedSubmissionAuthorization = $grantBasedSubmissionAuthorization;
    }

    public function getAllowedSubmissionStates(): int
    {
        return $this->allowedSubmissionStates;
    }

    public function setAllowedSubmissionStates(int $allowedSubmissionStates): void
    {
        $this->allowedSubmissionStates = $allowedSubmissionStates;
    }

    #[Ignore]
    public function isAllowedSubmissionState(int $submissionState): bool
    {
        return ($this->allowedSubmissionStates & $submissionState) === $submissionState;
    }

    public function isAllowedSubmissionActionWhenSubmitted(string $action): bool
    {
        return $this->isAllowedSubmissionActionWhenSubmittedFlag(self::toSubmissionActionFlag($action));
    }

    #[SerializedName('allowedActionsWhenSubmitted')]
    #[Groups(['FormalizeForm:output'])]
    public function getAllowedActionsWhenSubmitted(): array
    {
        return $this->isAllowedSubmissionActionWhenSubmittedFlag(self::MANAGE_ACTION_FLAG) ?
            [AuthorizationService::MANAGE_ACTION] :
            array_values(array_filter([
                AuthorizationService::READ_SUBMISSION_ACTION,
                AuthorizationService::UPDATE_SUBMISSION_ACTION,
                AuthorizationService::DELETE_SUBMISSION_ACTION],
                function ($submissionAction) {
                    return $this->isAllowedSubmissionActionWhenSubmitted($submissionAction);
                }));
    }

    #[SerializedName('allowedActionsWhenSubmitted')]
    #[Groups(['FormalizeForm:input'])]
    public function setAllowedActionsWhenSubmittedPublic(?array $allowedActionsWhenSubmitted): void
    {
        foreach ($allowedActionsWhenSubmitted ?? [] as $allowedSubmissionActionWhenSubmitted) {
            $this->allowedActionsWhenSubmitted |= self::toSubmissionActionFlag($allowedSubmissionActionWhenSubmitted);
        }
        // manage action implies all others. So if allowed, remove all others:
        if ($this->allowedActionsWhenSubmitted & self::MANAGE_ACTION_FLAG) {
            $this->allowedActionsWhenSubmitted = self::MANAGE_ACTION_FLAG;
        }
    }

    public function getTagPermissionsForSubmitters(): int
    {
        return $this->tagPermissionsForSubmitters;
    }

    public function setTagPermissionsForSubmitters(int $tagPermissionsForSubmitters): void
    {
        $this->tagPermissionsForSubmitters = $tagPermissionsForSubmitters;
    }

    public function getMaxNumSubmissionsPerCreator(): int
    {
        return $this->maxNumSubmissionsPerCreator;
    }

    public function setMaxNumSubmissionsPerCreator(int $maxNumSubmissionsPerCreator): void
    {
        $this->maxNumSubmissionsPerCreator = $maxNumSubmissionsPerCreator;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getAvailableTags(): array
    {
        return $this->availableTags ?? [];
    }

    /**
     * @param array<int, array<string, string>>|null $availableTags
     */
    public function setAvailableTags(?array $availableTags): void
    {
        $this->availableTags = $availableTags;
    }

    public function getGrantedActions(): array
    {
        return $this->grantedActions;
    }

    public function setGrantedActions(array $grantedActions): void
    {
        if (in_array(AuthorizationService::MANAGE_ACTION, $grantedActions, true)) {
            $grantedActions = [AuthorizationService::MANAGE_ACTION];
        } else {
            foreach ($grantedActions as $grantedAction) {
                if (false === in_array($grantedAction, AuthorizationService::FORM_ITEM_ACTIONS, true)) {
                    throw new \RuntimeException('undefined granted form item action: '.$grantedAction);
                }
            }
        }
        $this->grantedActions = $grantedActions;
    }

    public function isGrantedAction(string $action): bool
    {
        return
            ($this->grantedActions === [AuthorizationService::MANAGE_ACTION]
                && in_array($action, AuthorizationService::FORM_ITEM_ACTIONS, true))
            || in_array($action, $this->grantedActions, true);
    }

    public function getNumSubmissionsByCurrentUser(): int
    {
        return $this->numSubmissionsByCurrentUser;
    }

    public function setNumSubmissionsByCurrentUser(int $numSubmissionsByCurrentUser): void
    {
        $this->numSubmissionsByCurrentUser = $numSubmissionsByCurrentUser;
    }

    #[Ignore]
    private function isAllowedSubmissionActionWhenSubmittedFlag(int $actionFlag): bool
    {
        return $actionFlag !== 0
            && ($this->allowedActionsWhenSubmitted & (self::MANAGE_ACTION_FLAG | $actionFlag)) !== 0;
    }

    private static function toSubmissionActionFlag(string $submissionAction): int
    {
        return match ($submissionAction) {
            AuthorizationService::READ_SUBMISSION_ACTION => self::READ_SUBMISSION_ACTION_FLAG,
            AuthorizationService::UPDATE_SUBMISSION_ACTION => self::UPDATE_SUBMISSION_ACTION_FLAG,
            AuthorizationService::DELETE_SUBMISSION_ACTION => self::DELETE_SUBMISSION_ACTION_FLAG,
            AuthorizationService::MANAGE_ACTION => self::MANAGE_ACTION_FLAG,
            default => 0,
        };
    }
}
