<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 */
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
#[ApiResource(
    shortName: 'FormalizeLocalizedFormName',
    operations: [],
    openapi: false,
)]
class LocalizedFormName
{
    public const TABLE_NAME = 'formalize_localized_form_names';
    public const FORM_IDENTIFIER_COLUMN_NAME = 'form_identifier';
    public const LANGUAGE_TAG_COLUMN_NAME = 'language_tag';
    public const NAME_COLUMN_NAME = 'name';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::FORM_IDENTIFIER_COLUMN_NAME, referencedColumnName: Form::IDENTIFIER_COLUMN_NAME, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Form::class, inversedBy: 'localizedNames')]
    private ?Form $form = null;

    #[ORM\Id]
    #[ORM\Column(name: self::LANGUAGE_TAG_COLUMN_NAME, type: 'string', length: 2)]
    #[Groups(['FormalizeForm:output', 'FormalizeForm:input'])]
    private ?string $languageTag = null;

    #[ORM\Column(name: self::NAME_COLUMN_NAME, type: 'string', length: 128)]
    #[Groups(['FormalizeForm:output', 'FormalizeForm:input'])]
    private ?string $name = null;

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): void
    {
        $this->form = $form;
    }

    public function getLanguageTag(): ?string
    {
        return $this->languageTag;
    }

    public function setLanguageTag(?string $languageTag): void
    {
        $this->languageTag = $languageTag;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
