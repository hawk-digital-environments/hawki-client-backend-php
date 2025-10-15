<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Exception;


class ConnectionNotDecryptedException extends \RuntimeException implements HawkiClientBackendExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Connection must be decrypted before usage.');
    }
}
