# Nextcloud Bookmarks

![Downloads](https://img.shields.io/github/downloads/nextcloud/bookmarks/total.svg?style=flat-square)
[![Code coverage](https://img.shields.io/codecov/c/github/nextcloud/bookmarks.svg?style=flat-square)](https://codecov.io/gh/nextcloud/bookmarks/)
[![Dependabot status](https://img.shields.io/badge/Dependabot-enabled-brightgreen.svg?longCache=true&style=flat-square&logo=dependabot)](https://dependabot.com)

![](https://github.com/nextcloud/bookmarks/raw/master/screenshots/Bookmarks.png)

> Collect and manage bookmarks, synced with all your devices

This app provides you with a web interface for collecting and organizing bookmarks to the places on the web that are precious to you.

- 📂 Sort bookmarks into folders
- 🏷 Add tags and personal notes
- ☠ Find broken links and duplicates
- 📲 Synchronize with all your browsers and devices
- 📔 Store archived versions of your links in case they are depublished
- 🔍 Full-text search on site contents
- 👪 Share bookmarks with other users and via public links
- ⚛ Generate RSS feeds of your collections
- 📈 Stats on how often you access which links
- 🔒 Automatic backups of your bookmarks collection
- 💼 Built-in Dashboard widgets for frequent and recent links

## Third-party clients

### Android

- [Nextcloud Bookmarks](https://gitlab.com/bisada/OCBookmarks) - client app for Android ([Amazon Appstore](https://www.amazon.com/dp/B08L5RKHMM/ref=apps_sf_sta)/[F-Droid](https://f-droid.org/packages/org.schabi.nxbookmarks))
- [NCBookmark](https://gitlab.com/lenchan139/NCBookmark) - Android App
- Bookmarks for Nextcloud - client app for Android (phone and tablet) with folders, tags, search, sync ([Google Play Store - Bookmarks for Nextcloud](https://play.google.com/store/apps/details?id=de.emasty.bookmarks))
- [Floccus](https://floccus.org) - Bookmark sync as Browser extension for Firefox/Chromium-based browsers, Android & iOS Apps

### Browser

- [Owncloud Bookmarks](https://chrome.google.com/webstore/detail/owncloud-bookmarks/eomolhpeokmbnincelpkagpapjpeeckc) - Bookmarks extension for Chromium-based browsers (Chromium/Chrome/Opera/Vivaldi)
- [Floccus](https://floccus.org/) - Bookmark sync as Browser extension for Firefox/Chromium-based browsers, Android & iOS Apps
- [FreedomMarks](https://github.com/damko/freedommarks-browser-webextension) - Addon for Firefox and Chrome. No sync, just a client.
- [add-nextcloud-bookmarks](https://github.com/qutebrowser/qutebrowser/blob/master/misc/userscripts/README.md) - qutebrowser userscript that allows for easy bookmark creation
- [Bookmarker for Nextcloud](https://plushbyte.com/browser-extensions/bookmarker-for-nextcloud/) - Easily add bookmarks (Chrome extension)

### Desktop

- [Nextcloud Bookmark Manager](https://www.midwinter-dg.com/mac-apps/nextcloud-bookmark-manager.html) A MacOS client
- [QOwnNotes](https://www.qownnotes.org/) - Plain-text file markdown note taking desktop application (no sync, just importing bookmarks)

### iOS

- [Nextbookmark](https://gitlab.com/altepizza/nextbookmark) - A minimal client for iOS ([App Store entry](https://apps.apple.com/app/nextbookmark/id1500340092))
- [Onion Browser](https://onionbrowser.com) - Tor browser support syncing ([App Store entry](https://apps.apple.com/app/onion-browser/id519296448))
- [Floccus](https://floccus.org) - Bookmark sync as Browser extension for Firefox/Chromium-based browsers, Android & iOS Apps

### Other

- [uMarks](https://open-store.io/app/umarks.ernesst) - App for Ubuntu touch

## Community

Talk to us on [gitter](https://gitter.im/nextcloud-bookmarks/community), via matrix `#nextcloud-bookmarks_community:gitter.im` or in our [official Talk channel](https://cloud.nextcloud.com/call/u52jcby9)

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
- [php](https://php.net/), extension dom and tokenizer
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

| [<img src="https://img.shields.io/badge/paypal-donate-blue.svg?logo=paypal&style=for-the-badge">](https://www.paypal.me/marcelklehr1) | [<img src="http://img.shields.io/liberapay/receives/marcelklehr.svg?logo=liberapay&style=for-the-badge">](https://liberapay.com/marcelklehr/donate) | [<img src="https://img.shields.io/badge/github-sponsors-violet.svg?logo=github&style=for-the-badge">](https://github.com/sponsors/marcelklehr) |
| :-----------------------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------: | :--------------------------------------------------------------------------------------------------------------------------------------------: |

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
