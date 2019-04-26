# Richie Wordpress Plugin

The actual plugin code is in `richie` folder.

## Setting up dev environment

Expecting docker and docker-compose to be installed.

### Start containers

This installs images and starts containers in background. Wordpress `wp-content` folder is exposed locally, it will contain
all installed themes and plugins (except the richie plugin, which has separate mapping).

```shell
docker-compose up -d
```

Edit `/etc/hosts` and add:

```text
127.0.0.1       wordpress.local
```

### Stopping containers

```shell
docker-compose down
```

### Setting up wordpress

Dockerized wordpress installation should now be available in `http://wordpress.local`. Visit the address and run wordpress
setup. Activate Richie plugin in wordpress admin ui. Richie plugin currently depends on pmpro plugin, so that needs to be installed too.

### Access wordpress container

```shell
docker-compose run --rm wordpress bash
```

## Setup testsuite

Test suite was setup using wp cli scaffold. No need to run this again, but documenting the command:

```shell
docker-compose run --rm cli wp scaffold plugin-tests richie
```

The testing environment is provided by a separate Docker Compose file (docker-compose.phpunit.yml) to ensure isolation. To use it, you must first start it, then manually run your test installation script.

```shell
docker-compose -f docker-compose.yml -f docker-compose.phpunit.yml up -d
```

```shell
docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit /app/bin/install-wp-tests.sh wordpress_test root '' mysql_phpunit latest true
```

This installs wordpress and tests into an isolated container. Plugin code is mapped under `/app`.

### Running tests

```shell
docker-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit phpunit
```

## Troubleshooting

Show wordpress logs:

```shell
docker-compose logs wordpress
```
