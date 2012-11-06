<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
?>

<fieldset class="personalblock">
	<legend><strong><?php echo $l->t('Bookmarklet');?></strong></legend>
	<?php echo bookmarklet();?><br />
</fieldset>

<form id="import_bookmark" action="<?php echo OCP\Util::linkTo( "bookmarks", "ajax/import.php" );?>"
 method="post" enctype="multipart/form-data">
	<fieldset class="personalblock">
		<?php if(isset($_['error'])): ?>
			<h3><?php echo $_['error']['error']; ?></h3>
			<p><?php echo $_['error']['hint']; ?></p>
		<?php endif; ?>

			<legend><strong><?php echo $l->t('Export & Import');?></strong></legend>
			<input type="button" id="bm_export" href="<?php echo OCP\Util::linkTo('bookmarks', 'export.php') ;?>" value="<?php echo $l->t('Export'); ?>" />
			<input type="file" id="bm_import" name="bm_import" size="5">
			<button type="button" name="bm_import_btn" id="bm_import_submit"><?php echo $l->t('Import'); ?></button>
			<div id="upload"></div>


		
	</fieldset>
</form>
