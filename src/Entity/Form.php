<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
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
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            provider: FormProvider::class
        ),
        new GetCollection(
            uriTemplate: '/formalize/forms',
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            provider: FormProvider::class
        ),
        new Post(
            uriTemplate: '/formalize/forms',
            openapiContext: [
                'tags' => ['Formalize'],
                'requestBody' => [
                    'content' => [
                        'application/ld+json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name'],
                                'properties' => [
                                    'name' => [
                                        'type' => 'string',
                                        'example' => 'My Form',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            processor: FormProcessor::class,
        ),
        new Patch(
            uriTemplate: '/formalize/forms/{identifier}',
            inputFormats: [
                'json' => ['application/merge-patch+json'],
            ],
            openapiContext: [
                'tags' => ['Formalize'],
                'requestBody' => [
                    'content' => [
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
                    ],
                ],
            ],
            provider: FormProvider::class,
            processor: FormProcessor::class,
        ),
        new Delete(
            uriTemplate: '/formalize/forms/{identifier}',
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            provider: FormProvider::class,
            processor: FormProcessor::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['FormalizeForm:output'],
        'jsonld_embed_context' => true,
        'preserve_empty_objects' => true,
    ],
    denormalizationContext: [
        'groups' => ['FormalizeForm:input'],
    ],
)]
class Form
{
    public const READ_SUBMISSION_ACTION_FLAG = 0b0001;
    public const UPDATE_SUBMISSION_ACTION_FLAG = 0b0010;
    public const DELETE_SUBMISSION_ACTION_FLAG = 0b0100;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['FormalizeForm:output'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'name', type: 'string', length: 256)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?string $name = null;

    #[ORM\Column(name: 'date_created', type: 'datetime', nullable: true)]
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
    #[ORM\Column(name: 'grant_based_submission_authorization', type: 'boolean', options: ['default' => false])]
    private bool $grantBasedSubmissionAuthorization = false;

    #[ORM\Column(name: 'allowed_submission_states', type: 'smallint', options: ['default' => Submission::SUBMISSION_STATE_SUBMITTED])]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private int $allowedSubmissionStates = Submission::SUBMISSION_STATE_SUBMITTED;

    #[ORM\Column(name: 'allowed_actions_when_submitted', type: 'smallint', nullable: false, options: ['default' => 0])]
    private int $allowedActionsWhenSubmitted = 0;

    #[Groups(['FormalizeForm:output'])]
    private array $grantedActions = [];

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

    #[Ignore]
    public function getAllowedActionsWhenSubmitted(): int
    {
        return $this->allowedActionsWhenSubmitted;
    }

    public function isAllowedSubmissionActionWhenSubmitted(string $action): bool
    {
        return $this->isAllowedSubmissionActionFlag(self::toSubmissionActionFlag($action));
    }

    #[SerializedName('allowedActionsWhenSubmitted')]
    #[Groups(['FormalizeForm:output'])]
    public function getAllowedActionsWhenSubmittedPublic(): array
    {
        return array_values(array_filter([
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
    }

    public function getGrantedActions(): array
    {
        return $this->grantedActions;
    }

    public function setGrantedActions(array $grantedActions): void
    {
        $this->grantedActions = $grantedActions;
    }

    private function isAllowedSubmissionActionFlag(int $actionFlag): bool
    {
        return $actionFlag !== 0
            && ($this->allowedActionsWhenSubmitted & $actionFlag) === $actionFlag;
    }

    private static function toSubmissionActionFlag(string $submissionAction): int
    {
        return match ($submissionAction) {
            AuthorizationService::READ_SUBMISSION_ACTION => self::READ_SUBMISSION_ACTION_FLAG,
            AuthorizationService::UPDATE_SUBMISSION_ACTION => self::UPDATE_SUBMISSION_ACTION_FLAG,
            AuthorizationService::DELETE_SUBMISSION_ACTION => self::DELETE_SUBMISSION_ACTION_FLAG,
            default => 0,
        };
    }
}
