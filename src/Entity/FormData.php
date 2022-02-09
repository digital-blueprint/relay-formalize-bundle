<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\FormalizeBundle\Controller\LoggedInOnly;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get" = {
 *             "path" = "/forms/form_datas",
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         },
 *         "post" = {
 *             "method" = "POST",
 *             "path" = "/forms/form_datas",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         }
 *     },
 *     iri="https://schema.org/FormData",
 *     shortName="FormsFormData",
 *     normalizationContext={
 *         "groups" = {"FormsFormData:output"},
 *         "jsonld_embed_context" = true
 *     },
 *     denormalizationContext={
 *         "groups" = {"FormsFormData:input"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class FormData
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @ApiProperty(iri="https://schema.org/name")
     * @Groups({"FormsFormData:output", "FormsFormData:input"})
     *
     * @var string
     */
    private $data;

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public static function fromFormDataPersistence(FormDataPersistence $formDataPersistence): FormData
    {
        $formData = new FormData();
        $formData->setIdentifier($formDataPersistence->getIdentifier());
        $formData->setData($formDataPersistence->getData());

        return $formData;
    }

    /**
     * @param FormDataPersistence[] $formDataPersistences
     *
     * @return FormData[]
     */
    public static function fromFormDataPersistences(array $formDataPersistences): array
    {
        $formDatas = [];

        foreach ($formDataPersistences as $formDataPersistence) {
            $formDatas[] = self::fromFormDataPersistence($formDataPersistence);
        }

        return $formDatas;
    }
}
