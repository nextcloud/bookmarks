<?php
script('bookmarks', '3rdparty/backbone');
script('bookmarks', '3rdparty/backbone.radio');
script('bookmarks', '3rdparty/backbone.marionette.min');
script('bookmarks', 'settings');
script('bookmarks', 'bookmarks');
style('bookmarks', 'bookmarks');


script('bookmarks', '3rdparty/tag-it');
script('bookmarks', '3rdparty/js_tpl');
style('bookmarks', '3rdparty/jquery.tagit');

/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * Copyright (c) 2011 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
$bookmarkleturl = $_['bookmarkleturl'];
$bookmarkletscript = bookmarklet($bookmarkleturl);

function bookmarklet($bookmarkleturl) {
	$l = \OC::$server->getL10N('bookmarks');
	$defaults = \OC::$server->getThemingDefaults();
	$blet = "javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('";
	$blet .= $bookmarkleturl;
	$blet .= "?output=popup&url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=400px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();";
	$help_msg = $l->t('Drag this to your browser bookmarks and click it, when you want to bookmark a webpage quickly:');
	$output = '<div id="bookmarklet_hint" class="bkm_hint">' . $help_msg . '</div><a class="button bookmarklet" href="' . $blet . '">' . $l->t('Add to ' . \OCP\Util::sanitizeHTML($defaults->getName())) . '</a>';
	return $output;
}
?>

<?php
require 'js_tpl.php';
