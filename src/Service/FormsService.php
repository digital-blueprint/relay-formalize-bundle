<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Service;

use Dbp\Relay\FormsBundle\Entity\FormData;
use Dbp\Relay\FormsBundle\Entity\FormDataPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class FormsService
{
    private $formdatas;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(MyCustomService $service, ManagerRegistry $managerRegistry)
    {
        $manager = $managerRegistry->getManager('dbp_relay_forms_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;

        // Make phpstan happy
        $service = $service;

        $this->formdatas = [];
        $formdata1 = new FormData();

        $formdata1->setIdentifier((string) Uuid::v4());
        $formdata1->setData('{"name":"John Doe"}');

        $formdata2 = new FormData();
        $formdata2->setIdentifier((string) Uuid::v4());
        $formdata2->setData('{"name":"Jane Doe"}');

        $this->formdatas[] = $formdata1;
        $this->formdatas[] = $formdata2;
    }

    public function getFormDataById(string $identifier): ?FormData
    {
        foreach ($this->formdatas as $formdata) {
            if ($formdata->getIdentifier() === $identifier) {
                return $formdata;
            }
        }

        return null;
    }

    public function getFormDatas(): array
    {
        return $this->formdatas;
    }

    public function createFormData(FormData $formData): FormData
    {
        $formDataPersistence = FormDataPersistence::fromFormData($formData);
        $formDataPersistence->setIdentifier((string) Uuid::v4());
        $formDataPersistence->setCreated(new \DateTime('now'));

        try {
            $this->em->persist($formDataPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'FormData could not be created!', 'forms:form-data-not-created', ['message' => $e->getMessage()]);
        }

        return FormData::fromFormDataPersistence($formDataPersistence);
    }
}
