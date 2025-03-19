<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Dbp\Relay\FormalizeBundle\Rest\SubmittedFileDownloadController;
use Dbp\Relay\FormalizeBundle\Rest\SubmittedFileProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Table(name: 'formalize_submitted_files')]
#[ORM\Entity]
#[ApiResource(
    shortName: 'FormalizeSubmittedFile',
    types: ['https://schema.org/DigitalDocument'],
    operations: [
        new Get(
            uriTemplate: '/formalize/submitted-files/{identifier}',
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            normalizationContext: [
                'groups' => ['FormalizeSubmittedFile:output', 'FormalizeSubmittedFile:file_info_output'],
                'jsonld_embed_context' => true,
            ],
            provider: SubmittedFileProvider::class,
        ),
        new Get(
            uriTemplate: '/formalize/submitted-files/{identifier}/download',
            controller: SubmittedFileDownloadController::class,
            openapiContext: [
                'tags' => ['Formalize'],
            ],
            read: false,
        ),
    ],
    normalizationContext: [
        'groups' => ['FormalizeSubmittedFile:output'],
        'jsonld_embed_context' => true,
        'preserve_empty_objects' => true,
    ],
    denormalizationContext: [
        'groups' => ['FormalizeSubmittedFile:input'],
    ],
)]
class SubmittedFile
{
    #[ORM\Id]
    #[ORM\Column(name: 'identifier', type: 'string', length: 50, nullable: false)]
    #[Groups(['FormalizeSubmittedFile:output'])]
    private ?string $identifier = null;

    #[ORM\JoinColumn(name: 'submission_identifier', referencedColumnName: 'identifier', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Submission::class, inversedBy: 'submittedFiles')]
    private ?Submission $submission = null;

    #[ORM\Column(name: 'file_attribute_name', type: 'string', length: 128, nullable: false)]
    #[Groups(['FormalizeSubmittedFile:output'])]
    private ?string $fileAttributeName = null;

    #[ORM\Column(name: 'file_data_identifier', type: 'string', length: 50, nullable: false)]
    private ?string $fileDataIdentifier = null;

    #[Groups(['FormalizeSubmittedFile:file_info_output'])]
    private ?string $fileName = null;

    #[Groups(['FormalizeSubmittedFile:file_info_output'])]
    private int $fileSize = 0;

    #[Groups(['FormalizeSubmittedFile:file_info_output'])]
    private ?string $mimeType = null;

    private ?UploadedFile $uploadedFile = null;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getFileDataIdentifier(): ?string
    {
        return $this->fileDataIdentifier;
    }

    public function getSubmission(): ?Submission
    {
        return $this->submission;
    }

    public function setSubmission(?Submission $submission): void
    {
        $this->submission = $submission;
    }

    public function getFileAttributeName(): ?string
    {
        return $this->fileAttributeName;
    }

    public function setFileAttributeName(?string $fileAttributeName): void
    {
        $this->fileAttributeName = $fileAttributeName;
    }

    public function setFileDataIdentifier(?string $fileDataIdentifier): void
    {
        $this->fileDataIdentifier = $fileDataIdentifier;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getUploadedFile(): ?UploadedFile
    {
        return $this->uploadedFile;
    }

    public function setUploadedFile(?UploadedFile $uploadedFile): void
    {
        $this->uploadedFile = $uploadedFile;
    }
}
