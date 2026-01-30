#!/bin/bash
set -e

VERSIONS=("7_4" "8_0" "8_4")

for VERSION in "${VERSIONS[@]}"; do
    echo "=== Testing with PHP $VERSION ==="

    echo "Installing WP test suite..."
    podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit_${VERSION} \
        /app/bin/install-wp-tests.sh wordpress_test root '' host.containers.internal latest true

    echo "Running Richie tests on PHP $VERSION..."
    podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit_${VERSION} \
        sh -c "cd /app && phpunit"

    echo "Running Richie Editions tests on PHP $VERSION..."
    podman compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit_${VERSION} \
        sh -c "cd /app-editions && phpunit"
done