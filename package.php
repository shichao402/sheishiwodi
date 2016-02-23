<?php
class Connection extends DI {
	private $data = null;
	private $id = null;
	private $to = null;
	private $sock = null;
	private $last_life_time = null;

	public function __construct() {

	}
	public function setData($data) {
		$this->data = $data;
	}

	public function getData() {
		return $this->data;
	}

	public function heartBeat() {
		$this->last_life_time = time();
	}

	public function setTo($connectionObject) {
		if (!is_array($connectionObject)) {
			$connectionObject = array($connectionObject);
		}
		$this->to = $connectionObject;
	}

	public function getToGroup($connectionGroupObject) {
		if (!is_array($connectionGroupObject)) {
			$connectionGroupObject = array($connectionGroupObject);
		}
		$this->to = $connectionGroupObject;
	}

	public function setSock(Resource $sock) {
		$this->sock = $sock;
		$this->id = (int) $sock;
	}
}