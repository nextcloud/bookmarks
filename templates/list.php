<?php 
/**
 * Copyright (c) 2011 Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * Copyright (c) 2011 Arthur Schiwon <blizzz@arthur-schiwon.de>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
function bookmarklet(){
	$l = new OC_l10n('bookmarks');
	$blet = "javascript:(function(){var a=window,b=document,c=encodeURIComponent,e=c(document.title),d=a.open('";
	$blet .= OCP\Util::linkToAbsolute('bookmarks', 'addBm.php');
	$blet .= "?output=popup&url='+c(b.location)+'&title='+e,'bkmk_popup','left='+((a.screenX||a.screenLeft)+10)+',top='+((a.screenY||a.screenTop)+10)+',height=400px,width=550px,resizable=1,alwaysRaised=1');a.setTimeout(function(){d.focus()},300);})();";
	$help_msg  = $l->t('Drag this to your browser bookmarks and click it, when you want to bookmark a webpage quickly:');
	return '<div class="bkm_hint">'.$help_msg.'</div><br /><a class="button bookmarklet" href="' . $blet . '">' . $l->t('Read later') . '</a>';
}
?>

<div id="leftcontent">

	<form id="add_form">
		<input type="text" id="add_url" value="" placeholder="<?php p($l->t('Address')); ?>"/>
		<input type="submit" value="<?php p($l->t('Add')); ?>" id="bookmark_add_submit" />
	</form>

	<p id="tag_filter">
		<input type="text" value="<?php p($_['req_tag']); ?>"/>
	</p>
	<input type="hidden" id="bookmarkFilterTag" value="<?php p($_['req_tag']); ?>" />

	<label><?php p($l->t('Related Tags')); ?></label>
	<ul class="tag_list">
	</ul>


<div id="bookmark_settings" class="">
	<ul class="controls">
			<li id="settingsbtn" title="<?php p($l->t('Settings')); ?>">
				<img class="svg" src="<?php print_unescaped(OCP\Util::imagePath('core', 'actions/settings.svg')); ?>"
				alt="<?php p($l->t('Settings')); ?>" />
			</li>
	</ul>
	<div id="bm_setting_panel">
		<?php require 'settings.php';?>
	</div>
</div>

</div>
<div id="rightcontent" class="rightcontent">
	<div id="firstrun" style="display: none;">
		<div id="distance"></div>
		<div id="firstrun_message">
			<p class="title"><?php
			p($l->t('You have no bookmarks'));
			$embedded = true;
			
			print_unescaped(bookmarklet());?></p><br/><br />

			<div class="bkm_hint"><a href="#" id="firstrun_setting"><?php p($l->t('You can also try to import a bookmark file'));?></a></div>
		</div>
	</div>
	<div class="bookmarks_list"></div>
</div>
<script type="text/javascript" src="<?php print_unescaped(OC_Helper::linkTo('bookmarks/js', 'full_tags.php'));?>"></script>

<?php require 'js_tpl.php';?>
