# Nextcloud Bookmarks
![Downloads](https://img.shields.io/github/downloads/nextcloud/bookmarks/total.svg?style=flat-square)
[![Code coverage](https://img.shields.io/codecov/c/github/nextcloud/bookmarks.svg?style=flat-square)](https://codecov.io/gh/nextcloud/bookmarks/)
[![Dependabot status](https://img.shields.io/badge/Dependabot-enabled-brightgreen.svg?longCache=true&style=flat-square&logo=dependabot)](https://dependabot.com)

![](https://github.com/nextcloud/bookmarks/raw/master/screenshots/Bookmarks.png)

> Bookmarks app for Nextcloud

This app provides you with a web interface for collecting and organizing bookmarks to the places on the web that are precious to you.
 
- 📂 Browse and filter your bookmarks via tags and folders.
- 📰 Write down additional notes
- 🔍 Built-in search integrated into Nextcloud's unified search
- 👪 Share bookmarks with other users as well as publicly
- ☠ Easily ind broken links
- 📔 Archive bookmarked files 
- 📲 Access your bookmarks anywhere, via a built-in REST API
- 💡 Keep track of changes in the activity stream
- 💼 Includes a Dashboard widget
- ⚛ Easily generate public and private RSS feeds of your collections


## Third-party clients

### Android
- [Nextcloud Bookmarks](https://gitlab.com/bisada/OCBookmarks) - client app for Android ([new PlayStore entry](https://play.google.com/store/apps/details?id=org.bisw.nxbookmarks))
- [NCBookmark](https://gitlab.com/lenchan139/NCBookmark) - Android App

### Browser
- [Owncloud Bookmarks](https://chrome.google.com/webstore/detail/owncloud-bookmarks/eomolhpeokmbnincelpkagpapjpeeckc?hl=de) - Bookmarks extension for Chromium-based browsers (Chromium/Chrome/Opera/Vivaldi)
- [Floccus](https://github.com/marcelklehr/floccus) - Bookmark sync for Firefox/Chromium-based browsers
- [FreedomMarks](https://github.com/damko/freedommarks-browser-webextension) - Addon for Firefox and Chrome. No sync, just a client.

### Desktop
- [Nextcloud Bookmark Manager](https://www.midwinter-dg.com/mac-apps/nextcloud-bookmark-manager.html) A MacOS client
- [QOwnNotes](https://www.qownnotes.org/) - Plain-text file markdown note taking desktop application (no sync, just importing bookmarks)

### iOS
- [Nextbookmark](https://gitlab.com/altepizza/nextbookmark) - A minimal client for iOS ([App Store entry](https://apps.apple.com/de/app/nextbookmark/id1500340092))

### Other
- [uMarks](https://open-store.io/app/umarks.ernesst) - App for Ubuntu touch


## Community
Talk to us on [gitter](https://gitter.im/nextcloud-bookmarks/community) or in #nextcloud on freenode.net (IRC)!

## Install

### Requirements

- php 7.3 and above

PHP extensions:

- intl: \*
- mbstring: \*

### One-click

Install this app in the app store of your nextcloud instance (you must have administrator privileges). You will find it in the 'Organization' category.

### Manual install

#### Dependencies

- [git](https://git-scm.org/)
- [Node.js and npm](https://nodejs.org/)
- [php](https://php.net/)
- [composer](https://getcomposer.org/)

#### Setup

```
cd /path/to/nextcloud/apps/
git clone https://github.com/nextcloud/bookmarks.git
cd bookmarks
composer install
npm install
npm run build
```

## API

This app exposes a public REST API that third-party clients can interface with.

[Head over to the API docs](https://nextcloud-bookmarks.readthedocs.io/en/latest/).

## Maintainers

- [Marcel Klehr](https://github.com/marcelklehr)

## Donate

If you'd like to support the creation and maintenance of this software, consider donating.

| [<img src="https://img.shields.io/badge/paypal-donate-blue.svg?logo=paypal&style=for-the-badge">](https://www.paypal.me/marcelklehr1) | [<img src="http://img.shields.io/liberapay/receives/marcelklehr.svg?logo=liberapay&style=for-the-badge">](https://liberapay.com/marcelklehr/donate) |[<img src="https://img.shields.io/badge/github-sponsors-violet.svg?logo=github&style=for-the-badge">](https://github.com/sponsors/marcelklehr) |
| :-----------------------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------: |:--:|


## Contribute

We always welcome contributions. Have an issue or an idea for a feature? Let us know. Additionally, we happily accept pull requests.

In order to make the process run more smoothly, you can make sure of the following things:

- Announce that you're working on a feature/bugfix in the relevant issue
- Make sure the tests are passing
- If you have any questions you can let the maintainers above know privately via email, or simply open an issue on github

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

More information on how to contribute: https://nextcloud.com/contribute/

Happy hacking :heart:

## License

This software is licensed under the terms of the AGPL written by the Free Software Foundation and available at [COPYING](./COPYING).
