<?php

declare(strict_types=1);

namespace ShockedPlot7560\PmmpUnit\players;

use pocketmine\network\mcpe\PacketSender;

final class TestPacketSender implements PacketSender {
	public function send(string $payload, bool $immediate) : void {
	}

	public function close(string $reason = "unknown reason") : void {
	}
}
