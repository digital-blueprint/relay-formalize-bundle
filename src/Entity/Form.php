<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="formalize_forms")
 */
class Form
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=50)
     *
     * @Groups({"FormalizeForm:output"})
     *
     * @var string
     */
    private $identifier;

    /**
     * @ORM\Column(name="name", type="string", length=256)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $name;

    /**
     * @ORM\Column(name="date_created", type="datetime")
     *
     * @Groups({"FormalizeForm:output"})
     *
     * @var \DateTime
     */
    private $dateCreated;

    /**
     * @ORM\Column(name="data_feed_schema", type="text")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $dataFeedSchema;

    /**
     * @ORM\Column(name="availability_starts", type="datetime")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var \DateTime
     */
    private $availabilityStarts;

    /**
     * @ORM\Column(name="availability_ends", type="datetime")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var \DateTime
     */
    private $availabilityEnds;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): \DateTime
    {
        return $this->dateCreated;
    }

    public function getDataFeedSchema(): string
    {
        return $this->dataFeedSchema;
    }

    public function setDataFeedSchema(string $dataFeedSchema): void
    {
        $this->dataFeedSchema = $dataFeedSchema;
    }

    public function getAvailabilityStarts(): \DateTime
    {
        return $this->availabilityStarts;
    }

    public function setAvailabilityStarts(\DateTime $availabilityStarts): void
    {
        $this->availabilityStarts = $availabilityStarts;
    }

    public function getAvailabilityEnds(): \DateTime
    {
        return $this->availabilityEnds;
    }

    public function setAvailabilityEnds(\DateTime $availabilityEnds): void
    {
        $this->availabilityEnds = $availabilityEnds;
    }
}
