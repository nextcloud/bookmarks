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
						<%= encodeEntities(title == '' ? '' : url) %>
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
<script type="text/html" id="tag_tmpl">
	<li><a href="" class="tag"><%= tag %></a>
		<p class="tags_actions">
			<span class="tag_edit">
				<img class="svg" src="<?php echo OCP\image_path("", "actions/rename.svg");?>"
					title="<?php echo $l->t('Edit');?>">
			</span>
			<span class="tag_delete">
				<img class="svg" src="<?php echo OCP\image_path("", "actions/delete.svg");?>"
					title="<?php echo $l->t('Delete');?>">
			</span>
		</p>
		<em><%= nbr %></em>
	</li>
</script>