<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use Dbp\Relay\FormsBundle\Entity\FormData;
use Dbp\Relay\FormsBundle\Service\FormsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FormDataDataPersister extends AbstractController implements DataPersisterInterface
{
    private $api;

    public function __construct(FormsService $api)
    {
        $this->api = $api;
    }

    public function supports($data): bool
    {
        return $data instanceof FormData;
    }

    /**
     * @param mixed $data
     * @return FormData
     */
    public function persist($data)
    {
        // TODO: Check IP-address etc.

        $formData = $data;
        assert($formData instanceof FormData);

        return $this->api->createFormData($formData);
    }

    public function remove($data)
    {
    }
}
