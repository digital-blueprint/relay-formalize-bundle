<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @Assert\NotBlank
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
}
