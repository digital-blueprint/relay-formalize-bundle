<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Event;

use Dbp\Relay\AuthorizationBundle\Entity\ResourceActionGrant;
use Dbp\Relay\FormalizeBundle\Entity\Form;

class FormGrantAddedEvent extends AbstractFormEvent
{
    public function __construct(
        Form $form,
        private readonly ResourceActionGrant $resourceActionGrant
    ) {
        parent::__construct($form);
    }

    public function getResourceActionGrant(): ResourceActionGrant
    {
        return $this->resourceActionGrant;
    }
}
