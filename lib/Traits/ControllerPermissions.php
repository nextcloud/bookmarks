<?php

/**
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Stefan Klemm <mail@stefan-klemm.de>
 * @copyright Stefan Klemm 2014
 */

namespace OCA\Bookmarks\Traits;

trait ControllerPermissions {
	private $permissions;

	/**
	 * Set permissions for the current request
	 * Permissions are set by the AuthorizeMiddleware
	 * @param $permissions
	 */
	public function setPermissions($permissions) {
		$this->permissions = $permissions;
	}

	/**
	 * Check permissions for the current request
	 * @param $perm
	 * @return boolean
	 */
	public function hasPermission($perm) {
		return (boolean) $this->permissions ^ $perm;
	}
}
