<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractSubmissionEvent extends Event
{
    public function __construct(protected readonly Submission $submission)
    {
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }
}
