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
	<small>
		<?php echo $l->t('Drag this to your browser bookmarks and click it, when you want to bookmark a webpage quickly:');?>
	</small><br />
	<a class="button bookmarklet"
		href="javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=document.title,d=a.open('<?php 
		echo OCP\Util::linkToAbsolute('bookmarks', 'addBm.php');
		?>'?output=popup&url=\'+c(b.location)+\'&title=\'+e,\'bkmk_popup\',\'left=\'+((a.screenX||a.screenLeft)+10)+\',top=\'+((a.screenY||a.screenTop)+10)+\',height=400px,width=550px,resizable=1,alwaysRaised=1\');a.setTimeout(function(){d.focus()},300);})();">
		<?php echo $l->t('Read later');?>
	</a>
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
			<input type="file" id="bm_import" name="bm_import">
			<button type="button" name="bm_import_btn" id="bm_import_submit"><?php echo $l->t('Import'); ?></button>
			<div id="upload"></div>


		
	</fieldset>
</form>
