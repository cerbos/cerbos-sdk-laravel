DIRS := src

.PHONY: all
all: lint validate-composer test

.PHONY: lint
lint:
	vendor/bin/parallel-lint $(DIRS)

.PHONY: validate-composer
validate-composer:
	composer validate

.PHONY: test
test:
	vendor/bin/phpunit --no-coverage
