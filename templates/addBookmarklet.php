<?php
OCP\Util::addscript('bookmarks', 'dist/bookmarklet.bundle');
OCP\Util::addStyle('bookmarks', 'bookmarklet');
style('bookmarks', 'select2');

$bookmarkExists = $_['bookmarkExists'];
?>
<div id="bookmarklet_form">
    <form class="addBm" action="">
		<div id="add_form_loading" style=""><span class="icon-loading"></span></div>
			<h1><?php p($l->t('Add a bookmark')); ?></h1>

			<div class="bookmark-exists" style="display: <?php
			if ($bookmarkExists === false) {
				print_unescaped('none');
			}
			?>">
					<?php p($l->t('This URL is already bookmarked! Overwrite?')); ?>
			</div>
			<ul class="addBm">
					<li>
						<?php if($bookmarkExists !== false) { ?>
						<input id="bookmarkID" type="hidden" class="hidden" value="<?php p($bookmarkExists); ?>" />
						<?php } ?>
						<input id="title" type="text" name="title" class="title" value="<?php p($_['title']); ?>"
										 placeholder="<?php p($l->t('The title of the page')); ?>" />
					</li>

					<li>
						<input id="url" type="text" name="url" class="url_input" value="<?php p($_['url']); ?>"
										 placeholder="<?php p($l->t('The address of the page')); ?>" />
					</li>

					<li>
						<ul id="tags" class="tags" >
							<?php foreach ($_['tags'] as $tag): ?>
									<li><?php p($tag); ?></li>
							<?php endforeach; ?>
						</ul>
					</li>

					<li>
						<textarea id="description" name="description" class="desc"
										placeholder="<?php p($l->t('Description of the page')); ?>"><?php p($_['description']); ?></textarea>
					</li>

					<li>
						<input type="submit" class="submit" value="<?php p($l->t("Save")); ?>" />
						<input type="hidden" class="record_id" value="" name="record_id" />
						<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
					</li>
			</ul>
    </form>
</div>
