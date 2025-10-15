<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Exception;


class FailedToDecryptSecretsException extends \RuntimeException implements HawkiClientBackendExceptionInterface
{
    public function __construct(
        string  $secretName,
        ?string $reason
    )
    {
        $reasonPart = $reason !== null ? ": $reason" : '';
        parent::__construct("Failed to decrypt secret '$secretName'$reasonPart");
    }
}
