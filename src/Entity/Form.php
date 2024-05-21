<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: 'formalize_forms')]
#[ORM\Entity]
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

    #[ORM\Column(name: 'data_feed_schema', type: 'text', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?string $dataFeedSchema = null;

    #[ORM\Column(name: 'availability_starts', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityStarts = null;

    #[ORM\Column(name: 'availability_ends', type: 'datetime', nullable: true)]
    #[Groups(['FormalizeForm:input', 'FormalizeForm:output'])]
    private ?\DateTime $availabilityEnds = null;

    #[ORM\Column(name: 'submission_level_authorization', type: 'boolean', options: ['default' => false])]
    private bool $submissionLevelAuthorization = false;

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
}
