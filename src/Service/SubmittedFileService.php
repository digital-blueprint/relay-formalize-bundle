<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Service;

use Dbp\Relay\BlobLibrary\Api\BlobApi;
use Dbp\Relay\BlobLibrary\Api\BlobApiError;
use Dbp\Relay\BlobLibrary\Api\BlobFile;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Entity\SubmittedFile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

class SubmittedFileService implements LoggerAwareInterface, ResetInterface
{
    use LoggerAwareTrait;

    private const SAVING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:saving-submitted-file-failed';
    private const SUBMITTED_FILE_NOT_FOUND_ERROR_ID = 'formalize:submitted-file-not-found';
    private const SUBMITTED_FILE_NOT_FOUND_IN_FILE_STORAGE_BACKEND_ERROR_ID = 'formalize:submitted-file-not-found-in-file-storage-backend';
    private const GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID = 'formalize:getting-saved-file-data-failed';
    private const GETTING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:getting-submitted-file-failed';
    private const REMOVING_SUBMITTED_FILE_FAILED_ERROR_ID = 'formalize:removing-submitted-file-failed';
    private const REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID = 'formalize:removing-submitted-files-failed';

    private const BUCKET_METADATA_FILE_PREFIX = 'bucket_metadata';
    private const SUBMITTED_FILE_DATA_VERSION = 1;

    private ?BlobApi $blobApi = null;
    private array $submittedFileCache = [];
    private ?string $cachedFilesFormIdentifier = null;
    private ?string $cachedFilesSubmissionsIdentifier = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container,
        private bool $debug = false)
    {
    }

    /**
     * Test utility function.
     */
    public static function createSubmittedFilePrefixForSubmission(Submission $submission): string
    {
        return self::createSubmittedFilePrefix($submission->getForm()->getIdentifier(), $submission->getIdentifier());
    }

    private static function createSubmittedFilePrefix(string $formIdentifier, string $submissionIdentifier): string
    {
        return $formIdentifier.'/'.$submissionIdentifier;
    }

    public function reset()
    {
        $this->submittedFileCache = [];
        $this->cachedFilesFormIdentifier = null;
        $this->cachedFilesSubmissionsIdentifier = null;
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

    public function removeFilesBySubmission(Submission $submission): void
    {
        try {
            $options = [];
            BlobApi::setPrefix($options, self::createSubmittedFilePrefixForSubmission($submission));
            $this->blobApi->removeFiles($options);
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to remove submitted files of submission from file storage backend',
                self::REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID, [$submission->getIdentifier()]);
        }
    }

    public function removeFilesByForm(Form $form): void
    {
        try {
            $options = [];
            BlobApi::setPrefix($options, $form->getIdentifier());
            BlobApi::setPrefixStartsWith($options, true);
            $this->blobApi->removeFiles($options);
        } catch (BlobApiError) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                'failed to remove submitted files of form from file storage backend',
                self::REMOVING_SUBMITTED_FILES_FAILED_ERROR_ID, [$form->getIdentifier()]);
        }
    }

    private function saveFile(SubmittedFile $submittedFile): void
    {
        $blobFile = new BlobFile();
        $uploadedFile = $submittedFile->getUploadedFile();
        $blobFile->setFile($uploadedFile);
        $blobFile->setFilename($uploadedFile->getClientOriginalName());
        $blobFile->setPrefix(
            self::createSubmittedFilePrefix(
                $submittedFile->getSubmission()->getForm()->getIdentifier(),
                $submittedFile->getSubmission()->getIdentifier()
            )
        );

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

    public function setSubmittedFilesDetails(Submission $submission, bool $cacheAllSubmittedFilesOfForm = false): void
    {
        $filesSubmittedOnPastRequests = $submission->getSubmittedFiles()->filter(
            function (SubmittedFile $submittedFile): bool {
                return $submittedFile->getUploadedFile() === null;
            });

        if ($filesSubmittedOnPastRequests->isEmpty()) {
            // all files have been uploaded during this request -> details are already available -> nothing to do
            return;
        }

        $submissionIdentifier = $submission->getIdentifier();
        $formIdentifier = $submission->getForm()->getIdentifier();

        if (($cacheAllSubmittedFilesOfForm
                && ($this->cachedFilesFormIdentifier === null
                    || $this->cachedFilesFormIdentifier !== $formIdentifier))
            || (false === $cacheAllSubmittedFilesOfForm
                && ($this->cachedFilesSubmissionsIdentifier === null
                    || $this->cachedFilesSubmissionsIdentifier !== $submissionIdentifier))) {
            try {
                $options = [];
                if ($cacheAllSubmittedFilesOfForm) {
                    BlobApi::setPrefix($options, $formIdentifier);
                    BlobApi::setPrefixStartsWith($options, true);
                } else {
                    BlobApi::setPrefix($options, self::createSubmittedFilePrefix($formIdentifier, $submission->getIdentifier()));
                }

                foreach (Pagination::getAllResultsPageNumberBased(
                    function (int $currentPageNumber, int $maxNumItemsPerPage) use ($options) {
                        return $this->blobApi->getFiles($currentPageNumber, $maxNumItemsPerPage, $options);
                    }, 100) as $blobFile) {
                    $this->submittedFileCache[$blobFile->getIdentifier()] = $blobFile;
                }

                if ($cacheAllSubmittedFilesOfForm) {
                    $this->cachedFilesFormIdentifier = $formIdentifier;
                } else {
                    $this->cachedFilesSubmissionsIdentifier = $submissionIdentifier;
                }
            } catch (BlobApiError) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'failed to fetch files from file storage backend',
                    self::GETTING_SAVED_FILE_DATA_FAILED_ERROR_ID);
            }
        }

        /** @var SubmittedFile $priorlySubmittedFile */
        foreach ($filesSubmittedOnPastRequests as $priorlySubmittedFile) {
            if (null === ($fileData = $this->submittedFileCache[$priorlySubmittedFile->getFileDataIdentifier()] ?? null)) {
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR,
                    'submitted file not found in file storage backend',
                    self::SUBMITTED_FILE_NOT_FOUND_IN_FILE_STORAGE_BACKEND_ERROR_ID, [$priorlySubmittedFile->getIdentifier()]);
            }
            $this->setSubmittedFileDetails($priorlySubmittedFile, $fileData);
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

    /**
     * @throws BlobApiError
     */
    private function tryGetBucketMetadataFile(): ?BlobFile
    {
        $options = [];
        BlobApi::setPrefix($options, self::BUCKET_METADATA_FILE_PREFIX);
        /** @var BlobFile[] $blobFiles */
        $blobFiles = iterator_to_array($this->blobApi->getFiles(options: $options));

        $numberOfBlobFiles = count($blobFiles);
        if ($numberOfBlobFiles > 1) {
            throw new \Exception('more than one blob file with prefix '.self::BUCKET_METADATA_FILE_PREFIX.' found in file storage backend');
        }

        return $blobFiles[0] ?? null;
    }

    /**
     * @throws \Throwable
     */
    public function migrateToCurrentFileDataVersion(OutputInterface $output): void
    {
        if (self::SUBMITTED_FILE_DATA_VERSION !== $this->getSubmittedFileDataVersion()) {
            $output->write('FORMALIZE: updating submitted file data to version '.self::SUBMITTED_FILE_DATA_VERSION.'... ');
            $maxNumItemsPerPage = 100;
            $currentPage = 1;

            /** @var BlobFile $blobFile */
            foreach ($this->blobApi->getFiles($currentPage, $maxNumItemsPerPage) as $blobFile) {
                $submissionIdentifier = $blobFile->getPrefix();
                if (Uuid::isValid($submissionIdentifier)) {
                    $submission = $this->entityManager
                        ->getRepository(Submission::class)
                        ->find($submissionIdentifier);
                    if ($submission === null) {
                        $output->writeln('WARNING: submission '.$submissionIdentifier.' for submitted file '.$blobFile->getIdentifier().' not found');
                        continue;
                    }
                    $blobFile->setPrefix(
                        self::createSubmittedFilePrefix($submission->getForm()->getIdentifier(), $submissionIdentifier));
                    $this->blobApi->updateFile($blobFile);
                }
            }

            $this->setSubmittedFileDataVersion(self::SUBMITTED_FILE_DATA_VERSION);
            $output->writeln('DONE');
        } else {
            $output->writeln('FORMALIZE: submitted file data is up to date (version '.self::SUBMITTED_FILE_DATA_VERSION.')');
        }
    }

    /**
     * @return int|null null if it hasn't been set yet
     *
     * @throws \Throwable
     */
    private function getSubmittedFileDataVersion(): ?int
    {
        $blobFile = $this->tryGetBucketMetadataFile();

        $fileDataVersion = null;
        if ($blobFile !== null) {
            $metadata = json_decode($blobFile->getMetadata(), true, flags: JSON_THROW_ON_ERROR);
            $fileDataVersion = $metadata['version'] ?? null;
            if ($fileDataVersion === null) {
                throw new \Exception('version not found in metadata of blob file with prefix '.self::BUCKET_METADATA_FILE_PREFIX);
            }
        }

        return $fileDataVersion;
    }

    /**
     * @throws \Throwable
     */
    private function setSubmittedFileDataVersion(int $fileDataVersion): void
    {
        $bucketMetadataFile = $this->tryGetBucketMetadataFile();
        if ($bucketMetadataFile === null) {
            $bucketMetadataFile = new BlobFile();
            $bucketMetadataFile->setPrefix(self::BUCKET_METADATA_FILE_PREFIX);
            $bucketMetadataFile->setFile(self::BUCKET_METADATA_FILE_PREFIX); // dummy content
            $bucketMetadataFile->setFileName(self::BUCKET_METADATA_FILE_PREFIX.'.txt');
            $bucketMetadataFile->setMetadata(json_encode(['version' => $fileDataVersion], JSON_THROW_ON_ERROR));
            $this->blobApi->addFile($bucketMetadataFile);
        } else {
            $bucketMetadataFile->setMetadata(json_encode(['version' => $fileDataVersion], JSON_THROW_ON_ERROR));
            $this->blobApi->updateFile($bucketMetadataFile);
        }
    }
}
