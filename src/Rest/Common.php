<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class Common
{
    private const FORM_IDENTIFIER_FILTER = 'formIdentifier';

    private const REQUIRED_PARAMETER_MISSION_ERROR_ID = 'formalize:required-parameter-missing';

    /**
     * @throws ApiError 400 Bad Request in case the form identifier parameter is missing
     */
    public static function getFormIdentifier(array $filters): string
    {
        $formIdentifier = $filters[self::FORM_IDENTIFIER_FILTER] ?? null;
        if ($formIdentifier === null) {
            $apiError = ApiError::withDetails(Response::HTTP_BAD_REQUEST,
                'Parameter \''.self::FORM_IDENTIFIER_FILTER.'\' is required',
                self::REQUIRED_PARAMETER_MISSION_ERROR_ID, [self::FORM_IDENTIFIER_FILTER]);
            throw $apiError;
        }

        return $formIdentifier;
    }
}
