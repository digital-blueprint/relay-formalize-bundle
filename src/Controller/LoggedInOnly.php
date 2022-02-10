<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Controller;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class LoggedInOnly extends AbstractController
{
    public function __invoke(Submission $data, Request $request): Submission
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $data;
    }
}
