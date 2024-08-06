PHP_PATH := $(shell if [ ! -f "$(shell pwd)/bin/php7/bin/php" ]; then echo "$(shell which php)"; else echo "$(shell pwd)/bin/php7/bin/php"; fi;)

PHP = $(PHP_PATH) -dphar.readonly=0
# dir without /
PHP_DIR = $(dir $(PHP_PATH))
PHP_DIR := $(shell echo $(PHP_DIR) | sed 's/\/$$//g')
COMPOSER = ${shell pwd}/dev/composer.phar
PHP_CS_FIXER = ${shell pwd}/vendor/bin/php-cs-fixer
SUITE_TEST ?= normal

ENGINE_SOURCE_FILES = plugin.yml $(shell find src resources -type f) vendor
EXTENSION_DIR = $(shell find "$(shell pwd)/bin" -name "*debug-zts*" | tail -n 1)
$(shell mkdir -p dev && chmod 755 dev)

cs: vendor
	$(PHP) $(PHP_CS_FIXER) fix --verbose

cs/diff: vendor
	$(PHP) $(PHP_CS_FIXER) fix --dry-run --diff --ansi

php/install: Makefile
	wget https://github.com/pmmp/PHP-Binaries/releases/download/php-8.1-latest/PHP-Linux-x86_64-PM5.tar.gz
	tar -xzf PHP-Linux-x86_64-PM5.tar.gz
	rm PHP-Linux-x86_64-PM5.tar.gz
	grep -q '^extension_dir' bin/php7/bin/php.ini && sed -i'bak' "s{^extension_dir=.*{extension_dir=\"$(EXTENSION_DIR)\"{" bin/php7/bin/php.ini || echo "extension_dir=\"$(EXTENSION_DIR)\"" >> bin/php7/bin/php.ini

dev/composer.phar: Makefile
	cd dev && wget -O - https://getcomposer.org/installer | $(PHP)

phpstan: vendor
	PATH=$$PATH:$(PHP_DIR) php vendor/bin/phpstan analyse --memory-limit=4G

vendor: dev/composer.phar
	$(PHP) $(COMPOSER) install

composer/update: dev/composer.phar
	$(PHP) $(COMPOSER) update

composer/install: dev/composer.phar
	$(PHP) $(COMPOSER) install

unittests:
	export PHP_VERSION=8.2 && export PMMP_TAG=5.17.0 && make suitetest

suitetest:
	$(eval CONTAINER_PREFIX := unittests-suite)
	docker network create $(CONTAINER_PREFIX)-network > /dev/null || true
	echo $(SUITE_TEST)

	docker rm $(CONTAINER_PREFIX)-pocketmine > /dev/null || true
	docker build -t tests-runner --build-arg PHP_VERSION=$(PHP_VERSION) --build-arg PMMP_TAG=$(PMMP_TAG) .
	docker create --name $(CONTAINER_PREFIX)-pocketmine \
		--network $(CONTAINER_PREFIX)-network \
		-u root \
		--mount type=bind,source="$(shell pwd)/tests/pmmpunit/suitetest/$(SUITE_TEST)/tests",target=/data/plugin_data/PmmpUnit/tests \
		tests-runner \
		start-pocketmine > /dev/null

	docker cp tests/pmmpunit/shared/data $(CONTAINER_PREFIX)-pocketmine:/data/plugin_data > /dev/null
	docker cp tests/pmmpunit/suitetest/$(SUITE_TEST)/config $(CONTAINER_PREFIX)-pocketmine:/data/plugin_data > /dev/null
	docker cp tests/pmmpunit/suitetest/$(SUITE_TEST)/plugins $(CONTAINER_PREFIX)-pocketmine:/ > /dev/null

	docker start -ia $(CONTAINER_PREFIX)-pocketmine
	docker cp $(CONTAINER_PREFIX)-pocketmine:/data/plugin_data/PmmpUnit/results.txt /tmp/failed.txt
	docker rm $(CONTAINER_PREFIX)-pocketmine > /dev/null
	docker volume prune -f > /dev/null

	if [ `cat /tmp/failed.txt` -eq 0 ]; then echo "All test passed"; else echo "Some test failed"; exit 1; fi