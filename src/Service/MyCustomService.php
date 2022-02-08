<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

class MyCustomService
{
    private $someConfig;

    public function __construct(string $someConfig)
    {
        $this->$someConfig = $someConfig;
    }
}
