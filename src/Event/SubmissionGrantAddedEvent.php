<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\FormalizeBundle\Entity\Submission;

class SubmissionGrantAddedEvent extends AbstractSubmissionEvent
{
    public function __construct(
        Submission $submission,
        private readonly string $action,
        private readonly ?string $userIdentifier = null,
        private readonly ?string $groupIdentifier = null,
        private readonly ?string $dynamicGroupIdentifier = null
    ) {
        parent::__construct($submission);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getGroupIdentifier(): ?string
    {
        return $this->groupIdentifier;
    }

    public function getDynamicGroupIdentifier(): ?string
    {
        return $this->dynamicGroupIdentifier;
    }
}
