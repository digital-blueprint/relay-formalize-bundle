<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 *
 * @ORM\Table(name="formalize_forms")
 */
class Form
{
    public const USERS_OPTION_NONE = 'none'; // nobody
    public const USERS_OPTION_AUTHENTICATED = 'authenticated'; // authenticated users
    public const USERS_OPTION_AUTHORIZED = 'authorized'; // users that own the form or have the required authorization attributes (e.g. 'form_id.read' == true)
    public const DEPRECATED_USERS_OPTIONS_CLIENT_SCOPE = 'client_scope'; // users who have the client scope 'formalize' (read) or 'formalize-post' (write)

    private const AVAILABLE_USERS_OPTIONS = [
        self::USERS_OPTION_NONE,
        self::USERS_OPTION_AUTHENTICATED,
        self::USERS_OPTION_AUTHORIZED,
        self::DEPRECATED_USERS_OPTIONS_CLIENT_SCOPE,
    ];

    private const FORM_INVALID_USERS_OPTION_ERROR_ID = 'formalize:form-invalid-users-option';

    /**
     * @ORM\Id
     *
     * @ORM\Column(type="string", length=50)
     *
     * @Groups({"FormalizeForm:output"})
     *
     * @var string|null
     */
    private $identifier;

    /**
     * @ORM\Column(name="name", type="string", length=256)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string|null
     */
    private $name;

    /**
     * @ORM\Column(name="date_created", type="datetime")
     *
     * @var \DateTime
     */
    private $dateCreated;

    /**
     * @ORM\Column(name="creator_id", type="string", length=50)
     *
     * @var string|null
     */
    private $creatorId;

    /**
     * @ORM\Column(name="data_feed_schema", type="text")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string|null
     */
    private $dataFeedSchema;

    /**
     * @ORM\Column(name="availability_starts", type="datetime")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var \DateTime
     */
    private $availabilityStarts;

    /**
     * @ORM\Column(name="availability_ends", type="datetime")
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var \DateTime
     */
    private $availabilityEnds;

    /**
     * @ORM\Column(name="read_form_users_option", type="string", length=50)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $readForm = self::USERS_OPTION_AUTHORIZED;

    /**
     * @ORM\Column(name="write_form_users_option", type="string", length=50)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $writeForm = self::USERS_OPTION_AUTHORIZED;

    /**
     * @ORM\Column(name="add_submissions_users_option", type="string", length=50)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $addSubmissions = self::USERS_OPTION_AUTHORIZED;

    /**
     * @ORM\Column(name="read_submissions_users_option", type="string", length=50)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $readSubmissions = self::USERS_OPTION_AUTHORIZED;

    /**
     * @ORM\Column(name="write_submissions_users_option", type="string", length=50)
     *
     * @Groups({"FormalizeForm:input", "FormalizeForm:output"})
     *
     * @var string
     */
    private $writeSubmissions = self::USERS_OPTION_AUTHORIZED;

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setDateCreated(\DateTime $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateCreated(): \DateTime
    {
        return $this->dateCreated;
    }

    public function getDataFeedSchema(): ?string
    {
        return $this->dataFeedSchema;
    }

    public function setDataFeedSchema(?string $dataFeedSchema): void
    {
        $this->dataFeedSchema = $dataFeedSchema;
    }

    public function getAvailabilityStarts(): \DateTime
    {
        return $this->availabilityStarts;
    }

    public function setAvailabilityStarts(\DateTime $availabilityStarts): void
    {
        $this->availabilityStarts = $availabilityStarts;
    }

    public function getAvailabilityEnds(): \DateTime
    {
        return $this->availabilityEnds;
    }

    public function setAvailabilityEnds(\DateTime $availabilityEnds): void
    {
        $this->availabilityEnds = $availabilityEnds;
    }

    public function getCreatorId(): ?string
    {
        return $this->creatorId;
    }

    public function setCreatorId(?string $creatorId): void
    {
        $this->creatorId = $creatorId;
    }

    public function getReadForm(): string
    {
        return $this->readForm;
    }

    public function setReadForm(string $readForm): void
    {
        $this->readForm = $readForm;
    }

    public function getWriteForm(): string
    {
        return $this->writeForm;
    }

    public function setWriteForm(string $writeForm): void
    {
        $this->writeForm = $writeForm;
    }

    public function getAddSubmissions(): string
    {
        return $this->addSubmissions;
    }

    public function setAddSubmissions(string $addSubmissions): void
    {
        $this->addSubmissions = $addSubmissions;
    }

    public function getReadSubmissions(): string
    {
        return $this->readSubmissions;
    }

    public function setReadSubmissions(string $readSubmissions): void
    {
        $this->readSubmissions = $readSubmissions;
    }

    public function getWriteSubmissions(): string
    {
        return $this->writeSubmissions;
    }

    public function setWriteSubmissions(string $writeSubmissions): void
    {
        $this->writeSubmissions = $writeSubmissions;
    }

    public function validateUsersOptions(): void
    {
        $this->validateUsersOption($this->readForm);
        $this->validateUsersOption($this->writeForm);
        $this->validateUsersOption($this->readSubmissions);
        $this->validateUsersOption($this->writeSubmissions);
    }

    /**
     * @throws ApiError throws 422 unprocessable entity if the users options is not null and none of the available users options
     */
    private function validateUsersOption(string $usersOption): void
    {
        if (!in_array($usersOption, self::AVAILABLE_USERS_OPTIONS, true)) {
            $apiError = ApiError::withDetails(Response::HTTP_UNPROCESSABLE_ENTITY,
                'invalid users option '.$usersOption, self::FORM_INVALID_USERS_OPTION_ERROR_ID, [$usersOption]);
            throw $apiError;
        }
    }
}
