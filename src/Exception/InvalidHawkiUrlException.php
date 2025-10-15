<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Exception;

class InvalidHawkiUrlException extends \InvalidArgumentException implements HawkiClientBackendExceptionInterface
{
    public function __construct(string $givenUrl)
    {
        parent::__construct(
            sprintf(
                'The given HAWKI server URL "%s" is invalid. It must be a valid URL.',
                $givenUrl
            )
        );
    }
}
