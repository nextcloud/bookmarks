<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 * @author Marvin Thomas Rabe <mrabe@marvinrabe.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright (c) 2011, Marvin Thomas Rabe
 * @copyright (c) 2011, Arthur Schiwon
 * @copyright (c) 2014, Stefan Klemm
 */

namespace OCA\Bookmarks\AppInfo;

if ((@include_once __DIR__ . '/../vendor/autoload.php')===false) {
  throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

$navigationEntry = function () {
	return [
		'id' => 'bookmarks',
		'order' => 10,
		'name' => \OC::$server->getL10N('bookmarks')->t('Bookmarks'),
		'href' => \OC::$server->getURLGenerator()->linkToRoute('bookmarks.web_view.index'),
		'icon' => \OC::$server->getURLGenerator()->imagePath('bookmarks', 'bookmarks.svg'),
	];
};
\OC::$server->getNavigationManager()->add($navigationEntry);

\OC::$server->getSearch()->registerProvider('OCA\Bookmarks\Controller\Lib\Search', array('apps' => array('bookmarks')));
