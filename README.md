# Richie Wordpress Plugin

## Richie News Plugin

The actual plugin code is in `richie` folder.

## Richie Edition Plugin

Separate plugin for migrating richie editions e-papers to wordpress. The plugin is included as git submodule in richie-editions-wp folder.

## Setting up dev environment

Expecting podman and podman-compose to be installed.

### Start containers

This installs images and starts containers in background. Wordpress `wp-content` folder is exposed locally, it will contain
all installed themes and plugins (except the richie plugin, which has separate mapping).

```shell
podman-compose up -d
```

Edit `/etc/hosts` and add:

```text
127.0.0.1       wordpress.local
```

### Stopping containers

```shell
podman-compose down
```

### Setting up wordpress

Dockerized wordpress installation should now be available in `http://wordpress.local:8234`. Visit the address and run wordpress
setup. Activate Richie plugin in wordpress admin ui.

You can also do core install with cli:

```shell
podman-compose run --rm cli wp core install --url="http://wordpress.local:8234" --title="Richie Dev" --admin_user="admin" --admin_password="password" --admin_email="admin@example.com"
```

### Access wordpress container

```shell
podman-compose run --rm wordpress bash
```

## Setup testsuite

Test suite was setup using wp cli scaffold. No need to run this again, but documenting the command:

```shell
podman-compose run --rm cli wp scaffold plugin-tests richie
```

The testing environment is provided by a separate Docker Compose file (docker-compose.phpunit.yml) to ensure isolation. To use it, you must first start it, then manually run your test installation script.

```shell
podman-compose -f docker-compose.yml -f docker-compose.phpunit.yml up -d
```

```shell
podman-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit /app/bin/install-wp-tests.sh wordpress_test root '' mysql_phpunit latest true
```

This installs wordpress and tests into an isolated container. Plugin code is mapped under `/app`.

### Running tests

```shell
podman-compose -f docker-compose.phpunit.yml run --rm wordpress_phpunit phpunit
```

## Troubleshooting

Show wordpress logs:

```shell
podman-compose logs wordpress
```
