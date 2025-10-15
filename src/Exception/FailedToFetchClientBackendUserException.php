<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Exception;


class FailedToFetchClientBackendUserException extends \RuntimeException implements HawkiClientBackendExceptionInterface
{
    public function __construct(\Throwable $e, ?string $reason = null)
    {
        parent::__construct(
            'The request to fetch the app user from HAWKI failed.' . ($reason ? " Reason: $reason" : ''),
            0,
            $e
        );
    }
}
