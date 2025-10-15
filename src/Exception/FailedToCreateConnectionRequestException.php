<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Exception;


class FailedToCreateConnectionRequestException extends \RuntimeException implements HawkiClientBackendExceptionInterface
{
    public function __construct(\Throwable $e)
    {
        parent::__construct(
            'The request to create a new app user connect request failed.',
            0,
            $e
        );
    }
}
