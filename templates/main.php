<?php
script('bookmarks', 'bookmarks.main');
?>
<link rel="manifest" href="<?php link_to('bookmarks', 'manifest.json') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="white">
<meta name="apple-mobile-web-app-title" content="Bookmarks">
<link rel="apple-touch-icon" href="<?php link_to('bookmarks', 'img/icon-152x152.png') ?>">
<div id="vue-content"></div>
