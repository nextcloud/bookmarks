<?php

/**
 * ownCloud - bookmarks
 *
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

\OCP\App::addNavigationEntry(array(
	// the string under which your app will be referenced in owncloud
	'id' => 'bookmarks',
	// sorting weight for the navigation. The higher the number, the higher
	// will it be listed in the navigation
	'order' => 10,
	// the route that will be shown on startup
	'href' => \OCP\Util::linkToRoute('bookmarks.web_view.index'),
	// the icon that will be shown in the navigation
	// this file needs to exist in img/
	'icon' => \OCP\Util::imagePath('bookmarks', 'bookmarks.svg'),
	// the title of your application. This will be used in the
	// navigation or on the settings page of your app
	'name' => \OC_L10N::get('bookmarks')->t('Bookmarks')
));

\OC::$server->getSearch()->registerProvider('OCA\Bookmarks\Controller\Lib\Search', array('apps' => array('bookmarks')));
