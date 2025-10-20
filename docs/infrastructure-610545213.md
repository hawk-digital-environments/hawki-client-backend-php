# Infrastructure

Building on the "decrypt-then-re-encrypt" pattern detailed in [The Encryption Workflow](the-encryption-workflow-262429037.md), which secures user secrets through a two-lock strategy, this chapter outlines the infrastructure setup essential for implementing these encryption operations reliably. It provides a comprehensive overview of the project's local development environment, orchestrated using Docker to ensure a consistent, isolated, and easy-to-manage setup for all developers.

## Overview

The project's infrastructure is built entirely on Docker and Docker Compose. This approach containerizes the application and its dependencies, ensuring that it runs the same way regardless of your local machine's configuration.

The primary component of our infrastructure is a single service container named `app`. This container provides:

-   A **PHP 8.2-FPM** environment based on the lightweight `alpine` Linux distribution.
-   **Composer** for managing PHP dependencies.
-   **Xdebug** pre-installed and configured for easy debugging.
-   A shared volume that maps your local project files directly into the container for real-time development.

This setup is designed for local development and debugging. It is not intended for production deployment.

## Getting Started

Follow these steps to build and run the local development environment on your machine.

### Prerequisites

Before you begin, ensure you have the following software installed on your system:

-   [Docker](https://docs.docker.com/get-docker/)
-   [Docker Compose](https://docs.docker.com/compose/install/)

### 1. Configuration

The environment uses variables to configure container settings. These are managed in an `.env` file.

1.  **Create the environment file:**
    Copy the provided template to create your local configuration file.
    ```bash
    cp .env.tpl .env
    ```

2.  **Verify User and Group IDs (for Linux/macOS users):**
    To prevent file permission issues between your host machine and the Docker container, the `Dockerfile` rebuilds the `www-data` user with your local user and group IDs. Docker Compose passes these IDs during the build process.

    Find your User ID (UID) and Group ID (GID) by running:
    ```bash
    id -u
    id -g
    ```
    The default values in `docker-compose.yml` are `1000` for both. If your values are different, you can either export them in your shell before running Docker Compose commands...
    ```bash
    export DOCKER_UID=$(id -u)
    export DOCKER_GID=$(id -g)
    docker-compose build
    ```
    ...or create a `.env` file and add them there:
    ```
    COMPOSE_PROJECT_NAME=hawki-client-backend-php
    DOCKER_UID=1000 # Replace with your UID
    DOCKER_GID=1000 # Replace with your GID
    ```
    > **Note for Windows users:** You can typically skip this step and use the default values.

### 2. Building and Running the Container

With your `.env` file in place, you can now build and run the services.

1.  **Build the `app` image:**
    This command reads the `Dockerfile` and builds the PHP image with all the specified tools and configurations.
    ```bash
    docker-compose build
    ```

2.  **Start the services:**
    This command starts the `app` container in the background (`-d` for "detached" mode).
    ```bash
    docker-compose up -d
    ```

Your development environment is now running! The project directory on your host machine is synchronized with `/var/www/html` inside the `app` container.

### 3. Common Commands

Here are the most common commands you will use to manage the environment.

-   **Get a shell inside the `app` container:**
    ```bash
    docker-compose exec app sh
    ```
    Once inside, you can run commands like `composer install`.

-   **Stop the containers:**
    ```bash
    docker-compose stop
    ```

-   **Stop and remove the containers, networks, and volumes:**
    ```bash
    docker-compose down
    ```

-   **View container logs:**
    ```bash
    docker-compose logs -f app
    ```

## Service Details

### The `app` Service

This is the main PHP application container, defined in `docker-compose.yml`.

-   **Image:** Built from the `Dockerfile` in the project root.
-   **Base Image:** `neunerlei/php:8.2-fpm-alpine`, a lightweight image optimized for PHP development.
-   **Volumes:** The `./:/var/www/html` mapping is crucial. It syncs your local project folder into the container, allowing you to edit code on your host machine with your favorite IDE and see the changes reflected instantly inside the container.
-   **Ports:** This setup uses PHP-FPM, which doesn't expose an HTTP port directly. It is expected to be connected to a web server (like Nginx) in a separate container, which would then expose port 80/443.
-   **`extra_hosts`:** The `host.docker.internal:host-gateway` entry is a special configuration that allows the container to connect back to services running on your host machine. This is essential for Xdebug to connect to your IDE.

## Configuration

### Environment Variables

The following environment variables can be set in your `.env` file to customize the Docker environment.

| Variable             | Default | Description                                                                                             |
| -------------------- | ------- | ------------------------------------------------------------------------------------------------------- |
| `COMPOSE_PROJECT_NAME` | `hawki-client-backend-php` | Sets a custom project name, which prefixes container and network names.                 |
| `DOCKER_UID`         | `1000`  | The User ID to use for the `www-data` user inside the container. Match this to your host user's ID.    |
| `DOCKER_GID`         | `1000`  | The Group ID to use for the `www-data` group inside the container. Match this to your host group's ID. |

### PHP & Xdebug

The PHP environment is configured specifically for development via the `docker/php/config/php.dev.ini` file. This includes:

-   `display_errors = On`: Shows PHP errors directly in the output.
-   `memory_limit = 2G`: Provides ample memory for intensive tasks like dependency installation.
-   `xdebug.client_host = 'host.docker.internal'`: Instructs Xdebug to connect to your host machine for debugging sessions. Ensure your IDE is configured to "listen for incoming connections" for debugging to work.

## Maintenance and Best Practices

### Rebuilding the Image

You only need to rebuild the Docker image when you make changes to the underlying configuration. The most common reason to rebuild is a change to the `Dockerfile`.

To rebuild, simply run:
```bash
docker-compose build
```
You do **not** need to rebuild the image when you change your PHP application code, as the project files are mounted as a volume.

### Local Development vs. Production

**This setup is for local development only.** The `Dockerfile` installs development tools like Xdebug and applies permissive settings that are insecure and inefficient for a live environment.

For production, you would typically:
-   Have a separate `Dockerfile.prod` that does not include Xdebug or other dev tools.
-   Build from a `prod` stage in a multi-stage `Dockerfile`.
-   Use a more robust deployment strategy like Kubernetes or a managed cloud platform.

## Troubleshooting

### File Permission Issues

-   **Symptom:** You see "Permission denied" errors when the application tries to write to a log file, or when you try to delete a file created by `composer install`.
-   **Cause:** The user inside the container (`www-data`) has a different UID/GID than your user on the host machine.
-   **Solution:** Ensure the `DOCKER_UID` and `DOCKER_GID` values are set correctly as described in the [Configuration](#1-configuration) section. After setting them, you may need to rebuild the container: `docker-compose build` and restart it `docker-compose up -d`. You might also need to `chown` existing files in your project directory to your user.

### Xdebug Connection Problems

-   **Symptom:** You set a breakpoint in your IDE, but the code execution doesn't pause.
-   **Solution:**
    1.  Verify that your IDE's debugger is active and "listening for connections".
    2.  Check that the `xdebug.client_host` in `docker/php/config/php.dev.ini` is set to `host.docker.internal`.
    3.  Ensure your firewall is not blocking the connection from the Docker container to your IDE (the default debug port is 9003).

### Container fails to start

-   **Symptom:** `docker-compose up -d` exits with an error.
-   **Solution:** Run the command in the foreground to see the startup logs, or check the logs of the failed container.
    ```bash
    # View logs for the 'app' service
    docker-compose logs app
    ```
    The logs will often contain a specific error message pointing you to the problem (e.g., a syntax error in a config file, a failed command in the `Dockerfile`).

## Ensuring Robust Development Workflows

With your infrastructure properly configured and any common issues resolved through the troubleshooting steps above, you're now equipped to build a stable foundation for your PHP project. This setup not only supports local development but also paves the way for seamless integration into automated processes that enhance code quality and efficiency. In the next chapter, we'll explore how the CI/CD pipeline leverages this infrastructure to automate testing and deployment, transforming manual workflows into a streamlined, professional-grade development cycle.

[CI/CD Pipeline](ci-cd-pipeline-610418824.md)

