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
	<input type="text" id="add_url" value="" placeholder="<?php echo $l->t('Address'); ?>"/>
	<input type="submit" value="<?php echo $l->t('Add bookmark'); ?>" id="bookmark_add_submit" />

	<div id="view_type">
		<input type="button" class="list" value="<?php echo $l->t('List')?>" />
		<input type="button" class="image" style="display:none" value="<?php echo $l->t('Image')?>" />
	</div>
</div>
<div id="leftcontent">
	<div class="centercontent">
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
			require_once OC_App::getAppPath('bookmarks') .'/templates/bookmarklet.php' ;
			createBookmarklet(); 
		?>
</div>
<script>
	var fullTags = <?php echo $_['tags'];?>;
	var init_view = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'currentview', 'text');?>';
	var init_sidebar = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(), 'bookmarks', 'sidebar', 'true');?>';
	var shot_provider = '<?php echo OCP\Config::getUserValue(OCP\USER::getUser(),
		'bookmarks', 'shot_provider', 'http://screenshots.bookmarkly.com/thumb?url={url}');?>';
	//http://api.thumbalizr.com/?width={width}&url={url}
</script>

<script type="text/html" id="edit_dialog_tmpl">
<?php require 'addBm.php';?>
</script>


<script type="text/html" id="item_tmpl">
		<div class="bookmark_single" data-id="<%= id %>">
				<p class="bookmark_actions">
					<span class="bookmark_edit">
						<img class="svg" src="<?php echo OCP\image_path("", "actions/rename.svg");?>"
							title="<?php echo $l->t('Edit');?>">
					</span>
					<span class="bookmark_delete">
						<img class="svg" src="<?php echo OCP\image_path("", "actions/delete.svg");?>" 
							title="<?php echo $l->t('Delete');?>">
					</span>&nbsp;
				</p>
				<p class="bookmark_title">
					<a href="<%= encodeEntities(url) %>" target="_blank" class="bookmark_link">
						<%= encodeEntities(title == '' ? url : title ) %>
					</a>
				</p>
				<p class="bookmark_url">
					<a href="<%= encodeEntities(url) %>" target="_blank" class="bookmark_link">
						<%= encodeEntities(url) %>
					</a>
				</p>
				<p class="bookmark_date"><%= formatDate(added_date) %></p>
				<p class="bookmark_desc"><%= encodeEntities(description)%> </p>
			</div>
</script>

<script type="text/html" id="item_form_tmpl">
		<div class="bookmark_single_form" data-id="<%= id %>">
			<form method="post" action="<?php echo OCP\Util::linkTo('bookmarks', 'ajax/editBookmark.php');?>" >
					<input type="hidden" name="record_id" value="<%= id %>" />
				<p class="bookmark_form_title">
					<input type="text" name="title" placeholder="<?php echo $l->t('The title of the page');?>"
						value="<%= title %>"/>
				</p>
				<p class="bookmark_form_url">
					<input type="text" name="url" placeholder="<?php echo $l->t('The address of the page');?>"
						value="<%= encodeEntities(url)%>"/>
				</p>
				<div class="bookmark_form_tags"><ul>
					<% for ( var i = 0; i < tags.length; i++ ) { %>
						<li><%=tags[i]%></li>
					<% } %>
				</ul></div>
				<p class="bookmark_form_desc">
					<textarea name="description" placeholder="<?php echo $l->t('Description of the page');?>"
						><%= description%></textarea>
				</p>
				<p class="bookmark_form_submit"><button class="reset" ><?php echo $l->t('Cancel');?></button>
					<input type="submit" value="<?php echo $l->t('Save');?>">
				</p>
			</form>
		</div>
</script>
