<?php
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

<div id="app-navigation">
    <ul id="navigation-list">
        <li>
            <form id="add_form">
                <input type="text" id="add_url" value="" placeholder="<?php p($l->t('Address')); ?>"/>
                <button id="bookmark_add_submit" title="Add" class="icon-add"></button>
            </form>
            <p id="tag_filter" class="open">
                <input type="text" value="<?php if(isset($_['req_tag'])) p($_['req_tag']); else ""; ?>"/>


            </p>
            <input type="hidden" id="bookmarkFilterTag" value="<?php if(isset($_['req_tag'])) p($_['req_tag']); else ""; ?>" />
            <label id="tag_select_label"><?php p($l->t('Filterable Tags')); ?></label>
        </li>
        <li class="tag_list">
        </li>
    </ul>

    <div id="app-settings">
        <div id="app-settings-header">
            <button class="settings-button generalsettings" data-apps-slide-toggle="#app-settings-content" tabindex="0"></button>
        </div>
        <div id="app-settings-content">


			<?php require 'settings.php'; ?>
        </div>
    </div>

</div>
<div id="app-content">
    <div id="emptycontent" style="display: none;">
        <p class="title"><?php
			p($l->t('You have no bookmarks'));
			$embedded = true;
			print_unescaped($bookmarkletscript);
			?></p>
        <br/><br/>


        <div class="bkm_hint">
            <a href="#" id="firstrun_setting">
				<?php p($l->t('You can also import a bookmark file')); ?>
            </a></div>
    </div>
    <div class="bookmarks_list"></div>
</div>

<?php
require 'js_tpl.php';
