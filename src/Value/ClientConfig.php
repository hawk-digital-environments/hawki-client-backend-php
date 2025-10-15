<?php
declare(strict_types=1);


namespace Hawk\HawkiClientBackend\Value;


/**
 * Represents the client configuration, which can be either a connection or a connection request.
 * The type property indicates which one it is; this allows our js client to determine how to handle the payload.
 */
readonly class ClientConfig implements \JsonSerializable
{
    public ClientConfigType $type;
    
    public function __construct(
        public Connection|RequestConnection $payload
    )
    {
        $this->type = match (true) {
            $payload instanceof Connection => ClientConfigType::CONNECTED,
            $payload instanceof RequestConnection => ClientConfigType::CONNECTION_REQUEST,
        };
    }
    
    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'payload' => $this->payload->jsonSerialize(),
        ];
    }
}
