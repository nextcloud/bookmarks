<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
/** @var array $_ */
?>

<ul>
	<li><strong><?php p($l->t('Bookmarklet')); ?></strong></li>
	<?php print_unescaped($bookmarkletscript); ?><br />
</ul>
<form id="import_bookmark" action="bookmark/import" method="post" enctype="multipart/form-data">
	<ul>
		<li>
		</li>
		<li>
			<?php if (isset($_['error'])): ?>
				<h3><?php p($_['error']['error']); ?></h3>
				<p><?php p($_['error']['hint']); ?></p>
			<?php endif; ?>

		<legend><strong><?php p($l->t('Export & Import')); ?></strong></legend>
		<input type="button" id="bm_export" href="bookmark/export?requesttoken=<?php p($_['requesttoken']) ?>" value="<?php p($l->t('Export')); ?>" />
		<input type="file" id="bm_import" name="bm_import" size="5">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" id="requesttoken">
		<button type="button" name="bm_import_btn" id="bm_import_submit"><?php p($l->t('Import')); ?></button>
		<div id="upload"></div>



		</li>
	</ul>
</form>
