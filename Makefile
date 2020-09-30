app_name=bookmarks

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=3.4.1

all: dev-setup build-js-production

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

# Dev env management
dev-setup: clean clean-dev npm-init

npm-init:
	npm ci

npm-update:
	npm update

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
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
