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
    private $data;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTime
     */
    private $created;

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function setCreated(\DateTime $created): void
    {
        $this->created = $created;
    }

    public function getCreated(): \DateTime
    {
        return $this->created;
    }

    public static function fromSubmission(Submission $submission): SubmissionPersistence
    {
        $submissionPersistence = new SubmissionPersistence();
        $submissionPersistence->setIdentifier($submission->getIdentifier());
        $submissionPersistence->setData($submission->getData() === null ? '' : $submission->getData());

        return $submissionPersistence;
    }
}
