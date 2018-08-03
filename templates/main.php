<?php
script('bookmarks', 'dist/main.bundle');
style('bookmarks', 'bookmarks');

style('bookmarks', 'select2');
?>

<div id="app-navigation">
	<div id="add-bookmark-slot"></div>
	<div id="navigation-slot"></div>
	<h3><span class="icon-tag"></span> <?php p($l->t('Tags')); ?></h3>
	<div id="favorite-tags-slot"></div>
	<div id="settings-slot"></div>
</div>
<div id="app-content">
</div>
