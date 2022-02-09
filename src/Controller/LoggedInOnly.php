<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Controller;

use Dbp\Relay\FormalizeBundle\Entity\FormData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class LoggedInOnly extends AbstractController
{
    public function __invoke(FormData $data, Request $request): FormData
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $data;
    }
}
