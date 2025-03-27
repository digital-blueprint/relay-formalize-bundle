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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class SubmittedFileService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const SAVING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:saving-submitted-file-failed';
    private const SUBMITTED_FILE_NOT_FOUND_ERROR_ID = 'formalize:submitted-file-not-found';
    private const SUBMITTED_FILE_NOT_FOUND_IN_FILE_STORAGE_BACKEND_ERROR_ID = 'formalize:submitted-file-not-found-in-file-storage-backend';
    private const GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID = 'formalize:getting-saved-file-data-failed';
    private const GETTING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:getting-submitted-file-failed';
    private const REMOVING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:removing-submitted-file-failed';
    private const REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID = 'formalize:removing-submitted-files-failed';

    private ?string $submittedFilesBucketId = null;

    private array $submittedFileCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileApi $fileApi,
        private readonly RequestStack $requestStack,
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

    public function removeSubmittedFilesFromSubmission(array $submittedFileIdentifiersToRemove, Submission $submission): void
    {
        if ([] !== $submittedFileIdentifiersToRemove) {
            foreach ($submittedFileIdentifiersToRemove as $submittedFileIdentifier) {
                if (($submittedFile = $submission->tryGetSubmittedFile($submittedFileIdentifier)) !== null) {
                    $submission->removeSubmittedFile($submittedFile);
                }
                // TODO: should we complain if submitted file is not found?
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function applySubmittedFileChanges(Submission $submission): void
    {
        foreach ($submission->getSubmittedFilesToAdd() as $submittedFileToAdd) {
            $this->saveFile($submittedFileToAdd);
        }
        foreach ($submission->getSubmittedFilesToRemove() as $submittedFileToRemove) {
            $this->removeFile($submittedFileToRemove);
        }
        $submission->resetSubmittedFileChangesToApply();
    }

    /**
     * @throws ApiError
     */
    public function getSubmittedFileByIdentifier(string $identifier): SubmittedFile
    {
        $this->ensureSubmittedFileBucket();
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            $fileData = $this->fileApi->getFile($submittedFile->getFileDataIdentifier(),
                [FileApi::BASE_URL_OPTION => $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost()]);
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
        $this->ensureSubmittedFileBucket();
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            return $this->fileApi->getBinaryFileResponse($submittedFile->getFileDataIdentifier());
        } catch (FileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to fetch file from file storage backend',
                self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID, [$submittedFile->getIdentifier()]);
        }
    }

    public function removeFilesBySubmissionIdentifier(string $submissionIdentifier): void
    {
        $this->ensureSubmittedFileBucket();
        try {
            // TODO: FileApi: getFiles: add Filter parameter; offer collection remove (with Filter parameter)
            foreach ($this->fileApi->getFiles($this->submittedFilesBucketId, [FileApi::PREFIX_OPTION => $submissionIdentifier]) as $fileData) {
                $this->fileApi->removeFile($fileData->getIdentifier());
            }
        } catch (FileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'failed to remove submitted files from file storage backend',
                self::REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID, [$submissionIdentifier]);
        }
    }

    private function saveFile(SubmittedFile $submittedFile): void
    {
        $this->ensureSubmittedFileBucket();

        $fileData = new FileData();
        $uploadedFile = $submittedFile->getUploadedFile();
        $fileData->setFile($uploadedFile);
        $fileData->setFilename($uploadedFile->getClientOriginalName());
        $fileData->setPrefix($submittedFile->getSubmission()->getIdentifier());
        $fileData->setBucketId($this->submittedFilesBucketId);

        try {
            $fileData = $this->fileApi->addFile($fileData);
        } catch (FileApiException $fileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'saving file failed',
                self::SAVING_SUBMITTED_FILE_FAILED_ERROR_ID, [$submittedFile->getFilename()]);
        }

        $submittedFile->setFileDataIdentifier($fileData->getIdentifier());
        $submittedFile->setDownloadUrl($fileData->getContentUrl());
    }

    private function removeFile(SubmittedFile $submittedFile): void
    {
        $this->ensureSubmittedFileBucket();

        try {
            $this->fileApi->removeFile($submittedFile->getFileDataIdentifier());
        } catch (FileApiException $fileApiException) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'removing file failed',
                self::REMOVING_SUBMITTED_FILE_FAILED_ERROR_ID, [$submittedFile->getFilename()]);
        }
    }

    public function setSubmittedFilesDetails(Submission $submission): void
    {
        $priorlySubmittedFiles = $submission->getSubmittedFiles()->filter(function (SubmittedFile $submittedFile): bool {
            return $submittedFile->getUploadedFile() === null;
        });

        if (false === $priorlySubmittedFiles->isEmpty()) {
            $this->ensureSubmittedFileBucket();

            $fileDataMap = [];
            try {
                foreach ($this->fileApi->getFiles($this->submittedFilesBucketId, [
                    FileApi::PREFIX_OPTION => $submission->getIdentifier(),
                    FileApi::BASE_URL_OPTION => $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost(),
                ]) as $fileData) {
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
        $submittedFile->setDownloadUrl($fileData->getContentUrl());
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

    private function ensureSubmittedFileBucket(): void
    {
        if (!$this->submittedFilesBucketId) {
            throw new \RuntimeException('Formalize bundle config: Submitted files bucket ID is not configured');
        }
    }
}
