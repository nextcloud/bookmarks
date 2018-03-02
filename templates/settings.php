<?php
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
/** @var array $_ */
?>
<script>
var loc = location.href;
var baseurl = loc.substring(0,loc.lastIndexOf('/'));

var data = {
  // currently required
  "name": "<?php p($l->t('Bookmarks')); ?>",
  "iconURL":   baseurl+"<?php print_unescaped(OCP\image_path("bookmarks", "bookmarks.png")); ?>",
  "icon32URL": baseurl+"<?php print_unescaped(OCP\image_path("bookmarks", "bookmarks.svg")); ?>",
  "icon64URL": baseurl+"<?php print_unescaped(OCP\image_path("bookmarks", "bookmarks.svg")); ?>",

  "shareURL": baseurl+"/bookmarklet?output=popup&url=%{url}&title=%{title}",
  "sidebarURL": baseurl,

  // should be available for display purposes
  "description": "<?php p($l->t('Add to ownCloud')); ?>",
  "author": "Marvin Thomas Rabe, Olivier Mehani",
  "homepageURL": "https://github.com/owncloud/bookmarks/",

  // optional
  "version": "1.0"
}

function activate(node) {
  var event = new CustomEvent("ActivateSocialFeature");
  node.setAttribute("data-service", JSON.stringify(data));
  node.dispatchEvent(event);
}
</script>

<ul>
	<li><strong><?php p($l->t('Bookmarklet')); ?></strong></li>
	<?php print_unescaped($bookmarkletscript); ?><br />
	<li><strong><?php p($l->t('Social API')); ?></strong></li>
	<button type="button" onclick="activate(this)"><?php p($l->t('Activate provider')); ?></button>
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
		<input type="button" id="bm_export" href="bookmark/export?requesttoken=<?php p(urlencode($_['requesttoken'])) ?>" value="<?php p($l->t('Export')); ?>" />
		<input type="file" id="bm_import" name="bm_import" size="5">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" id="requesttoken">
		<button type="button" name="bm_import_btn" id="bm_import_submit"><?php p($l->t('Import')); ?></button>
		<div id="upload"></div>



		</li>
	</ul>
</form>
