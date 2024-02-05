<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Dbp\Relay\CoreBundle\Helpers\Tools;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="formalize_submissions")
 */
class Submission
{
    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=50)
     *
     * @Groups({"FormalizeSubmission:output"})
     *
     * @var string|null
     */
    private $identifier;

    /**
     * @ORM\Column(type="text")
     *
     * @Groups({"FormalizeSubmission:output", "FormalizeSubmission:input"})
     *
     * @var string|null
     */
    private $dataFeedElement;

    /**
     * @ORM\ManyToOne(targetEntity="Form")
     *
     * @ORM\JoinColumn(name="form", referencedColumnName="identifier")]
     *
     * @Groups({"FormalizeSubmission:output", "FormalizeSubmission:input"})
     *
     * @var Form|null
     */
    private $form;

    /**
     * @ORM\Column(type="datetime")
     *
     * @Groups({"FormalizeSubmission:output"})
     *
     * @var \DateTime|null
     */
    private $dateCreated;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getDataFeedElement(): ?string
    {
        return $this->dataFeedElement;
    }

    public function setDataFeedElement(?string $dataFeedElement): void
    {
        $this->dataFeedElement = $dataFeedElement;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(Form $form): void
    {
        $this->form = $form;
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
     * @throws \JsonException
     */
    public function getDataFeedElementDecoded(): array
    {
        return Tools::decodeJSON($this->dataFeedElement ?? '', true);
    }
}
