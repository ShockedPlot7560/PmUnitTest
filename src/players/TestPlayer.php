<?php

declare(strict_types=1);

namespace ShockedPlot7560\PmmpUnit\players;

use AssertionError;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ShockedPlot7560\PmmpUnit\players\behaviour\TestPlayerBehaviour;
use ShockedPlot7560\PmmpUnit\players\network\listener\ClosureTestPlayerPacketListener;
use ShockedPlot7560\PmmpUnit\players\network\TestPlayerNetworkSession;
use ShockedPlot7560\PmmpUnit\players\util\SortedMap;
use ShockedPlot7560\PmmpUnit\PmmpUnit;
use ShockedPlot7560\PmmpUnit\utils\TimeoutException;

final class TestPlayer {
	private TestPlayerNetworkSession $session;
	private Player $player;

	/** @phpstan-var SortedMap<int, TestPlayerBehaviour> */
	private SortedMap $behaviours;

	/** @var array<string, mixed> */
	private array $metadata = [];

	public function __construct(TestPlayerNetworkSession $session) {
		$this->session = $session;
		$this->player = $session->getPlayer() ?? throw new AssertionError("Player is null");
		$this->behaviours = new SortedMap();
	}

	public function getPlayer() : Player {
		return $this->player;
	}

	public function getPlayerNullable() : ?Player {
		return $this->player;
	}

	public function destroy() : void {
		foreach ($this->getBehaviours() as $behaviour) {
			$this->removeBehaviour($behaviour);
		}

		$this->metadata = [];
	}

	public function getNetworkSession() : TestPlayerNetworkSession {
		return $this->session;
	}

	public function addBehaviour(TestPlayerBehaviour $behaviour, int $score = 0) : void {
		if (!$this->behaviours->contains($id = spl_object_id($behaviour))) {
			$this->behaviours->set($id, $behaviour, $score);
			$behaviour->onAddToPlayer($this);
		}
	}

	/**
	 * @return array<int, TestPlayerBehaviour>
	 */
	public function getBehaviours() : array {
		return $this->behaviours->getAll();
	}

	public function removeBehaviour(TestPlayerBehaviour $behaviour) : void {
		if ($this->behaviours->contains($id = spl_object_id($behaviour))) {
			$this->behaviours->remove($id);
			$behaviour->onRemoveFromPlayer($this);
		}
	}

	public function tick() : void {
		foreach ($this->getBehaviours() as $behaviour) {
			$behaviour->tick($this);
		}
	}

	public function getMetadata(string $key, mixed $default = null) : mixed {
		return $this->metadata[$key] ?? $default;
	}

	public function setMetadata(string $key, mixed $value) : void {
		$this->metadata[$key] = $value;
	}

	public function deleteMetadata(string $key) : void {
		unset($this->metadata[$key]);
	}

	/**
	 * @return PromiseInterface<ClientboundPacket>
	 */
	public function registerSpecificSendPacketListener(string $packetName, int $timeout = 60) : PromiseInterface {
		$promise = new Deferred();
		$handler = PmmpUnit::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($promise) {
			$promise->reject(new TimeoutException('Timeout for packet expired'));
		}), $timeout * 20);

		$this->getNetworkSession()->registerSpecificPacketListener($packetName, new ClosureTestPlayerPacketListener(
			function (ClientboundPacket $packet, NetworkSession $session) use ($promise, $handler) : void {
				$promise->resolve($packet);
				$handler->cancel();
			}
		));

		return $promise->promise();
	}

	/**
	 * @return PromiseInterface<ClientboundPacket>
	 */
	public function registerSendPacketListener() : PromiseInterface {
		$promise = new Deferred();
		$this->getNetworkSession()->registerPacketListener(new ClosureTestPlayerPacketListener(
			function (ClientboundPacket $packet, NetworkSession $session) use ($promise) : void {
				$promise->resolve($packet);
			}
		));

		return $promise->promise();
	}

	/**
	 * @return PromiseInterface<ServerboundPacket>
	 */
	public function registerSpecificReceivePacketListener(string $packetName) : PromiseInterface {
		$promise = TestPlayerManager::getInstance()->getPacketReceiveListener()->addListener(
			$this->getPlayer()->getName(),
			$packetName
		);

		return $promise->promise();
	}
}
