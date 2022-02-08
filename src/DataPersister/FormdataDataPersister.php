<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use Dbp\Relay\FormsBundle\Entity\Formdata;
use Dbp\Relay\FormsBundle\Service\FormdataProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FormdataDataPersister extends AbstractController implements DataPersisterInterface
{
    private $api;

    public function __construct(FormdataProviderInterface $api)
    {
        $this->api = $api;
    }

    public function supports($data): bool
    {
        return $data instanceof Formdata;
    }

    public function persist($data): void
    {
        // TODO
    }

    public function remove($data)
    {
        // TODO
    }
}
