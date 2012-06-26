<form id="addBm" method="post" action="<?php OCP\Util::linkToAbsolute('bookmarks', 'addBm.php');?>">
		<h1><?php echo $l->t('Add a bookmark');?></h1>
		<div id="close_btn"><a href="javascript:self.close()" class="ui-icon ui-icon-closethick"><?php echo $l->t('Close');?></a></div>
		<fieldset class="bm_desc">
		<ul>
			<li>
				<label for="title"><strong><?php echo $l->t('Title');?></strong></label>
				<input type="text" name="title" id="title" value="<?php echo $_['bookmark']['title']; ?>" placeholder="<?php echo $l->t('The title of the page');?>" />
			</li>

			<li>
				<label for="url"><strong><?php echo $l->t('Address');?></strong></label>
				<div id="url-ro">
					<code><?php echo $_['bookmark']['url']; ?></code>
					<img class="svg action"	src="<?php echo image_path('core','actions/rename.svg')?>"
          alt="<?php echo $l->t('Edit');?>" title="<?php echo $l->t('Edit');?>" />
				</div>
				<input type="text" name="url" id="url" value="<?php echo $_['bookmark']['url']; ?>" placeholder="<?php echo $l->t('The address of the page');?>" />
			</li>

			<li>
				<label for="tags"><strong><?php echo $l->t('Tags');?></strong></label>
					<ul id="tags" >
						<?php foreach($_['bookmark']['tags'] as $tag):?>
							<li><?php echo $tag;?></li>
						<?php endforeach;?>
					</ul>
			</li>

			<li>
				<label for="desc"><strong><?php echo $l->t('Description');?></strong></label>
				<textarea name="desc" id="desc" value="<?php echo $_['bookmark']['desc']; ?>" placeholder="<?php echo $l->t('Description of the page');?>"></textarea>
			</li>

			<li>
				<input type="submit" class="submit" value="<?php echo $l->t("Submit");?>" />
				<input type="checkbox" <?php if($_['bookmark']['is_public']){echo 'checked="checked"';} ?> id="is_public" name="is_public">
				<label for="is_public" id="is_public_label"><?php echo $l->t("Make this link public");?></label>
			</li>

			</ul>
			
		</fieldset>
		<script>
			var sampleTags = <?php echo $_['tags'];?>;
		</script>
</form>