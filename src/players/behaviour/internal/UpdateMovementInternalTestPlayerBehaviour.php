<?php

declare(strict_types=1);

namespace ShockedPlot7560\UnitTest\players\behaviour\internal;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use ReflectionMethod;
use ReflectionProperty;
use ShockedPlot7560\UnitTest\players\behaviour\TestPlayerBehaviour;
use ShockedPlot7560\UnitTest\players\TestPlayer;
use ShockedPlot7560\UnitTest\players\TestPlayerManager;

final class UpdateMovementInternalTestPlayerBehaviour implements TestPlayerBehaviour {
	use InternalTestPlayerBehaviourTrait;

	public static function init(TestPlayerManager $plugin) : void {
	}

	private static function readMovementFromPlayer(Player $player) : Vector3 {
		static $_motion = null;
		if ($_motion === null) {
			/** @see Human::$motion */
			$_motion = new ReflectionProperty(Human::class, "motion");
			$_motion->setAccessible(true);
		}

		return $_motion->getValue($player)->asVector3();
	}

	private static function movePlayer(Player $player, Vector3 $dv) : void {
		static $reflection_method = null;
		if ($reflection_method === null) {
			/** @see Human::move() */
			$reflection_method = new ReflectionMethod(Human::class, "move");
			$reflection_method->setAccessible(true);
		}
		$reflection_method->getClosure($player)($dv->x, $dv->y, $dv->z);
	}

	private static function setPlayerLocation(Player $player, Location $location) : void {
		static $reflection_property = null;
		if ($reflection_property === null) {
			/** @see Human::$location */
			$reflection_property = new ReflectionProperty(Human::class, "location");
			$reflection_property->setAccessible(true);
		}
		$reflection_property->setValue($player, $location);
	}

	public function __construct(
		private TestPlayerMovementData $data
	) {
	}

	public function onAddToPlayer(TestPlayer $player) : void {
	}

	public function onRemoveFromPlayer(TestPlayer $player) : void {
	}

	public function tick(TestPlayer $player) : void {
		$player_instance = $player->getPlayer();
		$this->data->motion = self::readMovementFromPlayer($player_instance);
		if ($player_instance->hasMovementUpdate()) {
			$this->data->motion = $this->data->motion->withComponents(
				abs($this->data->motion->x) <= Entity::MOTION_THRESHOLD ? 0 : null,
				abs($this->data->motion->y) <= Entity::MOTION_THRESHOLD ? 0 : null,
				abs($this->data->motion->z) <= Entity::MOTION_THRESHOLD ? 0 : null
			);

			if ($this->data->motion->x != 0 || $this->data->motion->y != 0 || $this->data->motion->z != 0) {
				$old_location = $player_instance->getLocation();
				self::movePlayer($player_instance, $this->data->motion);
				$new_location = $player_instance->getLocation();

				self::setPlayerLocation($player_instance, $old_location);
				$player_instance->handleMovement($new_location);
			}

			$this->data->motion = self::readMovementFromPlayer($player_instance);
		}
	}

	public function onRespawn(TestPlayer $player) : void {
	}
}
