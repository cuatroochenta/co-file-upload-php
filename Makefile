SHELL := /bin/sh

DOCKER_COMPOSE ?= docker compose -f docker-compose.yaml
PHP_SERVICE ?= php

.DEFAULT_GOAL := help

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z0-9_\-]+:.*##/ {printf "%-24s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# DOCKER COMPOSE COMMANDS
.PHONY: dc-up dc-up-d dc-up-d-rebuild dc-down dc-build dc-run

dc-up: ## docker compose: Create and run containers.
	@$(DOCKER_COMPOSE) up

dc-up-d: ## docker compose: Create and run containers in background.
	@$(DOCKER_COMPOSE) up -d

dc-up-d-rebuild: ## docker compose: Create and run and rebuild containers in background.
	@$(DOCKER_COMPOSE) up -d --build --force-recreate

dc-down: ## docker compose: Stop and remove containers.
	@$(DOCKER_COMPOSE) down

dc-build: ## docker compose: Build the php image.
	@$(DOCKER_COMPOSE) build $(PHP_SERVICE)

dc-run: ## docker compose: Run a one-off command in the php container. Usage: make dc-run COMMAND="php -v"
	@$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) sh -lc "$(COMMAND)"

# COMPOSER COMMANDS
.PHONY: composer composer-install composer-update

composer-install: ## composer: Install dependencies.
	@$(MAKE) dc-run COMMAND="composer install --no-interaction"

composer-update: ## composer: Update dependencies.
	@$(MAKE) dc-run COMMAND="composer update -W"

composer: ## composer: Run composer with ARGS. Usage: make composer ARGS="validate"
	@$(MAKE) dc-run COMMAND="composer $(ARGS)"

# QA / VALIDATION COMMANDS
.PHONY: composer-validate composer-audit composer-check-platform

composer-validate: ## qa: composer validate
	@$(MAKE) dc-run COMMAND="composer validate"

composer-audit: ## qa: composer audit
	@$(MAKE) dc-run COMMAND="composer audit"

composer-check-platform: ## qa: composer check-platform-reqs
	@$(MAKE) dc-run COMMAND="composer check-platform-reqs"

.PHONY: codecept-build tests-unit tests-acceptance tests-functional tests-api tests-all

codecept-build: ## qa: generate Codeception _generated actions
	@$(MAKE) dc-run COMMAND="vendor/bin/codecept build"

tests-unit: ## qa: run unit tests
	@$(MAKE) dc-run COMMAND="vendor/bin/codecept run Unit"

tests-acceptance: ## qa: run acceptance tests
	@$(MAKE) dc-run COMMAND="vendor/bin/codecept run Acceptance"

tests-functional: ## qa: run functional tests
	@$(MAKE) dc-run COMMAND="vendor/bin/codecept run Functional"

tests-api: ## qa: run api tests
	@$(MAKE) dc-run COMMAND="vendor/bin/codecept run Api"

tests-all: ## qa: run all Codeception suites
	@$(MAKE) codecept-build
	@$(MAKE) tests-unit
	@$(MAKE) tests-functional
	@$(MAKE) tests-api
	@$(MAKE) tests-acceptance

.PHONY: phpstan psalm phpcs phpcbf

phpstan: ## qa: run PHPStan
	@$(MAKE) dc-run COMMAND="vendor/bin/phpstan analyse src tests --memory-limit=1G"

psalm: ## qa: run Psalm
	@$(MAKE) dc-run COMMAND="vendor/bin/psalm"

phpcs: ## qa: run PHPCS (PSR12)
	@$(MAKE) dc-run COMMAND="vendor/bin/phpcs --standard=PSR12 src tests"

phpcbf: ## qa: auto-fix PHPCS issues (PSR12)
	@$(MAKE) dc-run COMMAND="vendor/bin/phpcbf --standard=PSR12 src tests"

.PHONY: validate
validate: ## qa: run full validation (composer + tests + static analysis + lint)
	@$(MAKE) composer-install
	@$(MAKE) composer-validate
	@$(MAKE) composer-audit
	@$(MAKE) composer-check-platform
	@$(MAKE) tests-all
	@$(MAKE) phpstan
	@$(MAKE) psalm
	@$(MAKE) phpcs
