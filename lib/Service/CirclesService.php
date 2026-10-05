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
	public const TYPE_GROUP = 2;
	public const TYPE_CIRCLE = 16;
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

	/**
	 * Resolves a circle to the local users it contains, including users that are
	 * members through groups or nested circles
	 *
	 * @param string $circleId circle single id
	 * @return string[] user ids
	 */
	public function getUserIdsOfCircle(string $circleId): array {
		$circle = $this->getCircle($circleId);
		if ($circle === null) {
			return [];
		}

		try {
			$userIds = [];
			foreach ($circle->getInheritedMembers() as $member) {
				if ($member->getUserType() === self::TYPE) {
					$userIds[] = $member->getUserId();
				}
			}
			return array_values(array_unique($userIds));
		} catch (Throwable $e) {
		}
		return [];
	}

	/**
	 * @param string $circleId circle single id
	 * @return string[] single ids of the circles that this circle is a member of, directly or indirectly
	 */
	public function getParentCircleIds(string $circleId): array {
		$circle = $this->getCircle($circleId);
		if ($circle === null) {
			return [];
		}

		try {
			$circleIds = [];
			foreach ($circle->getMemberships() as $membership) {
				if ($membership->getCircleId() !== $circleId) {
					$circleIds[] = $membership->getCircleId();
				}
			}
			return array_values(array_unique($circleIds));
		} catch (Throwable $e) {
		}
		return [];
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
