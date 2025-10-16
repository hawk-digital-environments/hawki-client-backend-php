# Getting Started

Welcome to the `hawki-client-backend-php` library! This guide will walk you through the essential steps to get the library installed and running in your PHP application. You'll learn how to set up a secure endpoint to provide configuration to your HAWKI-enabled frontend.

## Installation

The project is managed using Composer. You can add it to your project with a single command.

### Prerequisites

Before you begin, ensure your environment meets the following requirements:
*   PHP `^8.2`
*   Composer

###
To install the package, run the following command in your project's root directory:

```bash
composer require hawk-hhg/hawki-client-backend
```

This will download the library and its dependencies, including `guzzlehttp/guzzle` for HTTP requests and `hawk-hhg/hawki-crypto` for handling the encryption.

## Overview

The `hawki-client-backend-php` library acts as a **secure bridge** between your PHP application's backend and your HAWKI instance. Its main purpose is to simplify the complex process of managing user connections.

Think of it as a specialized assistant that handles all the heavy lifting for you:
*   **API Communication:** It communicates with the HAWKI API to check a user's connection status.
*   **Automatic Logic:** It intelligently determines whether to fetch an existing user connection or create a new "connection request" (like an invitation QR code).
*   **End-to-End Encryption:** It manages all the necessary cryptographic steps, ensuring that sensitive information is securely encrypted before being sent to the frontend client.

Your primary interaction will be with one class: `HawkiClientBackend`. You will instantiate it with your application's secrets and use its simple API to generate a secure configuration payload for your frontend.

## Usage Example

The most common use case for this library is to create an API endpoint that the HAWKI frontend client can call to get its configuration. This endpoint will typically be authenticated, ensuring you know which user is making the request.

Let's create a simple API endpoint (e.g., `POST /api/hawki-config`) that demonstrates the complete workflow.

### 1. Set Up Your Credentials

First, you need three secret credentials from your HAWKI instance. It is critical to store these securely as environment variables (e.g., in a `.env` file) and **never** commit them to your version control.

*   `HAWKI_URL`: The URL of your HAWKI server.
*   `HAWKI_API_TOKEN`: Your application's API token for authentication.
*   `HAWKI_APP_PRIVATE_KEY`: Your application's private key for decrypting data.

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
    // This is the main method. It handles everything:
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

That's it! With one primary class and a single method call (`getClientConfig()`), you have a fully functional and secure backend endpoint for HAWKI. The library transparently handles the complex API and cryptographic workflow, allowing you to focus on your application's logic.

