<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Contracts\EventDispatcher\Event;

class CreateSubmissionPostEvent extends Event
{
    protected $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }
}
