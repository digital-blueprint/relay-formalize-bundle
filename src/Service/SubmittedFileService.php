<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    private ?BlobApi $blobApi = null;
    private array $submittedFileCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container,
        private bool $debug = false)
    {
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * For testing purposes.
     */
    public function getBlobApi(): BlobApi
    {
        return $this->blobApi;
    }

    /**
     * @throws BlobApiError
     */
    public function setConfig(array $config): void
    {
        $this->blobApi = BlobApi::createFromConfig($config, $this->container);
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
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            $blobFile = $this->blobApi->getFile($submittedFile->getFileDataIdentifier());
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to fetch file from file storage backend',
                self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID, [$submittedFile->getIdentifier()]);
        }
        $this->setSubmittedFileDetails($submittedFile, $blobFile);

        return $submittedFile;
    }

    public function getBinarySubmittedFileResponse(string $identifier): Response
    {
        $submittedFile = $this->getSubmittedFile($identifier);

        try {
            return $this->blobApi->getFileResponse($submittedFile->getFileDataIdentifier());
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to fetch file from file storage backend',
                self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID, [$submittedFile->getIdentifier()]);
        }
    }

    public function removeFilesBySubmissionIdentifier(string $submissionIdentifier): void
    {
        try {
            $this->blobApi->removeFiles([BlobApi::PREFIX_OPTION => $submissionIdentifier]);
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to remove submitted files from file storage backend',
                self::REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID, [$submissionIdentifier]);
        }
    }

    private function saveFile(SubmittedFile $submittedFile): void
    {
        $blobFile = new BlobFile();
        $uploadedFile = $submittedFile->getUploadedFile();
        $blobFile->setFile($uploadedFile);
        $blobFile->setFilename($uploadedFile->getClientOriginalName());
        $blobFile->setPrefix($submittedFile->getSubmission()->getIdentifier());

        try {
            $blobFile = $this->blobApi->addFile($blobFile);
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'saving file failed',
                self::SAVING_SUBMITTED_FILE_FAILED_ERROR_ID, [$submittedFile->getFilename()]);
        }

        $submittedFile->setFileDataIdentifier($blobFile->getIdentifier());
        $submittedFile->setDownloadUrl($blobFile->getContentUrl());
    }

    private function removeFile(SubmittedFile $submittedFile): void
    {
        try {
            $this->blobApi->removeFile($submittedFile->getFileDataIdentifier());
        } catch (BlobApiError) {
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
            $fileDataMap = [];
            try {
                foreach (Pagination::getAllResultsPageNumberBased(
                    function (int $currentPageNumber, int $maxNumItemsPerPage) use ($submission) {
                        return $this->blobApi->getFiles($currentPageNumber, $maxNumItemsPerPage,
                            [BlobApi::PREFIX_OPTION => $submission->getIdentifier()]);
                    }, 128) as $blobFile) {
                    $fileDataMap[$blobFile->getIdentifier()] = $blobFile;
                }
            } catch (BlobApiError) {
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

    private function setSubmittedFileDetails(SubmittedFile $submittedFile, BlobFile $blobFile): void
    {
        $submittedFile->setFileName($blobFile->getFileName());
        $submittedFile->setFileSize($blobFile->getFileSize());
        $submittedFile->setMimeType($blobFile->getMimeType());
        $submittedFile->setDownloadUrl($blobFile->getContentUrl());
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
