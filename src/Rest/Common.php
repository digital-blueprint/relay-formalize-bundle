<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class Common
{
    public const FORM_IDENTIFIER_PARAMETER = 'formIdentifier';
    private const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'formalize:required-parameter-missing';

    /**
     * @throws ApiError 400 Bad Request in case the form identifier parameter is missing
     */
    public static function getFormIdentifier(array $filters): string
    {
        $formIdentifier = self::tryGetFormIdentifierInternal($filters);
        if ($formIdentifier === null) {
            $apiError = ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'Parameter \''.self::FORM_IDENTIFIER_PARAMETER.'\' is required',
                self::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::FORM_IDENTIFIER_PARAMETER]);
            throw $apiError;
        }

        return $formIdentifier;
    }

    public static function tryGetFormIdentifier(array $filters): ?string
    {
        return self::tryGetFormIdentifierInternal($filters);
    }

    private static function tryGetFormIdentifierInternal(array $filters): ?string
    {
        return $filters[self::FORM_IDENTIFIER_PARAMETER] ?? null;
    }

    /**
     * Removes any sub-second part of a datetime.
     *
     * This is needed since we only store seconds in the DB atm, so round tripping via the DB
     * removes sub-seconds, while everything created in PHP has them. To avoid tests breaking
     * we also round in PHP when setting things.
     */
    public static function removeSubSeconds(\DateTimeInterface $date): \DateTimeInterface
    {
        $new = \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone('UTC'));

        return $new->setTime(
            (int) $new->format('H'),
            (int) $new->format('i'),
            (int) $new->format('s'),
            0
        );
    }
}
