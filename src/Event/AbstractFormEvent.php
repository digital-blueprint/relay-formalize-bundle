<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\FormalizeBundle\Entity\Form;
use Symfony\Contracts\EventDispatcher\Event;

class AbstractFormEvent extends Event
{
    public function __construct(protected readonly Form $form)
    {
    }

    public function getForm(): Form
    {
        return $this->form;
    }
}
