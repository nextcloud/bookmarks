<?php
/*
 * Copyright (c) 2024. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

declare(strict_types=1);


namespace OCA\Bookmarks\Service;

use OCP\App\IAppManager;
use OCP\Server;
use Throwable;

/**
 * Wrapper around circles app API since it is not in a public namespace so we need to make sure that
 * having the app disabled is properly handled
 */
class CirclesService {
	public const TYPE = 1;
	public const LEVEL_MEMBER = 1;
	private bool $circlesEnabled;

	private $userCircleCache = [];

	public function __construct(IAppManager $appManager) {
		$this->circlesEnabled = $appManager->isEnabledForUser('circles');
	}

	public function isCirclesEnabled(): bool {
		return $this->circlesEnabled;
	}

	public function getCircle(string $circleId) {
		if (!$this->circlesEnabled) {
			return null;
		}

		try {

			// Enforce current user condition since we always want the full list of members
			$circlesManager = Server::get('OCA\Circles\CirclesManager');
			$circlesManager->startSuperSession();
			return $circlesManager->getCircle($circleId);
		} catch (Throwable $e) {
		}
		return null;
	}

	public function isUserInCircle(string $circleId, string $userId): bool {
		if (!$this->circlesEnabled) {
			return false;
		}

		if (isset($this->userCircleCache[$circleId][$userId])) {
			return $this->userCircleCache[$circleId][$userId];
		}

		try {
			$circlesManager = Server::get('OCA\Circles\CirclesManager');
			$federatedUser = $circlesManager->getFederatedUser($userId, self::TYPE);
			$circlesManager->startSession($federatedUser);
			$circle = $circlesManager->getCircle($circleId);
			$member = $circle->getInitiator();
			$isUserInCircle = $member !== null && $member->getLevel() >= self::LEVEL_MEMBER;

			if (!isset($this->userCircleCache[$circleId])) {
				$this->userCircleCache[$circleId] = [];
			}
			$this->userCircleCache[$circleId][$userId] = $isUserInCircle;

			return $isUserInCircle;
		} catch (Throwable $e) {
		}
		return false;
	}

	/**
	 * @param string $userId
	 * @return string[] circle single ids
	 */
	public function getUserCircles(string $userId): array {
		if (!$this->circlesEnabled) {
			return [];
		}

		try {
			$circlesManager = Server::get('OCA\Circles\CirclesManager');
			$federatedUser = $circlesManager->getFederatedUser($userId, self::TYPE);
			$circlesManager->startSession($federatedUser);
			$circleProbe = 'OCA\Circles\Model\Probes\CircleProbe';
			$probe = new $circleProbe();
			$probe->mustBeMember();
			return array_map(function ($circle) {
				return $circle->getSingleId();
			}, $circlesManager->getCircles($probe));
		} catch (Throwable $e) {
		}
		return [];
	}
}
