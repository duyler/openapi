.PHONY: tests
tests:
	docker-compose run --rm php vendor/bin/phpunit

.PHONY: psalm
psalm:
	docker-compose run --rm php vendor/bin/psalm

.PHONY: cs-fix
cs-fix:
	docker-compose run --rm php vendor/bin/php-cs-fixer fix

.PHONY: rector
rector:
	docker-compose run --rm php vendor/bin/rector

.PHONY: build
build:
	docker-compose build
	docker-compose run --rm php composer install

.PHONY: shell
shell:
	docker-compose run --rm php /bin/bash

