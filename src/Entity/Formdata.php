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
 *         }
 *     },
 *     itemOperations={
 *         "get" = {
 *             "path" = "/forms/formdatas/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         },
 *         "put" = {
 *             "path" = "/forms/formdatas/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         },
 *         "delete" = {
 *             "path" = "/forms/formdatas/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Forms"},
 *             },
 *         },
 *         "loggedin_only" = {
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "method" = "GET",
 *             "path" = "/forms/formdatas/{identifier}/loggedin-only",
 *             "controller" = LoggedInOnly::class,
 *             "openapi_context" = {
 *                 "summary" = "Only works when logged in.",
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
    private $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
