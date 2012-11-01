<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
?>

<fieldset class="personalblock">
	<legend><strong><?php echo $l->t('Bookmarklet <br />');?></strong></legend>
	<?php
			require_once 'bookmarklet.php';
			createBookmarklet();
	?>
</fieldset>

<form id="import_bookmark" action="<?php echo OCP\Util::linkTo( "bookmarks", "ajax/import.php" );?>"
 method="post" enctype="multipart/form-data">
	<fieldset class="personalblock">
		<?php if(isset($_['error'])): ?>
			<h3><?php echo $_['error']['error']; ?></h3>
			<p><?php echo $_['error']['hint']; ?></p>
		<?php endif; ?>

			<legend><strong><?php echo $l->t('Import bookmarks');?></strong></legend>
			<p><input type="file" id="bm_import" name="bm_import" style="width:280px;">
				<label for="bm_import"> <?php echo $l->t('html bookmarks file');?></label>
			</p>
			<input type="button" name="bm_import_btn" id="bm_import_submit" value="<?php echo $l->t('Import'); ?>" />
		<div id="upload"></div>

		<legend><strong><?php echo $l->t('Export bookmarks');?></strong></legend>
		<p><a href="<?php echo OCP\Util::linkTo('bookmarks', 'export.php') ;?>"
			class="button"><?php echo $l->t('Export'); ?></a></p>
		
	</fieldset>
</form>
