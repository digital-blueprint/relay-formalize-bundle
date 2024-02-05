<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Rest\AbstractDataProcessor;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;

class FormProcessor extends AbstractDataProcessor
{
    /** @var FormalizeService */
    private $formalizeService;

    public function __construct(FormalizeService $formalizeService)
    {
        parent::__construct();

        $this->formalizeService = $formalizeService;
    }

    protected function addItem($data, array $filters): Form
    {
        return $this->formalizeService->addForm($data);
    }

    protected function removeItem($identifier, $data, array $filters): void
    {
        if ($this->getCurrentOperationName() === 'delete_form_submissions') {
            $this->formalizeService->removeAllFormSubmissions($data);
        } else {
            $this->formalizeService->removeForm($data);
        }
    }

    protected function updateItem($identifier, $data, $previousData, array $filters)
    {
        $this->formalizeService->updateForm($data);
    }

    protected function isUserGrantedOperationAccess(int $operation): bool
    {
        return $this->isAuthenticated()
            && $this->getCurrentUserAttribute('ROLE_DEVELOPER');
    }
}
