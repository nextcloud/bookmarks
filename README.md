# Nextcloud Bookmarks

![](https://github.com/nextcloud/bookmarks/raw/master/screenshots/Bookmarks.png)

> Bookmarks app for Nextcloud

This app provides you with a web interface for collecting and organizing bookmarks to the sites on the web that are precious to you. You can browse and filter your bookmarks via the tags you give them and by using the built-in search feature. Furthermore, in order to be able to access your bookmarks anywhere, it also allows you to synchronize third-party clients via a built-in REST API.

## Background
The bookmarks app is quite old and has gone through many hands. It is now more relevant than ever, as the nextcloud app providing support for Firefox Sync doesn't work with the latest versions of Firefox anymore. Currently, there are efforts to make it live up to the expectations produced by the gap that was left by the firefox sync app.

## Install
### One-click
Install this app in the app store of your nextcloud instance (you must have administrator privileges).

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
- [Nextcloud Bookmarks](https://github.com/theScrabi/OCBookmarks) - Android app with full add/edit/delete and view functionallity
- [Floccus](https://github.com/marcelklehr/floccus) - Bookmark sync for Firefox/Chromium-based browsers
- [NCBookmarks](https://github.com/lenchan139/NCBookmark) - App to view/edit/open bookmarks for Android
- [uMarks](https://uappexplorer.com/app/umarks.ernesst) - App for Ubuntu touch
- [Freedommarks](https://github.com/damko/freedommarks-browser-webextension) - Bookmarks extension with search for Firefox/Chromium-based browsers

## API
This app exposes a public REST API that third-party clients can interface with.

[Go to the API docs](./API.md).

## Maintainers
- [Blizzz](https://github.com/Blizzz)
- [Marcel Klehr](https://github.com/marcelklehr)

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
