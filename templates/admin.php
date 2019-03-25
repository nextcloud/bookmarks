<?php
/** @var $l \OCP\IL10N */
/** @var $_ array */
script('bookmarks', 'dist/admin.bundle');
?>

<div id="bookmarks" class="section">
        <h2><?php p($l->t('Previews')); ?></h2>
        <p>
            <?php p($l->t('In order to display real screenshots of your bookmarked websites, Bookmarks can use a third-party service to generate those.')); ?>
        </p>
        <p>
            <?php print($l->t('You can either sign up for free at <a href="http://screeenly.com">screeenly.com</a> or <a href="https://github.com/stefanzweifel/screeenly">setup your own server</a>.')); ?>
        </p>
        <p>
                <label for="bookmarks_previews_screenly_url"><?php p($l->t('Screeenly API URL')); ?></label>
                <input id="bookmarks_previews_screenly_url" name="bookmarks_previews_screenly_url"
                           type="text" style="width: 250px;" value="<?php p($_['previews.screenly.url']); ?>" />
                <span class="error-status icon-error-color" style="display: inline-block"></span>
                <span class="success-status icon-checkmark-color" style="display: inline-block"></span>

        </p>
        <p>
                <label for="bookmarks_previews_screenly_token"><?php p($l->t('Screeenly API key')); ?></label>
                <input id="bookmarks_previews_screenly_token" name="bookmarks_previews_screenly_token"
                           type="text" style="width: 250px;" value="<?php p($_['previews.screenly.token']); ?>" />
                <span class="error-status icon-error-color" style="display: inline-block"></span>
                <span class="success-status icon-checkmark-color" style="display: inline-block"></span>
        </p>
</div>
