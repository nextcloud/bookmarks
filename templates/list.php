<?php 
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * Copyright (c) 2011 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
?>
<input type="hidden" id="bookmarkFilterTag" value="<?php if(isset($_GET['tag'])) echo OCP\Util::sanitizeHTML($_GET['tag']); ?>" />
<div id="controls">
	<input type="submit" value="<?php echo $l->t('New bookmark'); ?>" id="bookmark_add_submit" />
</div>
<div id="leftcontent">
	<p id="tag_filter">
		<input type="text" placeholder="Filter By tag" />
	</p>

	<ul class="tag_list">
		<?php foreach($_['tags'] as $tag):?>
			<li><span><?php echo $tag['tag'];?></span><a class="close"></a></li>
		<?php endforeach;?>
	</ul>
</div>
<div id="rightcontent" class="rightcontent">
	<div class="bookmarks_list"></div>
	<div id="firstrun" style="display: none;">
		<?php
			echo $l->t('You have no bookmarks');
			require_once(OC_App::getAppPath('bookmarks') .'/templates/bookmarklet.php');
			createBookmarklet(); 
		?>
</div>