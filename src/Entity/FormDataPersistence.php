<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="forms_form_data")
 */
class FormDataPersistence
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

    public static function fromFormData(FormData $formData): FormDataPersistence
    {
        $formDataPersistence = new FormDataPersistence();
        $formDataPersistence->setIdentifier($formData->getIdentifier());
        $formDataPersistence->setData($formData->getData() === null ? '' : $formData->getData());

        return $formDataPersistence;
    }
}
