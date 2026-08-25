<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\FormalizeBundle\Entity\Submission;

class SubmissionGrantAddedEvent extends AbstractSubmissionEvent
{
    public function __construct(
        Submission $submission,
        private readonly ResourceActionGrant $resourceActionGrant
    ) {
        parent::__construct($submission);
    }

    public function getResourceActionGrant(): ResourceActionGrant
    {
        return $this->resourceActionGrant;
    }
}
