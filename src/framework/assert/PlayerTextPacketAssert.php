<?php

namespace ShockedPlot7560\PmmpUnit\framework\assert;

use pocketmine\network\mcpe\protocol\TextPacket;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use ShockedPlot7560\PmmpUnit\players\TestPlayer;
use Webmozart\Assert\Assert;

trait PlayerTextPacketAssert {
	use PlayerMessageAssert;
	use PlayerPopupAssert;

	/**
	 * @phpstan-param TextPacket::TYPE_* $type
	 * @phpstan-return PromiseInterface<TextPacket>
	 */
	private function promisePlayerReceiveTextPacket(TestPlayer $player, int $type, int $timeout = 60) : PromiseInterface {
		return $player->registerSpecificSendPacketListener(TextPacket::class, $timeout)
			->then(function (TextPacket $packet) use ($type) {
				Assert::eq($packet->type, $type, sprintf(
					"Expected %s type, got %s type",
					$this->typeToString($type),
					$this->typeToString($packet->type)
				));

				return resolve($packet);
			});
	}

	/**
	 * @phpstan-param TextPacket::TYPE_* $type
	 */
	private function typeToString(int $type) : string {
		switch ($type) {
			case TextPacket::TYPE_RAW:
				return "raw";

			case TextPacket::TYPE_CHAT:
				return "chat";

			case TextPacket::TYPE_TRANSLATION:
				return "translation";

			case TextPacket::TYPE_POPUP:
				return "popup";

			case TextPacket::TYPE_JUKEBOX_POPUP:
				return "jukebox_popup";

			case TextPacket::TYPE_TIP:
				return "tip";

			case TextPacket::TYPE_SYSTEM:
				return "system";

			case TextPacket::TYPE_WHISPER:
				return "whisper";

			case TextPacket::TYPE_ANNOUNCEMENT:
				return "announcement";

			case TextPacket::TYPE_JSON_WHISPER:
				return "json_whisper";

			case TextPacket::TYPE_JSON:
				return "json";

			case TextPacket::TYPE_JSON_ANNOUNCEMENT:
				return "json_announcement";
		}
	}
}
