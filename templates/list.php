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

	<div id="view_type">
		<input type="button" class="list" value="<?php echo $l->t('List')?>" />
		<input type="button" class="image" style="display:none" value="<?php echo $l->t('Image')?>" />
	</div>
</div>
<div id="leftcontent">
	<p id="tag_filter">
		<input type="text" placeholder="Filter By tag" value="<?php echo $_['req_tag']; ?>"/>
	</p>

	<label><?php echo $l->t('Related Tags'); ?></label>
	<ul class="tag_list">
	</ul>
	<label><?php echo $l->t('Shared with'); ?></label>

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
	<div class="centercontent">
		<img class="left_img svg" src="<?php echo OCP\image_path('bookmarks','triangle-w.svg'); ?>">
		<img class="right_img svg" src="<?php echo OCP\image_path('bookmarks','triangle-e.svg'); ?>"></div>

	<div class="bookmarks_list"></div>
	<div id="firstrun" style="display: none;">
		<?php
			echo $l->t('You have no bookmarks');
			$embedded = true;
			require_once(OC_App::getAppPath('bookmarks') .'/templates/bookmarklet.php');
			createBookmarklet(); 
		?>
</div>
<script>
	var fullTags = <?php echo $_['tags'];?>;
	var init_view = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'currentview', 'list');?>';
	var init_sidebar = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'sidebar', 'true');?>';
</script>
<div id="edit_dialog" style="display:none;">
<?php include 'addBm.php';?>
</div>