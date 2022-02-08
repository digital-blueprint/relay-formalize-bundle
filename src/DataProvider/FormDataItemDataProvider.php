<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Dbp\Relay\FormsBundle\Entity\FormData;
use Dbp\Relay\FormsBundle\Service\FormsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class FormDataItemDataProvider extends AbstractController implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $api;

    public function __construct(FormsService $api)
    {
        $this->api = $api;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return FormData::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?FormData
    {
        return $this->api->getFormDataById($id);
    }
}
