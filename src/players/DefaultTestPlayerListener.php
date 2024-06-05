<?php

declare(strict_types=1);

namespace ShockedPlot7560\PmmpUnit\players;

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ChangeDimensionPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use ShockedPlot7560\PmmpUnit\players\listener\TestPlayerListener;
use ShockedPlot7560\PmmpUnit\players\network\listener\ClosureTestPlayerPacketListener;
use ShockedPlot7560\PmmpUnit\players\network\TestPlayerNetworkSession;

final class DefaultTestPlayerListener implements TestPlayerListener {
	public function __construct(
		private TestPlayerManager $plugin
	) {
	}

	public function onPlayerAdd(Player $player) : void {
		$session = $player->getNetworkSession();
		assert($session instanceof TestPlayerNetworkSession);

		$entity_runtime_id = $player->getId();
		$session->registerSpecificPacketListener(PlayStatusPacket::class, new ClosureTestPlayerPacketListener(function (ClientboundPacket $packet, NetworkSession $session) use ($entity_runtime_id) : void {
			assert($packet instanceof PlayStatusPacket);
			if ($packet->status === PlayStatusPacket::PLAYER_SPAWN) {
				$this->plugin->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($session, $entity_runtime_id) : void {
					if ($session->isConnected()) {
						$packet = SetLocalPlayerAsInitializedPacket::create($entity_runtime_id);
						$serializer = PacketSerializer::encoder();
						$packet->encode($serializer);
						$session->handleDataPacket($packet, $serializer->getBuffer());
					}
				}), 40);
			}
		}));

		$session->registerSpecificPacketListener(RespawnPacket::class, new ClosureTestPlayerPacketListener(function (ClientboundPacket $packet, NetworkSession $session) : void {
			$this->plugin->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($session) : void {
				if ($session->isConnected()) {
					/** @var Player $player */
					$player = $session->getPlayer();
					$player->respawn();
					$testPlayer = $this->plugin->getTestPlayer($player);
					assert($testPlayer !== null);
					foreach ($testPlayer->getBehaviours() as $behaviour) {
						$behaviour->onRespawn($testPlayer);
					}
				}
			}), 40);
		}));

		$session->registerSpecificPacketListener(ChangeDimensionPacket::class, new ClosureTestPlayerPacketListener(function (ClientboundPacket $packet, NetworkSession $session) : void {
			$this->plugin->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($session) : void {
				if ($session->isConnected()) {
					$player = $session->getPlayer();
					if ($player !== null) {
						$packet = PlayerActionPacket::create(
							$player->getId(),
							PlayerAction::DIMENSION_CHANGE_ACK,
							BlockPosition::fromVector3($player->getPosition()->floor()),
							BlockPosition::fromVector3($player->getPosition()->floor()),
							0
						);

						$serializer = PacketSerializer::encoder();
						$packet->encode($serializer);
						$session->handleDataPacket($packet, $serializer->getBuffer());
					}
				}
			}), 40);
		}));
	}

	public function onPlayerRemove(Player $player) : void {
		// not necessary to unregister listeners because they'll automatically
		// be gc-d as nothing holds ref to player object?
	}
}
