<?php
class Game extends DI {
	public function __construct() {
		$this->inject(new SSWD());
		$this->inject(new WSServer(), "game_server");
	}
	public function run() {
		while ($this->waitConnection()) {
			$this->dispatch();
		}
	}
}