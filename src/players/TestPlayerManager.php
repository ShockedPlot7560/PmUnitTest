<?php

namespace ShockedPlot7560\PmmpUnit\players;

use InvalidArgumentException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\compression\ZlibCompressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\network\mcpe\StandardPacketBroadcaster;
use pocketmine\player\Player;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Limits;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use ShockedPlot7560\PmmpUnit\players\behaviour\internal\TestPlayerMovementData;
use ShockedPlot7560\PmmpUnit\players\behaviour\internal\TryChangeMovementInternalTestPlayerBehaviour;
use ShockedPlot7560\PmmpUnit\players\behaviour\internal\UpdateMovementInternalTestPlayerBehaviour;
use ShockedPlot7560\PmmpUnit\players\behaviour\TestPlayerBehaviourFactory;
use ShockedPlot7560\PmmpUnit\players\info\TestPlayerInfo;
use ShockedPlot7560\PmmpUnit\players\listener\PacketReceiveListener;
use ShockedPlot7560\PmmpUnit\players\listener\TestPlayerListener;
use ShockedPlot7560\PmmpUnit\players\network\TestPlayerNetworkSession;

class TestPlayerManager implements Listener {
	/** @var TestPlayerListener[] */
	private array $listeners = [];

	/** @var TestPlayer[] */
	private array $testPlayers = [];
	private ?PacketReceiveListener $packetReceiveListener = null;

	/** @var array<string, mixed> */
	private array $defaultExtraData = [
		"CurrentInputMode" => InputMode::MOUSE_KEYBOARD, /** @see ClientData::$CurrentInputMode */
		"DefaultInputMode" => InputMode::MOUSE_KEYBOARD, /** @see ClientData::$DefaultInputMode */
		"DeviceOS" => DeviceOS::DEDICATED, /** @see ClientData::$DeviceOS */
		"GameVersion" => ProtocolInfo::MINECRAFT_VERSION_NETWORK, /** @see ClientData::$GameVersion */
		"DeviceModel" => "PmmpUnit", /** @see ClientData::$DeviceModel */
		"DeviceId" => "PmmpUnit", /** @see ClientData::$DeviceId */
	];
	private static TestPlayerManager $instance;

	public function __construct(
		private PluginBase $plugin
	) {
		self::$instance = $this;
	}

	public static function getInstance() : TestPlayerManager {
		return self::$instance;
	}

	public function getPlugin() : PluginBase {
		return $this->plugin;
	}

	public function onEnable() : void {
		$client_data = new ReflectionClass(ClientData::class);
		foreach ($client_data->getProperties() as $property) {
			$comment = $property->getDocComment();
			if ($comment === false || !in_array("@required", explode(PHP_EOL, $comment), true)) {
				continue;
			}

			$property_name = $property->getName();
			if (isset($this->defaultExtraData[$property_name])) {
				continue;
			}

			$this->defaultExtraData[$property_name] = $property->hasDefaultValue() ? $property->getDefaultValue() : match ($property->getType()?->getName()) {
				"string" => "",
				"int" => 0,
				"array" => [],
				"bool" => false,
				default => throw new RuntimeException("Cannot map default value for property: " . ClientData::class . "::{$property_name}")
			};
		}

		$this->registerListener(new DefaultTestPlayerListener($this));
		TestPlayerBehaviourFactory::registerDefaults($this);

		$this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () : void {
			foreach ($this->testPlayers as $player) {
				$player->tick();
			}
		}), 1);

		$this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
	}

	public function getPacketReceiveListener() : PacketReceiveListener {
		if ($this->packetReceiveListener === null) {
			$this->packetReceiveListener = new PacketReceiveListener();
			$this->getPlugin()->getServer()->getPluginManager()->registerEvents($this->packetReceiveListener, $this->getPlugin());
		}

		return $this->packetReceiveListener;
	}

	public function registerListener(TestPlayerListener $listener) : void {
		$this->listeners[spl_object_id($listener)] = $listener;
		$server = $this->plugin->getServer();
		foreach ($this->testPlayers as $uuid => $_) {
			$listener->onPlayerAdd($server->getPlayerByRawUUID($uuid) ?? throw new RuntimeException("Player with UUID $uuid not found"));
		}
	}

	public function unregisterListener(TestPlayerListener $listener) : void {
		unset($this->listeners[spl_object_id($listener)]);
	}

	public function isTestPlayer(Player $player) : bool {
		return isset($this->testPlayers[$player->getUniqueId()->getBytes()]);
	}

	public function getTestPlayer(Player $player) : ?TestPlayer {
		return $this->testPlayers[$player->getUniqueId()->getBytes()] ?? null;
	}

	/**
	 * @return Promise<Player>
	 */
	public function addPlayer(TestPlayerInfo $info) : Promise {
		$server = $this->plugin->getServer();
		$network = $server->getNetwork();

		$internal_resolver = new PromiseResolver();
		$rakLibInterface = null;
		foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
			if ($interface instanceof RakLibInterface) {
				$rakLibInterface = $interface;
				break;
			}
		}
		if ($rakLibInterface === null) {
			throw new RuntimeException("RakLibInterface not found");
		}
		$session = new TestPlayerNetworkSession(
			$server,
			$network->getSessionManager(),
			PacketPool::getInstance(),
			new TestPacketSender(),
			new StandardPacketBroadcaster($server),
			ZlibCompressor::getInstance(),
			$server->getIp(),
			$server->getPort(),
			$internal_resolver
		);
		$network->getSessionManager()->add($session);

		$rp = new ReflectionProperty(NetworkSession::class, "info");
		$rp->setValue($session, new XboxLivePlayerInfo($info->xuid, $info->username, $info->uuid, $info->skin, "en_US" /* TODO: Make locale configurable? */, array_merge($info->extra_data, $this->defaultExtraData)));

		$rp = new ReflectionMethod(NetworkSession::class, "onServerLoginSuccess");
		$rp->invoke($session);

		$packet = ResourcePackClientResponsePacket::create(ResourcePackClientResponsePacket::STATUS_COMPLETED, []);
		$serializer = PacketSerializer::encoder();
		$packet->encode($serializer);
		$session->handleDataPacket($packet, $serializer->getBuffer());

		$internal_resolver->getPromise()->onCompletion(function (Player $player) use ($info, $session) : void {
			$player->setViewDistance(4);

			$this->testPlayers[$player->getUniqueId()->getBytes()] = $testPlayer = new TestPlayer($session);

			$movement_data = TestPlayerMovementData::new();
			$testPlayer->addBehaviour(new TryChangeMovementInternalTestPlayerBehaviour($movement_data), Limits::INT32_MIN);
			$testPlayer->addBehaviour(new UpdateMovementInternalTestPlayerBehaviour($movement_data), Limits::INT32_MAX);
			foreach ($info->behaviours as $behaviourIdentifier => $behaviourData) {
				$testPlayer->addBehaviour(TestPlayerBehaviourFactory::create($behaviourIdentifier, $behaviourData));
			}

			foreach ($this->listeners as $listener) {
				$listener->onPlayerAdd($player);
			}

			$player->doFirstSpawn();

			if (!$player->isAlive()) {
				$player->respawn();
			}
		}, static function () : void { /* no internal steps to take if player creation failed */ });

		// Create a new promise, to make sure a TestPlayer is always
		// registered before the caller's onCompletion is called.
		$result = new PromiseResolver();
		$internal_resolver->getPromise()->onCompletion(static function (Player $player) use ($result) : void {
			$result->resolve($player);
		}, static function () use ($result) : void { $result->reject(); });

		return $result->getPromise();
	}

	public function removePlayer(Player $player, bool $disconnect = true) : void {
		if (!$this->isTestPlayer($player)) {
			throw new InvalidArgumentException("Invalid Player supplied, expected a test player, got " . $player->getName());
		}

		if (!isset($this->testPlayers[$id = $player->getUniqueId()->getBytes()])) {
			return;
		}

		$this->testPlayers[$id]->destroy();
		unset($this->testPlayers[$id]);

		if ($disconnect) {
			$player->disconnect("Removed");
		}

		foreach ($this->listeners as $listener) {
			$listener->onPlayerRemove($player);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $event) : void {
		$player = $event->getPlayer();
		try {
			$this->removePlayer($player, false);
		} catch (InvalidArgumentException $e) {
		}
	}
}
