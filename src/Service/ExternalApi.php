<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

use Dbp\Relay\FormsBundle\Entity\FormData;
use Symfony\Component\Uid\Uuid;

class ExternalApi implements FormDataProviderInterface
{
    private $formdatas;

    public function __construct(MyCustomService $service)
    {
        // Make phpstan happy
        $service = $service;

        $this->formdatas = [];
        $formdata1 = new FormData();

        $formdata1->setIdentifier((string) Uuid::v4());
        $formdata1->setData('{"name":"John Doe"}');

        $formdata2 = new FormData();
        $formdata2->setIdentifier((string) Uuid::v4());
        $formdata2->setData('{"name":"Jane Doe"}');

        $this->formdatas[] = $formdata1;
        $this->formdatas[] = $formdata2;
    }

    public function getFormDataById(string $identifier): ?FormData
    {
        foreach ($this->formdatas as $formdata) {
            if ($formdata->getIdentifier() === $identifier) {
                return $formdata;
            }
        }

        return null;
    }

    public function getFormDatas(): array
    {
        return $this->formdatas;
    }
}
