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
	<div class="centercontent">
		<!--<img class="left_img svg" src="<?php echo OCP\image_path('bookmarks','triangle-w.svg'); ?>">
		<img class="right_img svg" src="<?php echo OCP\image_path('bookmarks','triangle-e.svg'); ?>">-->
		<span class="left_img"> <?php echo $l->t('Hide')?> &lt;&lt;</span>
		<span class="right_img"> <?php echo $l->t('Show')?> &gt;&gt;</span>
	</div>

	<p id="tag_filter">
		<input type="text" placeholder="Filter By tag" value="<?php echo $_['req_tag']; ?>"/>
	</p>

	<label><?php echo $l->t('Related Tags'); ?></label>
	<ul class="tag_list">
	</ul>

</div>

<div id="rightcontent" class="rightcontent">
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
	var init_view = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'currentview', 'text');?>';
	var init_sidebar = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'sidebar', 'true');?>';
	var shot_provider = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'shot_provider', 'http://screenshots.bookmarkly.com/thumb?url={url}');?>';
	//http://api.thumbalizr.com/?width={width}&url={url}
</script>
<div id="edit_dialog" style="display:none;">
<?php include 'addBm.php';?>
</div>