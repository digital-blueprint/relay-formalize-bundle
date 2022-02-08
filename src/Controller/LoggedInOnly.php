<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Controller;

use Dbp\Relay\FormsBundle\Entity\Formdata;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class LoggedInOnly extends AbstractController
{
    public function __invoke(Formdata $data, Request $request): Formdata
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $data;
    }
}
