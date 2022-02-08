<?php

declare(strict_types=1);

namespace Dbp\Relay\FormsBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\FormsBundle\Controller\LoggedInOnly;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get" = {
 *             "path" = "/forms/formdatas",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         },
 *         "post" = {
 *             "method" = "POST",
 *             "path" = "/forms/formdatas",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         }
 *     },
 *     iri="https://schema.org/Formdata",
 *     shortName="FormsFormdata",
 *     normalizationContext={
 *         "groups" = {"FormsFormdata:output"},
 *         "jsonld_embed_context" = true
 *     },
 *     denormalizationContext={
 *         "groups" = {"FormsFormdata:input"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class Formdata
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @ApiProperty(iri="https://schema.org/name")
     * @Groups({"FormsFormdata:output", "FormsFormdata:input"})
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
}
