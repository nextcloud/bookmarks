<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title><?php p($l->t('Bookmarks feed')); ?></title>uuu
		<language><?php p($_['rssLang']); ?></language>
		<description><?php p($_['description']); ?></description>
		<pubDate><?php p($_['rssPubDate']); ?></pubDate>
<?php foreach ($_['bookmarks'] as $bookmark) {
	?>
		<item>
			<guid isPermaLink="false"><?php p($bookmark['id']); ?></guid>
<?php if (!empty($bookmark['title'])): ?>
			<title><?php p(str_replace("\n", ' ', $bookmark['title'])); ?></title>
<?php endif; ?>
<?php if (!empty($bookmark['url'])): ?>
			<link><?php p($bookmark['url']); ?></link>
<?php endif; ?>
<?php if (!empty($bookmark['added'])): ?>
			<pubDate><?php p(date('r', $bookmark['added'])); ?></pubDate>
<?php endif; ?>
<?php if (!empty($bookmark['description'])): ?>
			<description><![CDATA[<?php print_unescaped(str_replace("\n", '<br />', \OCP\Util::sanitizeHTML($bookmark['description']))); ?>]]></description>
<?php endif; ?>
		</item>
<?php
} ?>
	</channel>
</rss>
