<?php

namespace ShockedPlot7560\PmmpUnit\tests\normal;

use Exception;
use React\Promise\PromiseInterface;
use ShockedPlot7560\PmmpUnit\framework\attribute\ExpectedExceptionAttribute;
use ShockedPlot7560\PmmpUnit\framework\TestCase;
use ShockedPlot7560\PmmpUnit\players\TestPlayer;
use ShockedPlot7560\PmmpUnit\utils\TimeoutException;

class TimeoutTest extends TestCase {
	/**
	 * @return PromiseInterface<null>
	 */
	#[ExpectedExceptionAttribute(TimeoutException::class)]
	public function testTimeout() : PromiseInterface {
		return $this->getPlayer()->then(function (TestPlayer $testPlayer) {
			$promise = $this->promisePlayerReceiveMessageEquals("an_unique_string", $testPlayer, timeout: 5);

			return $promise;
		})
			->then(function () {
				throw new Exception("This should not be called");
			});
	}
}
