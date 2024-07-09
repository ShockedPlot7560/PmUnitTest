<?php

namespace ShockedPlot7560\PmmpUnit\framework\assert;

use pocketmine\network\mcpe\protocol\TextPacket;
use React\Promise\PromiseInterface;
use ShockedPlot7560\PmmpUnit\players\TestPlayer;

trait PlayerMessageAssert {
	/**
	 * @phpstan-return PromiseInterface<null>
	 */
	protected function promisePlayerReceiveMessageEquals(string $message, TestPlayer $player, bool $cleanPacket = false, int $timeout = 60) : PromiseInterface {
		return $this->promisePlayerReceiveMessage($player, $cleanPacket, $timeout)
			->then(function (string $m) use ($message) {
				return $this->assertEquals($message, $m);
			});
	}

	/**
	 * @phpstan-return PromiseInterface<null>
	 */
	protected function promisePlayerReceiveMessageNotEquals(string $message, TestPlayer $player, bool $cleanPacket = false, int $timeout = 60) : PromiseInterface {
		return $this->promisePlayerReceiveMessage($player, $cleanPacket, $timeout)
			->then(function (string $m) use ($message) {
				return $this->assertNotEquals($message, $m);
			});
	}

	/**
	 * @phpstan-return PromiseInterface<null>
	 */
	protected function promisePlayerReceiveMessageContains(string $needle, TestPlayer $player, bool $cleanPacket = false, int $timeout = 60) : PromiseInterface {
		return $this->promisePlayerReceiveMessage($player, $cleanPacket, $timeout)
			->then(function (string $m) use ($needle) {
				return $this->assertStringContainsString($needle, $m);
			});
	}

	/**
	 * @phpstan-return PromiseInterface<null>
	 */
	protected function promisePlayerReceiveMessageNotContains(string $needle, TestPlayer $player, bool $cleanPacket = false, int $timeout = 60) : PromiseInterface {
		return $this->promisePlayerReceiveMessage($player, $cleanPacket, $timeout)
			->then(function (string $m) use ($needle) {
				return $this->assertStringNotContainsString($needle, $m);
			});
	}

	/**
	 * @phpstan-return PromiseInterface<string>
	 */
	private function promisePlayerReceiveMessage(TestPlayer $player, bool $cleanPacket = false, int $timeout = 60) : PromiseInterface {
		// if ($message instanceof Translatable) {
		// 	return $this->promisePlayerReceiveTextPacket($player, TextPacket::TYPE_TRANSLATION, $cleanPacket)
		// 		->then(function (TextPacket $packet) use ($player) : string {
		// 			var_dump($packet);

		// 			return $packet->parameters[1];
		// 		});
		// } else {
		return $this->promisePlayerReceiveTextPacket($player, TextPacket::TYPE_RAW, $timeout)
			->then(function (TextPacket $packet) : string {
				return $packet->message;
			});
		// }
	}
}
