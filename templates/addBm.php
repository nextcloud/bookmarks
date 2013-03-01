<form class="addBm" method="post" action="<?php print_unescaped(OCP\Util::linkTo('bookmarks', 'ajax/editBookmark.php'));?>">
		<?php if(!isset($embedded) || !$embedded):?>
			<script type="text/javascript" src="<?php print_unescaped(OC_Helper::linkTo('bookmarks/js', 'full_tags.php'));?>"></script>

			<h1><?php p($l->t('Add a bookmark'));?></h1>
			<div class="close_btn">
				<a href="javascript:self.close()" class="ui-icon ui-icon-closethick">
					<?php p($l->t('Close'));?>
				</a>
			</div>
		<?php endif;?>
		<fieldset class="bm_desc">
		<ul>
			<li>
				<input type="text" name="title" class="title" value="<?php p($_['bookmark']['title']); ?>"
					placeholder="<?php p($l->t('The title of the page'));?>" />
			</li>

			<li>
				<input type="text" name="url" class="url_input" value="<?php p($_['bookmark']['url']); ?>"
					placeholder="<?php p($l->t('The address of the page'));?>" />
			</li>

			<li>
					<ul class="tags" >
						<?php foreach($_['bookmark']['tags'] as $tag):?>
							<li><?php p($tag);?></li>
						<?php endforeach;?>
					</ul>
			</li>

			<li>
				<textarea name="description" class="desc" value="<?php p($_['bookmark']['desc']); ?>"
					placeholder="<?php p($l->t('Description of the page'));?>"></textarea>
			</li>

			<li>
				<input type="submit" class="submit" value="<?php p($l->t("Save"));?>" />
				<input type="hidden" class="record_id" value="" name="record_id" />
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
			</li>

			</ul>
			
		</fieldset>
</form>