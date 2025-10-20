# Chapter 3: `ClientConfig`: The Standardized Payload

In [Chapter 2: Connection State: `Connection` & `RequestConnection`](connection-state-connection-requestconnection-1400742608.md), we learned that our backend can prepare two different kinds of "ID cards" for a user: a `Connection` for existing members and a `RequestConnection` for new guests.

But after your backend encrypts one of these and sends it to the frontend, a new question arises: How does the frontend client know which kind of card it received? Should it show the user's dashboard, or should it display a QR code to start a new connection?

The answer lies in a simple but powerful object: `ClientConfig`.

### The Packing Slip for Your Data

Imagine you're sending a package. You could just put a gift in a box and ship it. But what if the recipient doesn't know what's inside? They'd have to open it and guess. A much better way is to attach a **packing slip** to the outside of the box that says, "This box contains a birthday gift."

The `ClientConfig` object is exactly that: a standardized packing slip for the data being sent to the frontend. It wraps the `Connection` or `RequestConnection` object and adds a clear label—a `type` field—that tells the frontend exactly what's inside.

This creates a "message contract" between your backend and the frontend:
*   **The Backend Promises:** "I will always package the data in this standard format with a `type` label."
*   **The Frontend Knows:** "After I decrypt the message, I can always check the `type` label first to understand how to handle the data."

After decryption, the frontend will see a simple JSON structure like one of these:

**Scenario 1: User is already connected**
```json
{
  "type": "connected",
  "payload": {
    "device": { "...": "..." },
    "secrets": { "...": "..." }
  }
}
```
The `type` is `"connected"`, so the frontend knows the `payload` contains the user's secure details from a `Connection` object. It can now proceed to use HAWKI features.

**Scenario 2: User needs to connect**
```json
{
  "type": "connect_request",
  "payload": {
    "url": "https://hawki.example.com/connect/....",
    "expires_at": "..."
  }
}
```
The `type` is `"connect_request"`, so the frontend knows the `payload` contains the details from a `RequestConnection` object needed to generate a QR code.

### A Look Inside the `ClientConfig` Class

The code that creates this helpful structure is refreshingly simple. Its main job is to add that `type` label based on the data it's given.

Let's look at its constructor in `src/Value/ClientConfig.php`.

```php
// File: src/Value/ClientConfig.php

public function __construct(
    public Connection|RequestConnection $payload
) {
    $this->type = match (true) {
        $payload instanceof Connection => ClientConfigType::CONNECTED,
        $payload instanceof RequestConnection => ClientConfigType::CONNECTION_REQUEST,
    };
}
```
That's it! When you create a `ClientConfig` object, you give it either a `Connection` or a `RequestConnection`. The code then uses a `match` expression to check the type of the `$payload` and sets the `type` property accordingly.

The values `ClientConfigType::CONNECTED` and `ClientConfigType::CONNECTION_REQUEST` come from a simple helper called an `Enum`, which holds the official string values.

```php
// File: src/Value/ClientConfigType.php

enum ClientConfigType: string
{
    case CONNECTED = 'connected';
    case CONNECTION_REQUEST = 'connect_request';
}
```
This ensures the `type` string is always consistent and spelled correctly.

### The Journey of a Payload

This `ClientConfig` wrapper is the final step on the backend before the data is encrypted and sent away. Let's visualize the entire journey, from its creation in your backend to its interpretation in the frontend.

```mermaid
graph TD
    subgraph "Your Backend"
        A[Get `Connection` or `RequestConnection`] --> B{Wrap in `ClientConfig`};
        B --> C[Convert to JSON string];
        C --> D[Encrypt for Frontend];
    end

    D -- " Send Secure Package " --> E;

    subgraph "Frontend Client"
        E[Receive Secure Package] --> F[Decrypt Package];
        F --> G{Read "type" property};
        G -- " type: connected " --> H[Show Connected UI];
        G -- " type: connect_request " --> I[Show QR Code Prompt];
    end
```

As you can see, `ClientConfig` plays a small but vital role. It makes the communication between your backend and the HAWKI frontend predictable and reliable.

### Putting It All Together

Let's revisit the final lines of the `getClientConfig` method from [Chapter 1: `HawkiClientBackend`: The Main Orchestrator`](hawkiclientbackend-the-main-orchestrator-840305559.md) to see this in action.

```php
// ...inside HawkiClientBackend::getClientConfig()

// Step 3: Package and encrypt the final payload for the frontend.
return new EncryptedClientConfig(
    $this->hybridCrypto->encrypt(
        json_encode(new ClientConfig($payload), JSON_THROW_ON_ERROR),
        $this->asymmetricCrypto->loadPublicKeyFromWeb($publicKey)
    )
);
```

The logic is clear and happens in sequence:
1.  Take the `$payload` (which is either a `Connection` or `RequestConnection`).
2.  Wrap it inside `new ClientConfig($payload)`. This adds the all-important `type` field.
3.  Convert the entire `ClientConfig` object into a JSON string.
4.  Encrypt that JSON string, making it ready and safe to send to the frontend.

This ensures the standardized, labeled package is what gets securely delivered.

### Conclusion

You've now learned about the simple but essential "message contract" that makes your backend and frontend speak the same language.

*   **`ClientConfig`** is a wrapper that standardizes the data sent to the frontend.
*   It adds a **`type` property** (`connected` or `connect_request`).
*   This `type` acts as a clear **label**, telling the frontend exactly how to handle the decrypted data.

So far, we've seen that the `HawkiClientBackend` can fetch a `Connection` or create a `RequestConnection`. But we've treated that part like magic. How does it *actually* talk to the HAWKI API to get this information? In the next chapter, we'll pull back the curtain on the special-purpose "messengers" that handle that job.

Next up: [Chapter 4: API Request Layer: The Messengers](api-request-layer-the-messengers-80603215.md)

