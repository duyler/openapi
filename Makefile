.PHONY: tests
tests:
	docker-compose run --rm php vendor/bin/phpunit

.PHONY: infection
infection:
	docker-compose run --rm php vendor/bin/infection

.PHONY: psalm
psalm:
	docker-compose run --rm php vendor/bin/psalm

.PHONY: cs-fix
cs-fix:
	docker-compose run --rm php vendor/bin/php-cs-fixer fix

.PHONY: rector
rector:
	docker-compose run --rm php vendor/bin/rector

.PHONY: shell
shell:
	docker-compose run --rm php /bin/bash

.PHONY: coverage
coverage:
	docker-compose run --rm php vendor/bin/phpunit --coverage-html=coverage --coverage-text

.PHONY: update
update:
	docker-compose run --rm php composer update

.PHONY: init
init:
	@if [ -z "$(NAME)" ]; then \
		echo "Error: Please specify a package name."; \
		echo "Example: make init NAME=my-awesome-package"; \
		exit 1; \
	fi

	@echo "Starting to initialize the package: $(NAME)..."

	$(eval PASCAL_NAME=$(shell echo $(NAME) | sed 's/-/ /g' | awk '{for(i=1;i<=NF;i++){$$i=toupper(substr($$i,1,1)) substr($$i,2)}}1' OFS=''))

	@sed -i.bak 's/package-template/$(NAME)/g' composer.json && rm composer.json.bak
	@sed -i.bak 's/PackageTemplate/$(PASCAL_NAME)/g' composer.json && rm composer.json.bak

	@sed -i.bak 's/package-template/$(NAME)/g' README.md && rm README.md.bak
	@sed -i.bak 's/PackageTemplate/$(PASCAL_NAME)/g' README.md && rm README.md.bak

	@sed -i.bak 's/package-template/$(NAME)/g' sonar-project.properties && rm sonar-project.properties.bak
	@sed -i.bak 's/PackageTemplate/$(PASCAL_NAME)/g' sonar-project.properties && rm sonar-project.properties.bak

	@echo "Package name (kebab-case): $(NAME)"
	@echo "Namespace (PascalCase):  $(PASCAL_NAME)"

.PHONY: build
build:
	docker-compose build
	docker-compose run --rm php composer install

