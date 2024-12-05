<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

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

    /**
     * @deprecated
     */
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?string $dataFeedSchema = null;

    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'example' => [
                'type' => 'object',
                'properties' => [
                    'firstname' => [
                        'type' => 'string',
                    ],
                    'lastname' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['firstname', 'lastname'],
                'additionalProperties' => false,
            ],
        ],
        jsonSchemaContext: [
            'type' => 'object',
        ]
    )]
    #[ORM\Column(name: 'data_feed_schema', type: 'json', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?array $dataSchema = null;

    #[ORM\Column(name: 'availability_starts', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityStarts = null;

    #[ORM\Column(name: 'availability_ends', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityEnds = null;

    #[ORM\Column(name: 'submission_level_authorization', type: 'boolean', options: ['default' => false])]
    private bool $submissionLevelAuthorization = false;

    #[Groups(['FormalizeForm:output'])]
    private array $grantedActions = [];

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
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

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): ?\DateTime
    {
        return $this->dateCreated;
    }

    /**
     * @deprecated Use getDataSchema() instead
     */
    public function getDataFeedSchema(): ?string
    {
        return $this->dataFeedSchema;
    }

    /**
     * @deprecated Use setDataSchema() instead
     */
    public function setDataFeedSchema(?string $dataFeedSchema): void
    {
        $this->dataFeedSchema = $dataFeedSchema;
    }

    public function getDataSchema(): ?array
    {
        return $this->dataSchema;
    }

    public function setDataSchema(?array $dataSchema): void
    {
        $this->dataSchema = $dataSchema;
    }

    public function getAvailabilityStarts(): ?\DateTime
    {
        return $this->availabilityStarts;
    }

    public function setAvailabilityStarts(\DateTime $availabilityStarts): void
    {
        $this->availabilityStarts = $availabilityStarts;
    }

    public function getAvailabilityEnds(): ?\DateTime
    {
        return $this->availabilityEnds;
    }

    public function setAvailabilityEnds(\DateTime $availabilityEnds): void
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

    public function getSubmissionLevelAuthorization(): bool
    {
        return $this->submissionLevelAuthorization;
    }

    public function setSubmissionLevelAuthorization(bool $submissionLevelAuthorization): void
    {
        $this->submissionLevelAuthorization = $submissionLevelAuthorization;
    }

    public function getGrantedActions(): array
    {
        return $this->grantedActions;
    }

    public function setGrantedActions(array $grantedActions): void
    {
        $this->grantedActions = $grantedActions;
    }
}
