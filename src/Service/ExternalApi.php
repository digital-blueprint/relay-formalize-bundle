<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

use Dbp\Relay\FormsBundle\Entity\Formdata;

class ExternalApi implements FormdataProviderInterface
{
    private $formdatas;

    public function __construct(MyCustomService $service)
    {
        // Make phpstan happy
        $service = $service;

        $this->formdatas = [];
        $formdata1 = new Formdata();
        $formdata1->setIdentifier('graz');
        $formdata1->setName('Graz');

        $formdata2 = new Formdata();
        $formdata2->setIdentifier('vienna');
        $formdata2->setName('Vienna');

        $this->formdatas[] = $formdata1;
        $this->formdatas[] = $formdata2;
    }

    public function getFormdataById(string $identifier): ?Formdata
    {
        foreach ($this->formdatas as $formdata) {
            if ($formdata->getIdentifier() === $identifier) {
                return $formdata;
            }
        }

        return null;
    }

    public function getFormdatas(): array
    {
        return $this->formdatas;
    }
}
