# CI/CD Pipeline

Building on the infrastructure setup and management outlined in [Infrastructure](infrastructure-610545213.md), the CI/CD pipelines provide the automated workflows that ensure seamless integration and deployment of the `hawki-client-backend-php` project.

This document describes the Continuous Integration and Continuous Delivery (CI/CD) pipelines configured for the `hawki-client-backend-php` project using GitHub Actions. These pipelines help automate testing and releasing to ensure code quality and stability.

## Create new Release (PHP) (`release.yml`)

This pipeline automates the testing and release process for the PHP backend client. It ensures that any new code merged into the `main` branch is tested across multiple PHP versions before being automatically versioned and released.

### Overview

The primary purpose of this workflow is to maintain code quality and streamline the release cycle. When relevant changes are pushed to the `main` branch, it runs a comprehensive test suite. If all tests pass, it automatically generates a new release, including an updated version number in `composer.json` and a changelog based on the project's commit history.

### Triggers

This pipeline is configured to run in the following scenarios:

*   **On push to `main`**: The pipeline automatically starts whenever a commit is pushed to the `main` branch, but only if the changes affect files in the `src/` or `tests/` directories, the `composer.json` file, or the workflow file itself (`release.yml`).
*   **Manual Trigger**: The pipeline can be run manually from the "Actions" tab in the GitHub repository via the `workflow_dispatch` event.

### Jobs

The pipeline consists of two main jobs that run in sequence:

1.  **`test`**: This job is responsible for running the project's test suite to verify code correctness and compatibility.
    *   It uses a "matrix strategy" to run the tests across multiple PHP versions (**8.2, 8.3, and 8.4**).
    *   It caches Composer dependencies to speed up subsequent runs.
    *   It installs the project dependencies using `composer update`.
    *   It executes the PHPUnit test suite and generates a code coverage report.
    *   On the PHP 8.4 run, it uploads the code coverage report to [Codecov](https://about.codecov.io/) to track test coverage over time.

2.  **`release`**: This job handles the creation of a new package release.
    *   It depends on the `test` job, meaning it will only run if all tests on all PHP versions complete successfully.
    *   It uses the `conventional-changelog-action` to analyze commit messages since the last release.
    *   Based on the commits (which should follow the [Conventional Commits](https://www.conventionalcommits.org/) specification), it automatically determines the new version number (e.g., a patch, minor, or major update).
    *   It then creates a new GitHub Release with an automatically generated changelog.

### Deployment

This workflow handles the "deployment" of new package versions by publishing them as **GitHub Releases**.

The `release` job automates the following steps:
1.  Analyzes the commit history to determine the correct new version number.
2.  Updates the `version` field in the `composer.json` file.
3.  Commits this change, creates a new Git tag, and pushes both to the repository.
4.  Publishes a new formal GitHub Release, complete with a changelog detailing the new features, bug fixes, and other changes included in the new version.

This process makes the new version of the client library officially available for consumption by other projects.

### Required Secrets

*   `secrets.CODECOV_TOKEN`: This token is required for the `test` job to securely upload code coverage reports to Codecov. It must be generated from your project's settings page on the Codecov website and added to your GitHub repository's secrets.
*   `secrets.github_token`: This is a special, temporary token that GitHub Actions provides to the workflow automatically. The `release` job uses it to gain permission to create a GitHub Release and push the version bump commit back to the repository. No manual setup is required for this secret, as it is provided by the platform.

