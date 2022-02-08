<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

use Dbp\Relay\FormsBundle\Entity\Formdata;

interface FormdataProviderInterface
{
    public function getFormdataById(string $identifier): ?Formdata;

    public function getFormdatas(): array;
}
