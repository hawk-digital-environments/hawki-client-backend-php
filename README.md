# HAWKI Client Backend (PHP)

[![Latest Version](https://img.shields.io/packagist/v/hawk-hhg/hawki-client-backend.svg)](https://packagist.org/packages/hawk-hhg/hawki-client-backend)
[![PHP Version](https://img.shields.io/packagist/php/hawk-hhg/hawki-client-backend)](https://packagist.org/packages/hawk-hhg/hawki-client-backend)
[![Total Downloads](https://img.shields.io/packagist/dt/hawk-hhg/hawki-client-backend.svg)](https://packagist.org/packages/hawk-hhg/hawki-client-backend)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](https://github.com/hawk-digital-environments/hawki-client-backend-php/blob/main/LICENSE)

This package provides a secure, encrypted bridge between your PHP application and your HAWKI instance. It simplifies the
process of managing user connections for HAWKI's external applications by handling API communication, data
encapsulation, and cryptography.

## Requirements

* PHP: `^8.2`
* `psr/log`: `^3.0`
* `guzzlehttp/guzzle`: `^7.0`
* `hawk-hhg/hawki-crypto`: `^0.5.2`

## Installation

You can install the package via Composer:

```bash
composer require hawk-hhg/hawki-client-backend
```

## Quick Start: Creating a Configuration Endpoint

The primary role of this backend library is to provide a secure endpoint that your frontend HAWKI client can call to get
its configuration. This configuration tells the frontend whether a user is already connected to HAWKI or needs to
initiate a new connection.

The entire process is managed by the main `HawkiClientBackend` class.

### 1. Instantiate `HawkiClientBackend`

First, create an instance of `HawkiClientBackend`. You'll need three pieces of sensitive information from your HAWKI
application setup. It's crucial to store these as environment variables or secrets, not in version control.
All three values will be provided to you when you execute the `php artisan ext-app:create` command in your HAWKI
instance.

```php
use Hawk\HawkiClientBackend\HawkiClientBackend;

// Load these values securely from your environment configuration.
$hawkiUrl = $_ENV['HAWKI_URL']; // e.g., 'https://your-hawki-instance.com'
$apiToken = $_ENV['HAWKI_API_TOKEN'];
$appPrivateKey = $_ENV['HAWKI_APP_PRIVATE_KEY'];

$hawkiClientBackend = new HawkiClientBackend(
    hawkiUrl: $hawkiUrl,
    apiToken: $apiToken,
    privateKey: $appPrivateKey
);
```

### 2. Get the Encrypted Client Configuration

With your `HawkiClientBackend` instance ready, you can now retrieve the secure configuration for a given user.

The main `getClientConfig()` method handles everything. It automatically checks if a user connection exists in HAWKI.

* If a connection exists, it fetches, decrypts, and prepares the details.
* If not, it creates a new connection request.

Finally, it encrypts the entire configuration payload using the **frontend's public key** before returning it.

### 3. Example: A Full API Endpoint

Here is a practical example of a PHP script that could serve as your API endpoint (e.g., `POST /api/hawki-config`). The
HAWKI frontend client is designed to call an endpoint like this. When using our provided Javascript client,
the `clientConfigUrl` expects the JSON encoded output of this method.

```php
<?php

// index.php

require_once __DIR__ . '/vendor/autoload.php';

use Hawk\HawkiClientBackend\HawkiClientBackend;

// 1. Authenticate your user and get their unique ID from your system.
//    (This is a placeholder for your application's authentication logic).
$localUserId = 'user_id_from_your_app_session_123';

// 2. The HAWKI frontend client will send its public key in the POST request.
$frontendPublicKey = $_POST['public_key'] ?? null;

if (!$frontendPublicKey) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Frontend public key is required.']);
    exit;
}

try {
    // 3. Instantiate the client.
    $hawkiClientBackend = new HawkiClientBackend(
        hawkiUrl: $_ENV['HAWKI_URL'],
        apiToken: $_ENV['HAWKI_API_TOKEN'],
        privateKey: $_ENV['HAWKI_APP_PRIVATE_KEY']
    );

    // 4. Get the encrypted configuration for the user.
    $encryptedClientConfig = $hawkiClientBackend->getClientConfig(
        $localUserId,
        $frontendPublicKey
    );

    // 5. Send the encrypted configuration back to the frontend.
    header('Content-Type: application/json');
    echo json_encode($encryptedClientConfig);

} catch (\Throwable $e) {
    // It's good practice to log the actual error for debugging
    // but return a generic error message to the client.
    error_log($e->getMessage()); 
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to retrieve HAWKI configuration.']);
    exit;
}
```

This single `getClientConfig()` call orchestrates the entire workflow, providing you with a secure, ready-to-use payload
for your frontend with minimal effort.

## How It Works: Core Concepts

While the `HawkiClientBackend` class simplifies usage, it's helpful to understand the components working behind the
scenes.

### Security and Encryption Flow

The library enforces a strict, secure workflow for handling secrets:

1. **Fetch & Decrypt**: When fetching an existing user connection from HAWKI, the connection details (like the user's
   API token and private key) are received in an encrypted format. The `HawkiClientBackend` uses **your application's
   private key** to decrypt these secrets on the server.
2. **Prepare & Encrypt**: Once the connection data is ready (either a decrypted existing connection or a new connection
   request), it's packaged into a `ClientConfig` object. This entire object is then **encrypted using the frontend's
   public key**.
3. **Transmit**: The final `EncryptedClientConfig` payload is sent to the browser. Only the frontend client, which holds
   the corresponding private key, can decrypt and read the configuration.

This ensures sensitive data is never exposed in transit or on the client-side without proper encryption.

### Key Classes

* `HawkiClientBackend`: The main orchestrator and your primary entry point into the library.
* `FetchConnectionRequest` & `CreateConnectionRequest`: Internal classes that model the API calls to fetch or create a
  user connection in HAWKI.
* `Connection`: A value object representing an existing, established user connection. It has a mandatory `decrypt()`
  method to access secrets.
* `RequestConnection`: A value object for a newly created connection request.
* `ClientConfig`: A wrapper that holds either a `Connection` or a `RequestConnection`, clearly identifying the payload
  type for the frontend.
* `EncryptedClientConfig`: The final, secure wrapper for the encrypted `ClientConfig` payload, ready to be sent to the
  browser.
* `ClientConfigurator`: An internal helper that configures the Guzzle HTTP client, automatically adding the base URL and
  `Authorization` token to every request.

## Testing

This package uses PHPUnit for testing. You can run the test suite using the provided Composer scripts.

```bash
# Run unit tests
composer test:unit

# Run unit tests with HTML coverage report
composer test:unit:coverage

# Run unit tests with text coverage in the console
composer test:unit:coverage:text
```

The HTML coverage report will be generated in the `.phpunit.coverage` directory.

## Development Setup

### CLI Interface

The library comes with a powerful CLI interface (`bin/env`) built around a preconfigured Docker environment, providing
commands for
development and testing:

```
bin/env composer                            runs a certain composer command for the project
bin/env docker:build [options]              Builds an image from the Dockerfile of the project
bin/env docker:clean|clean [options]        Stops the project and removes all containers, networks, volumes and images
bin/env docker:down|down                    Stops and removes the docker containers (docker compose down)
bin/env docker:install|install              Installs the project on your device; sets up a unique url, ip address, hosts entry and ssl certificate
bin/env docker:logs|logs [options]          Shows the logs of the docker containers (docker compose logs) - by default only the logs of the main container are shown, use "--all" to show
                                            all logs
bin/env docker:open|open                    opens the current project in your browser.
bin/env docker:ps|ps                        Shows the docker containers of the project (docker compose ps)
bin/env docker:restart|restart [options]    Restarts the docker containers (docker compose restart), all arguments and flags are passed to the "up" command
bin/env docker:ssh|ssh [options] [service]  Opens a shell in a docker container (docker compose exec)
bin/env docker:stop|stop                    Stops the docker containers (docker compose stop)
bin/env docker:up|up [options]              Starts the docker containers (docker compose up)
bin/env env:reset                           Resets your current .env file back to the default definition
bin/env help [command]                      display help for command
bin/env test [options]                      Execute phpunit tests inside the app container
```

## License

This project is licensed under the Apache-2.0 License. See the `LICENSE` file for details.

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a
postcard from your hometown, mentioning which of our package(s) you are using.

```
HAWK Fakultät Gestaltung
Interaction Design Lab
Renatastraße 11
31134 Hildesheim
```

Thank you :D
