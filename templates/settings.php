<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
?>
<form id="bookmarks">
		<fieldset class="personalblock">
			<span class="bold"><?php echo $l->t('Bookmarklet <br />');?></span>
			<?php
			    require_once('bookmarklet.php');
			    createBookmarklet(); 
			?>
		</fieldset>
</form>

<form id="import_bookmark" action="#" method="post" enctype="multipart/form-data">
	<fieldset class="personalblock">
		<?php if(isset($_['error'])): ?>
			<h3><?php echo $_['error']['error']; ?></h3>
			<p><?php echo $_['error']['hint']; ?></p>
		<?php endif; ?>

		<legend><strong><?php echo $l->t('Import bookmarks');?></strong></legend>
		</p>
		<p><input type="file" id="bm_import" name="bm_import" style="width:280px;"><label for="bm_import"> <?php echo $l->t('Bookmark html file');?></label>
		</p>
		<input type="submit" name="bm_import" value="<?php echo $l->t('Import'); ?>" />
	</fieldset>
</form>