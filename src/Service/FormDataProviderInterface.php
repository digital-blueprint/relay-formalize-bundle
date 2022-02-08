<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

use Dbp\Relay\FormsBundle\Entity\FormData;

interface FormDataProviderInterface
{
    public function getFormDataById(string $identifier): ?FormData;

    public function getFormDatas(): array;
}
