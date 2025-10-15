<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Value;

/**
 * Represents the client configuration when there is no established connection yet.
 * This will tell the frontend to show a connection request screen.
 */
readonly class RequestConnection implements \JsonSerializable
{
    public function __construct(
        private array $data
    )
    {
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
