<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Dbp\Relay\FormsBundle\Entity\Formdata;
use Dbp\Relay\FormsBundle\Service\FormdataProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class FormdataItemDataProvider extends AbstractController implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $api;

    public function __construct(FormdataProviderInterface $api)
    {
        $this->api = $api;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Formdata::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?Formdata
    {
        return $this->api->getFormdataById($id);
    }
}
