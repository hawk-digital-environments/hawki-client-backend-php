# Getting Started

Welcome to the `hawki-client-backend-php` library! This guide will walk you through the essential steps to install the library and integrate it into your PHP application. You'll learn how to set up a secure API endpoint that provides the necessary configuration for your HAWKI-enabled frontend client.

## Overview

The `hawki-client-backend-php` library acts as a **secure bridge** between your PHP application's backend and the HAWKI authentication system. Its main purpose is to simplify the complex process of managing user connections by securely determining a user's status with the HAWKI API.

Think of it as a specialized assistant that handles all the heavy lifting for you:

*   **API Communication:** It communicates with the HAWKI API to check if a user is already connected.
*   **Automatic Logic:** It intelligently determines whether to fetch an existing user's connection details or create a new "connection request" (which the frontend can display as a QR code invitation).
*   **End-to-End Encryption:** It manages all the necessary cryptographic steps, ensuring that sensitive information is securely encrypted before being sent to the frontend client.

Your primary interaction will be with one central class: `HawkiClientBackend`. This is the only class you need to interact with directly. You will instantiate it with your application's credentials and use its `getClientConfig()` method to generate a secure payload for your frontend.

## Installation

The project is managed using Composer. You can add it as a dependency to your project with a single command.

### Prerequisites

Before you begin, ensure your development environment meets the following requirements:

*   PHP `^8.2`
*   Composer

### Composer Installation

To install the package, run the following command in your project's root directory:

```bash
composer require hawk-hhg/hawki-client-backend
```

This will download the library and its required dependencies, such as `guzzlehttp/guzzle` for HTTP requests and `hawk-hhg/hawki-crypto` for handling encryption.

## Usage Example

The most common use case for this library is to create an API endpoint that the HAWKI frontend client can call to get its configuration. This endpoint should be authenticated on your side to ensure you know which user is making the request.

Let's create a simple example of an API endpoint (e.g., `POST /api/hawki-config`) that demonstrates the complete workflow.

### 1. Set Up Your Credentials

First, you need three secret credentials from your HAWKI instance. It is critical to store these securely as environment variables (e.g., in a `.env` file) and **never** commit them to your version control.

*   `HAWKI_URL`: The URL of your HAWKI server.
*   `HAWKI_API_TOKEN`: Your application's API token for authentication.
*   `HAWKI_APP_PRIVATE_KEY`: Your application's private key for decrypting data from the HAWKI server.

### 2. Create the API Endpoint

Create a PHP file (e.g., `api/hawki-config.php`) that will handle the request. The code below shows a complete, runnable example.

```php
<?php
// api/hawki-config.php

// Ensure Composer's autoloader is included.
require_once __DIR__ . '/../vendor/autoload.php';

use Hawk\HawkiClientBackend\HawkiClientBackend;

// --- Step 1: Identify the User ---
// In a real application, you would get this from your session or authentication system.
// This is the unique ID of the user in *your* application.
$localUserId = 'user_id_from_your_app_session_123';

// --- Step 2: Get the Frontend's Public Key ---
// The HAWKI frontend client sends its public key in a POST request.
// This key is used to encrypt the response so only that specific client can read it.
$frontendPublicKey = $_POST['public_key'] ?? null;

if (!$frontendPublicKey) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Frontend public key is required.']);
    exit;
}

try {
    // --- Step 3: Instantiate the HawkiClientBackend ---
    // Load your secret credentials securely from environment variables.
    $hawkiClientBackend = new HawkiClientBackend(
        hawkiUrl: $_ENV['HAWKI_URL'],
        apiToken: $_ENV['HAWKI_API_TOKEN'],
        privateKey: $_ENV['HAWKI_APP_PRIVATE_KEY']
    );

    // --- Step 4: Get the Encrypted Client Configuration ---
    // This is the main method that does all the work. It handles everything:
    // - Checks if a connection exists for `$localUserId`.
    // - If yes, fetches and prepares the connection details.
    // - If no, creates a new connection request.
    // - Encrypts the entire payload using the `$frontendPublicKey`.
    $encryptedClientConfig = $hawkiClientBackend->getClientConfig(
        $localUserId,
        $frontendPublicKey
    );

    // --- Step 5: Send the Secure Payload to the Frontend ---
    // The frontend receives this JSON and can decrypt it with its private key.
    header('Content-Type: application/json');
    echo json_encode($encryptedClientConfig);

} catch (\Throwable $e) {
    // Log the detailed error for your own debugging.
    error_log('HAWKI Config Error: ' . $e->getMessage());

    // Return a generic error to the client for security.
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to retrieve HAWKI configuration.']);
    exit;
}
```

That's it! With one primary class and a single method call to `getClientConfig()`, you have a fully functional and secure backend endpoint for HAWKI. The library transparently handles the complex API and cryptographic workflow for you.

## Cloning the Repository (for Development)

If you want to contribute to the development of this library, you will need to clone the repository from GitHub.

```bash
git clone git@github.com:hawk-digital-environments/hawki-client-backend-php.git
cd hawki-client-backend-php
```

Note: The `git@` URL uses SSH for cloning. You will need to have an SSH key configured with your GitHub account.

### Local Development Environment with `bin/env`

The project includes a command-line tool, `bin/env`, to simplify local development setup using Docker. This ensures a consistent environment for all contributors.

1.  **First-Time Setup**: Run the install command. This will set up your `.env` file, generate local SSL certificates, and build the Docker containers.
    ```bash
    ./bin/env install
    ```

2.  **Start the Environment**: To start the Docker containers for daily development, use the `up` command.
    ```bash
    ./bin/env up
    ```

### Scripts

The project includes several scripts defined in `composer.json` for running tests and performing other common tasks.

#### Running Tests

You can run the PHPUnit test suite using the following Composer commands:

*   **Run unit tests:**
    ```bash
    composer test:unit
    ```

*   **Run unit tests with an HTML coverage report:**
    (The report will be generated in the `.phpunit.coverage` directory)
    ```bash
    composer test:unit:coverage
    ```

*   **Run tests and display text-based coverage in the console:**
    ```bash
    composer test:unit:coverage:text
    ```

## [bin/env - Your local dev helper](bin-env-your-local-dev-helper-862670637.md)

With the project installed and a solid grasp of running its tests, you're now ready to unlock even more efficiency in your development workflow. The next chapter introduces `bin/env`, a handy local development helper program designed to streamline common tasks, giving you powerful tools right at your fingertips. Dive in to see how it can elevate your setup and supercharge your coding sessions!


