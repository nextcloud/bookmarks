app_name=bookmarks

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
cert_url=https://github.com/nextcloud/app-certificate-requests/raw/master/$(app_name)/$(app_name).crt
# Path to the occ of a Nextcloud server install, used for code signing. Override with `make appstore occ=/path/to/nextcloud/occ`
occ=$(CURDIR)/../../occ
version+=17.0.0

all: dev-setup build-js-production composer-no-dev

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

# Dev env management
dev-setup: clean clean-dev npm-init composer-install

npm-init:
	npm ci

npm-update:
	npm update

composer-install:
	composer install --ignore-platform-req=php

composer-no-dev:
	composer install --no-dev --ignore-platform-req=php

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

# Linting
lint:
	npm run lint

lint-fix:
	npm run lint:fix

# Style linting
stylelint:
	npm run stylelint

stylelint-fix:
	npm run stylelint:fix

# Cleaning
clean:
	rm -rf js
	rm -rf $(sign_dir)

clean-dev:
	rm -rf node_modules

appstore:
	mkdir -p $(sign_dir)
	rsync -a \
	--include=/js \
	--include=/vendor \
	--include=/CHANGELOG.md \
	--include=/README.md \
	--include=/composer.json \
	--include=/composer.lock \
	--include=/vendor \
	--include=/tests \
	--include=/templates \
	--include=/package.json \
	--include=/package-lock.json \
	--include=/src \
	--include=/lib \
	--include=/l10n \
	--include=/img \
	--include=/appinfo \
	--exclude=**/*.map \
	--exclude=/* \
	$(project_dir)/ $(sign_dir)/$(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		if [ ! -f $(cert_dir)/$(app_name).crt ]; then \
			echo "Downloading app certificate…"; \
			curl -sSfL -o $(cert_dir)/$(app_name).crt $(cert_url) || exit 1; \
		fi; \
		if [ ! -f $(occ) ]; then \
			echo "Cannot sign app: occ not found at $(occ). Run with occ=/path/to/nextcloud/occ"; \
			exit 1; \
		fi; \
		echo "Signing app code…"; \
		php $(occ) integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key \
			--certificate=$(cert_dir)/$(app_name).crt \
			--path=$(sign_dir)/$(app_name) || exit 1; \
	fi
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
