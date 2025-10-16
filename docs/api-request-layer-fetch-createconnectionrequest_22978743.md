# Chapter 4: API Request Layer (`Fetch-` & `CreateConnectionRequest`)

In the [previous chapter](clientconfig-the-standardized-payload_1483722428.md), we saw how the library packages data into a standardized `ClientConfig` object before sending it to the frontend. We know that the [Chapter 2: `HawkiClientBackend` (The Main Orchestrator)](hawkiclientbackend-the-main-orchestrator_1464278974.md) is responsible for getting this data, but we've treated the "how" as a bit of a magic trick.

How does it *actually* talk to the remote HAWKI API? Does it just have a bunch of messy HTTP code inside?

Of course not! The library uses a clean design pattern where specific, single-purpose classes handle the API calls. Think of the `HawkiClientBackend` as a busy manager. The manager doesn't run errands personally. Instead, they have a team of trusted messengers.

-   One messenger's job is to **fetch** existing reports.
-   Another messenger's job is to **create** new request forms.

This is exactly what the API Request Layer does. It's a collection of "messenger" classes that handle the low-level communication with the HAWKI API, keeping our main orchestrator clean and focused on its primary job: making decisions.

In this chapter, we'll meet the two most important messengers: `FetchConnectionRequest` and `CreateConnectionRequest`.

### Messenger 1: `FetchConnectionRequest` (The Scout)

Imagine our manager (`HawkiClientBackend`) needs to know the status of a user named "jane.doe". The first question is always: "Is Jane already connected?"

The manager dispatches a specialist scout: the `FetchConnectionRequest` class. Its one and only job is to go to the HAWKI API and ask this question.

This scout is smart. It knows there are two possible answers:
1.  **"Yes, here is Jane's file."** (An HTTP `200 OK` response with user data).
2.  **"Sorry, I've never heard of Jane."** (An HTTP `404 Not Found` response).

Crucially, the scout is trained to handle the "Not Found" answer without causing a panic. It simply returns to the manager and reports, "I found nothing." In code, this means it returns `null`.

Let's see the scout's mission in a diagram.

```mermaid
sequenceDiagram
    participant HCB as HawkiClientBackend
    participant FCR as FetchConnectionRequest
    participant HAPI as HAWKI API

    HCB->>FCR: Find connection for "jane.doe"
    FCR->>HAPI: GET /api/apps/connection/jane.doe

    alt User Exists
        HAPI-->>FCR: 200 OK with encrypted connection data
        FCR->>HCB: Return `Connection` object
    else User Does Not Exist
        HAPI-->>FCR: 404 Not Found
        FCR->>HCB: Return `null`
    end
```

The code that performs this is lean and focused. It uses an HTTP client (Guzzle) to make the request.

**File: `src/Request/FetchConnectionRequest.php`**
```php
public function execute(ClientInterface $client): Connection|null
{
    try {
        // Send a GET request for the user
        $response = $client->send(
            new Request('GET', 'api/apps/connection/' . $this->localUserId)
        );
        
        $data = json_decode((string)$response->getBody(), true);
        return new Connection($data); // Found it! Return a Connection object.
    } catch (ClientExceptionInterface $e) {
        // Did we get a "404 Not Found" error?
        if ($e->getCode() === 404) {
            return null; // Yes. This is normal, just return null.
        }
    }
}
```
This little piece of logic is incredibly important. It turns a "hard" HTTP error (404 Not Found) into a "soft" application response (`null`), which the `HawkiClientBackend` can easily check in an `if` statement.

### Messenger 2: `CreateConnectionRequest` (The Registrar)

So, what happens when the scout comes back empty-handed? The manager (`HawkiClientBackend`) now knows the user isn't connected. The next step is to invite them.

For this, the manager dispatches a different specialist: the `CreateConnectionRequest` class, our "Registrar." This messenger's job is to go to the HAWKI API with a simple instruction: "Please create a new connection invitation for 'jane.doe'."

Unlike the scout, the registrar expects its mission to succeed. There's no normal reason for this request to fail. If it does, something is genuinely wrong (like the API server being down), and it should report an error immediately.

```mermaid
sequenceDiagram
    participant HCB as HawkiClientBackend
    participant CCR as CreateConnectionRequest
    participant HAPI as HAWKI API

    HCB->>CCR: Create connection request for "jane.doe"
    CCR->>HAPI: POST /api/apps/connection/jane.doe
    HAPI-->>CCR: 200 OK with new request data (URL, etc.)
    CCR->>HCB: Return `RequestConnection` object
```

Let's look at the registrar's code. It's even simpler because it doesn't need to handle a "Not Found" case.

**File: `src/Request/CreateConnectionRequest.php`**
```php
public function execute(ClientInterface $client): RequestConnection
{
    try {
        // Send a POST request to create the connection
        $response = $client->send(
            new Request('POST', 'api/apps/connection/' . $this->localUserId)
        );
        
        $data = json_decode((string)$response->getBody(), true);
        return new RequestConnection($data); // Success! Return the invitation.
    } catch (\Throwable $e) {
        // If anything goes wrong here, it's a real problem.
        throw new FailedToCreateConnectionRequestException($e);
    }
}
```
This messenger makes a `POST` request. If it's successful, it wraps the data in the simple `RequestConnection` object we learned about in [Chapter 1: Connection State (`Connection` & `RequestConnection`)](connection-state-connection-requestconnection_1088094409.md) and returns it.

### How the Manager Uses Its Messengers

Now that you've met the messengers, the logic inside `HawkiClientBackend` will be crystal clear. It's just a simple sequence of giving orders.

Let's look at that code again, but now with a full understanding of what's happening.

**File: `src/HawkiClientBackend.php`**
```php
// Inside the getClientConfig() method...

// 1. Send the scout.
$payload = (new FetchConnectionRequest($localUserId))->execute($this->client);

if ($payload) {
    // 2a. The scout found something! We have a Connection.
    // (We'll decrypt it in the next step).
    $payload = $payload->decrypt(...);
} else {
    // 2b. The scout came back empty-handed. Time to send the registrar.
    $payload = (new CreateConnectionRequest($localUserId))->execute($this->client);
}
```
And just like that, the complex process of talking to an API is reduced to a simple, readable `if/else` block. This is the power of abstraction and dedicating classes to single responsibilities.

### Conclusion

You've just pulled back the curtain on how `hawki-client-backend-php` neatly manages its API calls.

-   The library uses **specialized request classes** to handle HTTP communication.
-   **`FetchConnectionRequest`** acts like a scout, safely checking for an existing user and gracefully handling a `404 Not Found` by returning `null`.
-   **`CreateConnectionRequest`** acts like a registrar, creating a new connection request when one is needed.
-   This design keeps the main `HawkiClientBackend` class clean, readable, and focused on **orchestration**, not low-level details.

We've mentioned a few times that when `FetchConnectionRequest` finds a user, the returned `Connection` object contains encrypted data. In the code snippet above, we even see a call to `$payload->decrypt()`. How does this secure decryption work, and what keys are involved? That's the final piece of the puzzle.

Next up: [Chapter 5: Encryption Workflow](encryption-workflow_611751868.md)

