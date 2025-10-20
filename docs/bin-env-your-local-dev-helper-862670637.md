# bin/env - Your local dev helper

Having familiarized yourself with installing and understanding the project fundamentals in [Getting Started](getting-started-929492837.md), we'll now explore the tools that enhance your local development experience.

Welcome to the documentation for `bin/env`, the all-in-one command-line helper for the `hawki-client-backend-php` project. This tool is designed to streamline your local development workflow, from initial setup to daily tasks, ensuring a consistent and hassle-free experience for every developer on the team.

This chapter will guide you through its features, how to use it, and how you can extend it to fit your needs.

## Overview

`bin/env` is a self-contained command-line interface (CLI) written in Node.js and TypeScript. It lives in the `bin/` directory of your project and serves as a single entry point for managing your entire local development environment.

Its primary responsibilities include:
*   **Environment Setup**: Automatically configuring your `.env` file.
*   **Project Installation**: A one-command setup for new developers.
*   **Docker Management**: Simplified, project-aware wrappers around `docker compose` commands.
*   **Script Execution**: A central place to run common project scripts, such as tests or database migrations.

One of its key features is that it's **self-bootstrapping**. It manages its own Node.js version and dependencies, meaning you don't need to have Node.js or a specific `npm` version installed on your system to use it. Just run the script, and it handles the rest.

## Core Features & Benefits

Using `bin/env` provides several advantages for local development:

*   **Zero-Dependency Setup**: The tool automatically downloads a project-specific Node.js version into a local cache (`~/.bin-env`), completely isolated from your system's Node.js installation. This guarantees that the tool runs consistently for everyone, regardless of their machine's configuration.
*   **Simplified Installation**: New team members can get up and running with a single command: `bin/env install`. This command automates every step of the setup process, which we'll detail in the next section.
*   **Consistent Environment**: By encapsulating environment logic within the tool, we eliminate "it works on my machine" problems. Everyone uses the same commands and underlying configuration.
*   **Convenient Docker Wrappers**: Instead of remembering complex `docker compose` commands, you can use simple aliases like `bin/env up`, `bin/env ssh`, and `bin/env logs`. These commands are pre-configured for the project's specific services.
*   **Extensibility**: The tool is built with an addon architecture, making it easy to add new, project-specific commands without cluttering the project's root directory with dozens of shell scripts.

## Getting Started: The `install` Command

The first and most important command you will run is `install`. This command performs a complete, one-time setup of your local development environment.

To start, simply run:
```bash
./bin/env install
```
> Note: The command can also be run as `bin/env docker:install`.

Here’s a step-by-step breakdown of what the `install` command does:

1.  **Dependency Check**: It inspects your system for required tools (like `mkcert` for SSL). If a dependency is missing, it will offer to install it for you using your system's package manager (e.g., Homebrew on macOS, APT/YUM on Linux, or Scoop on WSL/Windows).
2.  **Assigns a Unique IP Address**: To avoid port conflicts and allow multiple projects to run side-by-side, the installer assigns a unique loopback IP address for the project (e.g., `127.x.x.x`).
3.  **Generates a Local Domain**: It creates a friendly local domain name for your project, typically based on the project name (e.g., `hawki-client-backend-php.dev.local`).
4.  **Updates Hosts File**: It automatically adds an entry to your system's `hosts` file (`/etc/hosts` on Linux/macOS or `C:\Windows\System32\drivers\etc\hosts` on Windows) to map the new domain to the assigned IP address. **This step will require `sudo`/administrator privileges.**
5.  **Creates SSL Certificates**: Using `mkcert`, it generates a trusted local SSL certificate and key, enabling you to access your local site via HTTPS without browser warnings. These certificates are stored in `docker/certs/`.
6.  **Configures `.env` File**: It populates your `.env` file with all the generated values, such as `DOCKER_PROJECT_HOST`, `DOCKER_PROJECT_IP`, and sets `DOCKER_PROJECT_PROTOCOL` to `https`.
7.  **Starts the Environment**: Finally, it brings up all the necessary Docker containers using `docker compose up`.

After the process completes, your project will be running and accessible at a local HTTPS URL, like `https://hawki-client-backend-php.dev.local`. You can open it directly by running `bin/env open`.

## Common Commands

Here is a list of common commands you will use in your daily workflow.

| Command(s)                         | Description                                                                                                                           |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `install`, `docker:install`        | Performs the one-time installation and setup of the project.                                                                          |
| `up`, `docker:up`                  | Starts the Docker environment in detached mode. Use `bin/env up -f` to attach and follow logs.                                        |
| `down`, `docker:down`              | Stops and removes the Docker containers.                                                                                              |
| `stop`, `docker:stop`              | Stops the Docker containers without removing them.                                                                                    |
| `restart`, `docker:restart`        | Restarts the Docker containers. Effectively runs `stop` then `up`.                                                                    |
| `open`, `docker:open`              | Opens your project's local URL in your default web browser.                                                                           |
| `ssh`, `docker:ssh [service]`      | Opens a shell (`bash` or `sh`) inside a container. Defaults to the `app` service. e.g. `bin/env ssh`                                   |
| `composer ...`                     | Executes any `composer` command inside the `app` container. e.g. `bin/env composer update`                                            |
| `logs`, `docker:logs`              | Shows logs from the containers. Use `-f` to follow logs and `--all` to show logs from all services.                                   |
| `test`                             | Executes the project's PHPUnit test suite inside the `app` container.                                                                 |
| `ps`, `docker:ps`                  | Lists the running Docker containers for this project.                                                                                 |
| `clean`, `docker:clean`            | **DANGER!** Stops and removes all containers, networks, volumes, and images associated with the project. Asks for confirmation.       |
| `env:reset`                        | Resets your `.env` file to its default state based on the `.env.tpl` template, prompting you for any required values.                   |


## Extending `bin/env`

The `bin/env` tool is designed to be extensible. You can add your own commands, install new Node.js dependencies for your commands, and hook into core events.

### Adding New Commands

You can add custom commands by creating **addon files**. An addon is a TypeScript file ending in `.addon.ts` located in either `bin/_env/addons/` (for generic, reusable functionality) or `bin/_env/project/` (for project-specific scripts).

Here is a "Hello World" example. Create a file named `bin/_env/project/hello.addon.ts`:
```typescript
// bin/_env/project/hello.addon.ts
import type { AddonEntrypoint } from '@/loadAddons.js';

export const addon: AddonEntrypoint = async (context) => ({
  commands: async (program) => {
    program
      .command('hello')
      .description('A custom command that greets the user')
      .argument('[name]', 'The name to greet', 'World')
      .action(async (name) => {
        console.log(`Hello, ${name}!`);
        // You can access context properties like project paths
        console.log(`The project directory is: ${context.paths.projectDir}`);
      });
  },
});
```
After saving the file, your new command is immediately available:
```bash
./bin/env hello 'Awesome Developer'
# Output:
# Hello, Awesome Developer!
# The project directory is: /path/to/hawki-client-backend-php
```

### Adding NPM Dependencies

If your custom command requires a new Node.js dependency (e.g., an SDK or utility library), you must add it to the `bin/env` tool's own `package.json` file. Do not run `npm install` directly in the `bin/_env/` directory.

Instead, use the built-in `bin:npm` command, which ensures that the correct Node.js environment is used:
```bash
# To add a new dependency
./bin/env bin:npm install some-package

# To add a new development dependency
./bin/env bin:npm install --save-dev @types/some-package
```
This command will update `bin/_env/package.json` and install the package into `bin/_env/node_modules`, making it available to your addon scripts.

### Using the Events System

`bin/env` has an event system that allows addons to hook into its lifecycle. This is useful for running code before or after core actions. You can find a complete list of events by inspecting the source code, particularly files ending in `global.d.ts` within the `addons` directory.

For example, you could add a hook that runs before the `up` command starts the containers.

```typescript
// bin/_env/project/my-hook.addon.ts
import type { AddonEntrypoint } from '@/loadAddons.js';
import chalk from 'chalk';

export const addon: AddonEntrypoint = async (context) => ({
  events: async (events) => {
    // This event is fired just before 'docker compose up' is executed
    events.on('docker:up:before', async ({ args }) => {
      console.log(chalk.yellow('Custom hook: Containers are about to start!'));
      
      // You can even modify the arguments passed to the command.
      // For example, to force a build every time 'up' is called:
      // args.add('--build');
    });
  },
});
```
This simple but powerful system allows you to deeply integrate your custom logic into the `bin/env` workflow.

## Orchestrating the Magic: Enter HawkiClientBackend

With `bin/env` empowering your local development workflows through customizable hooks and seamless integrations, we now shift gears to the heart of your application's backend interactions. In the next chapter, we'll dive into [`HawkiClientBackend`: The Main Orchestrator](hawkiclientbackend-the-main-orchestrator-840305559.md), the central class that serves as your primary entry point into the library. Acting as a master chef, it effortlessly handles complex tasks behind the scenes – from querying the HAWKI API and managing connections to orchestrating end-to-end encryption – all triggered by a simple call to `getClientConfig()`. This component bridges your development tools with secure, efficient backend operations, ensuring your frontend receives the precise payload it needs. Let's explore how to instantiate and leverage this powerful orchestrator to streamline your application's core functionality.

