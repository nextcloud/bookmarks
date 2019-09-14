# Nextcloud Bookmarks

![](https://github.com/nextcloud/bookmarks/raw/master/screenshots/Bookmarks.png)

> Bookmarks app for Nextcloud

This app provides you with a web interface for collecting and organizing bookmarks to the sites on the web that are precious to you. You can browse and filter your bookmarks via tags and folders and by using the built-in search feature. Furthermore, in order to access your bookmarks anywhere, it also allows you to synchronize third-party clients via a built-in REST API -- in your browsers and on your phone.

## Install

### Requirements

- php v7.1+

PHP extensions:

- gmp: \*
- intl: \*
- mbstring: \*

### One-click

Install this app in the app store of your nextcloud instance (you must have administrator privileges). You will find it in the 'Organization' category.

### Manual install

#### Dependencies

- [git](https://git-scm.org/)
- [Node.js and npm](https://nodejs.org/)
- [php](https://php.net/)
- [composer](https://getcompoert.org/)

#### Setup

```
cd /path/to/nextcloud/apps/
git clone https://github.com/nextcloud/bookmarks.git
cd bookmarks
composer install
npm install
npm run build
```

## Third-party clients

### Android
- [Nextcloud Bookmarks](https://gitlab.com/bisada/OCBookmarks) - client app for Android ([new PlayStore entry](https://play.google.com/store/apps/details?id=org.bisw.nxbookmarks))
- [NCBookmarks](https://github.com/lenchan139/NCBookmark) - Android App

### Browser
- [Owncloud Bookmarks](https://chrome.google.com/webstore/detail/owncloud-bookmarks/eomolhpeokmbnincelpkagpapjpeeckc?hl=de) - Bookmarks extension for Chromium-based browsers (Chromium/Chrome/Opera/Vivaldi)
- [Floccus](https://github.com/marcelklehr/floccus) - Bookmark sync for Firefox/Chromium-based browsers
- [FreedomMarks](https://github.com/damko/freedommarks-browser-webextension) - Addon for Firefox and Chrome. No sync, just a client.

### Desktop
- [Nextcloud Bookmark Manager](https://www.midwinter-dg.com/mac-apps/nextcloud-bookmark-manager.html) A MacOS client
- [QOwnNotes](https://www.qownnotes.org/) - Plain-text file markdown note taking desktop application (no sync, just importing bookmarks)

### Other
- [uMarks](https://uappexplorer.com/app/umarks.ernesst) - App for Ubuntu touch

## API

This app exposes a public REST API that third-party clients can interface with.

[Head over to the API docs](https://nextcloud-bookmarks.readthedocs.io/en/latest/).

## Maintainers

- [Blizzz](https://github.com/Blizzz)
- [Marcel Klehr](https://github.com/marcelklehr)

## Donate

If you'd like to support the creation and maintenance of this software, consider donating.

| [<img src="https://img.shields.io/badge/paypal-donate-blue.svg?logo=paypal&style=for-the-badge">](https://www.paypal.me/marcelklehr1) | [<img src="http://img.shields.io/liberapay/receives/marcelklehr.svg?logo=liberapay&style=for-the-badge">](https://liberapay.com/marcelklehr/donate) |
| :-----------------------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------: |


## Contribute

We always welcome contributions and happily accept pull requests.

In order to make the process run more smoothly, you can make sure of the following things:

- Announce that you're working on a feature/bugfix in the relevant issue
- Make sure the tests are passing
- If you have any questions you can let the maintainers above know privately via email, or simply open an issue on github

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

More information on how to contribute: https://nextcloud.com/contribute/

Happy hacking :heart:

## License

This software is licensed under the terms of the AGPL written by the Free Software Foundation and available at [COPYING](./COPYING).
