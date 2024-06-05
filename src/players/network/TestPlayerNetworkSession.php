<?php

declare(strict_types=1);

namespace ShockedPlot7560\PmmpUnit\players\network;

use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\StandardEntityEventBroadcaster;
use pocketmine\network\NetworkSessionManager;
use pocketmine\player\Player;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use ReflectionMethod;
use ReflectionProperty;
use ShockedPlot7560\PmmpUnit\players\network\listener\TestPlayerPacketListener;
use ShockedPlot7560\PmmpUnit\players\network\listener\TestPlayerSpecificPacketListener;

class TestPlayerNetworkSession extends NetworkSession {
	/** @var TestPlayerPacketListener[] */
	private array $packetListeners = [];

	/** @var PromiseResolver<Player>|null */
	private ?PromiseResolver $playerAddResolver;
	private ?TestPlayerSpecificPacketListener $specificPacketListener = null;

	/**
	 * @param PromiseResolver<Player> $playerAddResolver
	 */
	public function __construct(
		Server $server,
		NetworkSessionManager $manager,
		PacketPool $packetPool,
		PacketSender $sender,
		PacketBroadcaster $broadcaster,
		Compressor $compressor,
		string $ip,
		int $port,
		PromiseResolver $playerAddResolver
	) {
		$typeConverter = TypeConverter::getInstance();
		$rakLibInterface = null;
		foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
			if ($interface instanceof RakLibInterface) {
				$rakLibInterface = $interface;
				break;
			}
		}
		parent::__construct($server, $manager, $packetPool, $sender, $broadcaster, new StandardEntityEventBroadcaster($broadcaster, $typeConverter), $compressor, $typeConverter, $ip, $port);
		$this->playerAddResolver = $playerAddResolver;

		// do not store the resolver eternally
		$this->playerAddResolver->getPromise()->onCompletion(function (Player $_) : void {
			$this->playerAddResolver = null;
		}, function () : void { $this->playerAddResolver = null; });
	}

	public function registerPacketListener(TestPlayerPacketListener $listener) : void {
		$this->packetListeners[spl_object_id($listener)] = $listener;
	}

	public function unregisterPacketListener(TestPlayerPacketListener $listener) : void {
		unset($this->packetListeners[spl_object_id($listener)]);
	}

	/**
	 * @phpstan-param class-string<ClientboundPacket> $packet
	 */
	public function registerSpecificPacketListener(string $packet, TestPlayerPacketListener $listener) : void {
		if ($this->specificPacketListener === null) {
			$this->specificPacketListener = new TestPlayerSpecificPacketListener();
			$this->registerPacketListener($this->specificPacketListener);
		}
		$this->specificPacketListener?->register($packet, $listener);
	}

	public function unregisterSpecificPacketListener(string $packet, TestPlayerPacketListener $listener) : void {
		if ($this->specificPacketListener !== null) {
			$this->specificPacketListener->unregister($packet, $listener);
			if ($this->specificPacketListener->isEmpty()) {
				$this->unregisterPacketListener($this->specificPacketListener);
				$this->specificPacketListener = null;
			}
		}
	}

	public function sendDataPacket(ClientboundPacket $packet, bool $immediate = false) : bool {
		$ret = parent::sendDataPacket($packet, true);
		foreach ($this->packetListeners as $key => $listener) {
			$listener->onPacketSend($packet, $this);
		}

		return $ret;
	}

	protected function createPlayer() : void {
		$getProp = function (string $name) : mixed {
			$rp = new ReflectionProperty(NetworkSession::class, $name);

			return $rp->getValue($this);
		};

		$info = $getProp("info");
		$authenticated = $getProp("authenticated");
		$cached_offline_player_data = $getProp("cachedOfflinePlayerData");
		Server::getInstance()->createPlayer($this, $info, $authenticated, $cached_offline_player_data)->onCompletion(function (Player $player) : void {
			$this->onPlayerCreated($player);
		}, function () : void {
			$this->disconnect("Player creation failed");
			$this->playerAddResolver?->reject();
		});
	}

	private function onPlayerCreated(Player $player) : void {
		// call parent private method
		$rm = new ReflectionMethod(NetworkSession::class, "onPlayerCreated");
		$rm->invoke($this, $player);
		$this->playerAddResolver?->resolve($player);
	}
}
