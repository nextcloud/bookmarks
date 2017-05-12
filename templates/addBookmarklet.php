<?php
OCP\Util::addscript('bookmarks', '3rdparty/tag-it');
OCP\Util::addscript('bookmarks', 'bookmarklet');
OCP\Util::addStyle('bookmarks', 'bookmarks');
OCP\Util::addStyle('bookmarks', '3rdparty/jquery.tagit');

$bookmarkExists = $_['bookmarkExists'];
$bookmarkTitleTooLong = $_['bookmarkExists'];
?>
<div id="bookmarklet_form">
    <form class="addBm" action="">
		<script type="text/javascript" src="tag"></script>

		<h1 style="display: block; float: left"><?php p($l->t('Add a bookmark')); ?></h1>
		<span style="display: inline; float: right"><div id="add_form_loading" style="margin: 3px;"><img src="<?php print_unescaped(OCP\image_path("bookmarks", "loading.gif")); ?>"> </div></span>

		<div style="color: red; clear: both; visibility: <?php
		if ($bookmarkExists === false) {
			print_unescaped('hidden');
		}
		?>">
			<strong>
				<?php p($l->t('This URL is already bookmarked! Overwrite?')); ?>
			</strong>
		</div>
                <div style="color: red; clear: both; visibility: <?php
                if ($bookmarkTitleTooLong == false) {
                        print_unescaped('hidden');
                }
                ?>">   
                        <strong>
                                <?php p($l->t('Title must not exeed 140 characters!?')); ?>
                        </strong>
                </div>

        <fieldset class="bm_desc">
            <ul>
                <li>
					<?php if($bookmarkExists !== false) { ?>
						<input id="bookmarkID" type="hidden" class="hidden" value="<?php p($bookmarkExists); ?>" />
					<?php } ?>
                    <input id="title" type="text" name="title" class="title" value="<?php p($_['title']); ?>"
                           placeholder="<?php p($l->t('The title of the page')); ?>" />
                </li>

                <li>
                    <input id="url" type="text" name="url" class="url_input" value="<?php p($_['url']); ?>"
                           placeholder="<?php p($l->t('The address of the page')); ?>" />
                </li>

                <li>
                    <ul id="tags" class="tags" >
                        <?php foreach ($_['tags'] as $tag): ?>
                            <li><?php p($tag); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <li>
                    <textarea id="description" name="description" class="desc"
                              placeholder="<?php p($l->t('Description of the page')); ?>"><?php p($_['description']); ?></textarea>
                </li>

                <li>
                    <input type="submit" class="submit" value="<?php p($l->t("Save")); ?>" />
                    <input type="hidden" class="record_id" value="" name="record_id" />
                    <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
                </li>

            </ul>

        </fieldset>
    </form>
</div>
