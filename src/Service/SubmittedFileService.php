<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\BlobBundle\Api\FileApi;
use Dbp\Relay\BlobBundle\Api\FileApiException;
use Dbp\Relay\BlobBundle\Entity\FileData;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\DependencyInjection\Configuration;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class SubmittedFileService
{
    private const SAVING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:saving-submitted-file-failed';
    private const ADDING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:adding-submitted-file-failed';
    private const SUBMITTED_FILE_NOT_FOUND_ERROR_ID = 'formalize:submitted-file-not-found';
    private const SUBMITTED_FILE_NOT_FOUND_IN_FILE_STORAGE_BACKEND_ERROR_ID = 'formalize:submitted-file-not-found-in-file-storage-backend';
    private const GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID = 'formalize:getting-saved-file-data-failed';
    private const GETTING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:getting-submitted-file-failed';

    private ?string $submittedFilesBucketId = null;

    private array $submittedFileCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileApi $fileApi,
        private bool $debug = false)
    {
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function setConfig(array $config): void
    {
        $this->submittedFilesBucketId = $config[Configuration::SUBMITTED_FILES_BUCKET_ID] ?? null;
    }

    /**
     * @param UploadedFile[] $uploadedFiles
     */
    public function addSubmittedFilesToSubmission(string $fileAttributeName, array $uploadedFiles, Submission $submission): void
    {
        foreach ($uploadedFiles as $uploadedFile) {
            $submittedFile = new SubmittedFile();
            $submittedFile->setIdentifier((string) Uuid::v7());
            $submittedFile->setSubmission($submission);
            $submittedFile->setFileAttributeName($fileAttributeName);
            $submittedFile->setUploadedFile($uploadedFile);
            $submittedFile->setFileName($uploadedFile->getClientOriginalName());
            $submittedFile->setFileSize($uploadedFile->getSize());
            $submittedFile->setMimeType($uploadedFile->getMimeType());

            $submission->addSubmittedFile($submittedFile);
        }
    }

    public function removeSubmittedFilesFromSubmission(array $submittedFileIdentifiers, Submission $submission): void
    {
        foreach ($submittedFileIdentifiers as $submittedFileIdentifier) {
            if (($submittedFile = $submission->tryGetSubmittedFile($submittedFileIdentifier)) !== null) {
                $submission->removeSubmittedFile($submittedFile);
            }
            // TODO: should we complain if submitted file is not found?
        }
    }

    public function removeSubmittedFiles(Submission $submission): void
    {
        foreach ($submission->getSubmittedFiles() as $submittedFile) {
            $submission->removeSubmittedFile($submittedFile);
        }
    }

    /**
     * @throws \Exception
     */
    public function commitSubmittedFileChanges(Submission $submission): void
    {
        try {
            foreach ($submission->getSubmittedFilesToAdd() as $submittedFileToAdd) {
                $submittedFileToAdd->setFileDataIdentifier($this->saveFile($submittedFileToAdd));
            }
            foreach ($submission->getSubmittedFilesToRemove() as $submittedFileToRemove) {
                $this->removeFile($submittedFileToRemove);
            }
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            // TODO: think of a reasonable rollback strategy
            foreach ($submission->getSubmittedFilesToAdd() as $submittedFile) {
                try {
                    $this->removeFile($submittedFile);
                } catch (\Exception) { // ignore
                }
            }
            throw $exception;
        }
    }

    /**
     * @throws ApiError
     */
    public function getSubmittedFileByIdentifier(string $identifier): SubmittedFile
    {
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            $fileData = $this->fileApi->getFile($submittedFile->getFileDataIdentifier());
        } catch (FileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to fetch file from file storage backend',
                self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID, [$submittedFile->getIdentifier()]);
        }

        $this->setSubmittedFileDetails($submittedFile, $fileData);

        return $submittedFile;
    }

    public function getBinarySubmittedFileResponse(string $identifier): Response
    {
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            return $this->fileApi->getBinaryFileResponse($submittedFile->getFileDataIdentifier());
        } catch (FileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to fetch file from file storage backend',
                self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID, [$submittedFile->getIdentifier()]);
        }
    }

    private function saveFile(SubmittedFile $submittedFile): string
    {
        if (!$this->submittedFilesBucketId) {
            throw new \RuntimeException('Formalize bundle config: Submitted files bucket ID is not configured');
        }

        $fileData = new FileData();
        $uploadedFile = $submittedFile->getUploadedFile();
        $fileData->setFile($uploadedFile);
        $fileData->setFilename($uploadedFile->getClientOriginalName());
        $fileData->setPrefix($submittedFile->getSubmission()->getIdentifier());
        $fileData->setBucketId($this->submittedFilesBucketId);

        try {
            $this->fileApi->addFile($fileData);
        } catch (FileApiException $fileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'saving file failed',
                self::SAVING_SUBMITTED_FILE_FAILED_ERROR_ID, [$submittedFile->getFilename()]);
        }

        return $fileData->getIdentifier();
    }

    private function removeFile(SubmittedFile $submittedFile): void
    {
        if (!$this->submittedFilesBucketId) {
            throw new \RuntimeException('Formalize bundle config: Submitted files bucket ID is not configured');
        }

        try {
            $this->fileApi->removeFile($submittedFile->getFileDataIdentifier());
        } catch (FileApiException $fileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'removing file failed',
                self::SAVING_SUBMITTED_FILE_FAILED_ERROR_ID, [$submittedFile->getFilename()]);
        }
    }

    public function setSubmittedFilesDetails(Submission $submission): void
    {
        $priorlySubmittedFiles = $submission->getSubmittedFiles()->filter(function (SubmittedFile $submittedFile): bool {
            return $submittedFile->getUploadedFile() === null;
        });

        if (false === $priorlySubmittedFiles->isEmpty()) {
            $fileDataMap = [];
            try {
                foreach ($this->fileApi->getFiles($this->submittedFilesBucketId, [FileApi::PREFIX_OPTION => $submission->getIdentifier()]) as $fileData) {
                    $fileDataMap[$fileData->getIdentifier()] = $fileData;
                }
            } catch (FileApiException) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'failed to fetch files from file storage backend',
                    self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID);
            }

            /** @var SubmittedFile $priorlySubmittedFile */
            foreach ($priorlySubmittedFiles as $priorlySubmittedFile) {
                if (($fileData = $fileDataMap[$priorlySubmittedFile->getFileDataIdentifier()] ?? null) === null) {
                    throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                        'submitted file not found in file storage backend',
                        self::SUBMITTED_FILE_NOT_FOUND_IN_FILE_STORAGE_BACKEND_ERROR_ID, [$priorlySubmittedFile->getIdentifier()]);
                }
                $this->setSubmittedFileDetails($priorlySubmittedFile, $fileData);
            }
        }
    }

    private function setSubmittedFileDetails(SubmittedFile $submittedFile, FileData $fileData): void
    {
        $submittedFile->setFileName($fileData->getFileName());
        $submittedFile->setFileSize($fileData->getFileSize());
        $submittedFile->setMimeType($fileData->getMimeType());
    }

    /**
     * @throws ApiError
     */
    private function getSubmittedFile(string $identifier): SubmittedFile
    {
        $submittedFile = $this->submittedFileCache[$identifier] ?? null;
        if ($submittedFile === null) {
            try {
                $submittedFile = $this->entityManager
                    ->getRepository(SubmittedFile::class)
                    ->find($identifier);
                $this->submittedFileCache[$identifier] = $submittedFile;
            } catch (\Exception $exception) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(),
                    self::GETTING_SUBMITTED_FILE_FAILED_ERROR_ID);
            }
        }

        if ($submittedFile === null) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Submitted file not found',
                self::SUBMITTED_FILE_NOT_FOUND_ERROR_ID, [$identifier]);
        }

        return $submittedFile;
    }
}
