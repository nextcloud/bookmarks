<?php 
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * Copyright (c) 2011 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
?>
<input type="hidden" id="bookmarkFilterTag" value="<?php echo $_['req_tag']; ?>" />
<div id="controls">
	<input type="submit" value="<?php echo $l->t('New bookmark'); ?>" id="bookmark_add_submit" />
</div>
<div id="leftcontent">
	<p id="tag_filter">
		<input type="text" placeholder="Filter By tag" value="<?php echo $_['req_tag']; ?>"/> <a href="javascript:bookmarks_page = 0;	$('.bookmarks_list').empty();getBookmarks()">go</a>
	</p>

	<label><?php echo $l->t('Related Tags'); ?></label>
	<ul class="tag_list">
	</ul>
	<label><?php echo $l->t('Shared with'); ?></label>
	<hr />
	<ul class="share_list">
		<?php foreach($_['shared'] as $users):?>
			<li><span class="tag"><?php echo $users['name'];?></span>
				<p class="tags_actions">
					<span class="bookmark_edit">
						<img class="svg" src="<?php echo OCP\image_path('core','actions/rename.svg') ?>" title="Edit">
					</span>
					<span class="bookmark_delete">
						<img class="svg" src="<?php echo OCP\image_path('core','actions/delete.svg') ?>" title="Delete">
					</span>
				</p>
				<em><?php echo $users['nbr'];?></em>
			</li>
		<?php endforeach;?>
	<ul>
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