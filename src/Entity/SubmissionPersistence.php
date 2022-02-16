<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="formalize_submission")
 */
class SubmissionPersistence
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=50)
     */
    private $identifier;

    /**
     * @ORM\Column(type="text")
     *
     * @var string
     */
    private $dataFeedElement;

    /**
     * @ORM\Column(type="text")
     *
     * @var string
     */
    private $form;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTime
     */
    private $dateCreated;

    public function getDataFeedElement(): string
    {
        return $this->dataFeedElement;
    }

    public function setDataFeedElement(string $dataFeedElement): void
    {
        $this->dataFeedElement = $dataFeedElement;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getForm(): string
    {
        return $this->form;
    }

    public function setForm(string $form): void
    {
        $this->form = $form;
    }

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): \DateTime
    {
        return $this->dateCreated;
    }

    public static function fromSubmission(Submission $submission): SubmissionPersistence
    {
        $submissionPersistence = new SubmissionPersistence();
        $submissionPersistence->setIdentifier($submission->getIdentifier());
        $submissionPersistence->setForm($submission->getForm());
        $submissionPersistence->setDataFeedElement($submission->getDataFeedElement() === null ? '' : $submission->getDataFeedElement());
        $submissionPersistence->setDateCreated($submission->getDateCreated());

        return $submissionPersistence;
    }
}
