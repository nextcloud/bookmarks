<script type="text/html" id="item_tmpl">
		<div class="bookmark_single" data-id="<&= id &>">
				<p class="bookmark_actions">
					<span class="bookmark_delete">
						<img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/delete.svg"));?>"
							title="<?php p($l->t('Delete'));?>">
					</span>&nbsp;
				</p>
				<p class="bookmark_title">
					<a href="<&= encodeURI(url) &>" target="_blank" class="bookmark_link" rel="noreferrer">
						<&= escapeHTML(title == '' ? encodeURI(url) : title ) &>
					</a>
					<span class="bookmark_edit bookmark_edit_btn">
						<img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/rename.svg"));?>" title="<?php p($l->t('Edit'));?>">
					</span>
				</p>
				<span class="bookmark_desc"><&= escapeHTML(description)&> </span>
				<span class="bookmark_date"><&= formatDate(added_date) &></span>
			</div>
</script>

<script type="text/html" id="item_form_tmpl">
		<div class="bookmark_single_form" data-id="<&= id &>">
			<form method="post" action="<?php p(OCP\Util::linkTo('bookmarks', 'ajax/editBookmark.php'));?>" >
					<input type="hidden" name="record_id" value="<&= id &>" />
				<p class="bookmark_form_title">
					<input type="text" name="title" placeholder="<?php p($l->t('The title of the page'));?>"
						value="<&= escapeHTML(title) &>"/>
				</p>
				<p class="bookmark_form_url">
					<input type="text" name="url" placeholder="<?php p($l->t('The address of the page'));?>"
						value="<&= encodeURI(url)&>"/>
				</p>
				<div class="bookmark_form_tags"><ul>
					<& for ( var i = 0; i < tags.length; i++ ) { &>
						<li><&= escapeHTML(tags[i]) &></li>
					<& } &>
				</ul></div>
				<p class="bookmark_form_desc">
					<textarea name="description" placeholder="<?php p($l->t('Description of the page'));?>"
						><&= escapeHTML(description) &></textarea>
				</p>
				<p class="bookmark_form_submit">
					<button class="reset" ><?php p($l->t('Cancel'));?></button>
					<input type="submit" class="primary" value="<?php p($l->t('Save'));?>">
				</p>
			</form>
		</div>
</script>
<script type="text/html" id="tag_tmpl">
	<li><a href="" class="tag"><&= escapeHTML(tag) &></a>
		<p class="tags_actions">
			<span class="tag_edit">
				<img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/rename.svg"));?>"
					title="<?php p($l->t('Edit'));?>">
			</span>
			<span class="tag_delete">
				<img class="svg" src="<?php print_unescaped(OCP\image_path("", "actions/delete.svg"));?>"
					title="<?php p($l->t('Delete'));?>">
			</span>
		</p>
		<em><&= nbr &></em>
	</li>
</script>
