#!/bin/bash
set -e

VERSIONS=("8.0" "8.4")

for VERSION in "${VERSIONS[@]}"; do
    echo "=== Testing with PHP $VERSION ==="

    echo "Installing WP test suite..."
    PHP_VERSION=$VERSION podman compose -f docker-compose.phpunit.yml run --rm --build wordpress_phpunit \
        /app/bin/install-wp-tests.sh wordpress_test root '' host.containers.internal latest true

    echo "Running Richie tests on PHP $VERSION..."
    PHP_VERSION=$VERSION podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit \
        sh -c "cd /app && phpunit"

    echo "Running Richie Editions tests on PHP $VERSION..."
    PHP_VERSION=$VERSION podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit \
        sh -c "cd /app-editions && phpunit"
done
