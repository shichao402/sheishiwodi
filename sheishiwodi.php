<?php
class SSWDException extends Exception {
	public $object = array();
	public $args = array();

	public function __construct($object, $args, $message, $code, $previous) {
		$this->object = $object;
		$this->args = $args;
		parent::__construct($message, $code, $previous);
	}
}

class SSWD {
	private $openrtx_session_info = null;
	private $openrtx_user_info = null;
	private $cache_data = null;
	private $openRtxObject = null;
	private $current_uuid = null;
	public function __construct() {
		//$this->openRtxObject = new OpenRtx();
		//$this->initGame();
	}

	public function initGame() {
		$params = $this->openRtxObject->getInputParams();
		$this->current_uuid = $params['sender'];
		//获取会话信息
		$action = "get_session_info";
		$param = array();
		$result = $this->openRtxObject->request($action, $param);
		$result = json_decode($result, true);
		$this->openrtx_session_info = $result;

		//获取会话中所有人的信息
		$action = "get_user_info";
		$param = array(
			"open_user" => $this->openrtx_session_info['session_info']['member'],
		);
		$result = $this->openRtxObject->request($action, $param);
		$result = json_decode($result, true);
		$this->openrtx_user_info = $result;
		if (file_exists($this->openrtx_session_info['session_guid'])) {
			$this->cache_data = unserialize(file_get_contents($this->openrtx_session_info['session_guid']));
		} else {
			//初始化会话缓存
			$this->cache_data = array(
				'session_guid' => $this->openrtx_session_info['session_guid'],
				'user_info' => array(),
				'vote_count' => array(),
				'status' => "waiting",
			);
			//组织数据
			foreach ($this->openrtx_user_info['user_info'] as $each) {
				//参与吗,默认不参与
				$each['status'] = "not_join";
				//扮演角色
				$each['role'] = null;
				//推了谁
				$each['vote_list'] = array();

				$this->cache_data['user_info'][$each['openid']] = $each;
				$this->cache_data['vote_count'][$each['openid']] = 0;
			}
			//立刻持久化
			$this->saveCache();
		}
	}

	public function joinGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "joined";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
	}

	public function quitGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "not_join";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
	}

	public function readyGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "ready";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
	}

	public function startGame() {
		$word1 = "word1";
		$word2 = "word2";

		$joined = array();
		foreach ($this->cache_data['user_info'] as $key => $each) {
			if ($this->cache_data['user_info'][$key]['status'] === "joined") {
				$joined[$key] = null;
			}
		}

		$randopenid = array_rand($joined, 1);
		if (!is_array(randopenid)) {
			$randopenid = array($randopenid);
		}
		foreach ($randopenid as $each) {
			$this->cache_data['user_info'][$key]['role'] = $word2;
			unset($joined[$randopenid]);
		}

		$randopenid = array_rand($joined, count($joined) - 1);
		if (!is_array(randopenid)) {
			$randopenid = array($randopenid);
		}
		foreach ($randopenid as $each) {
			$this->cache_data['user_info'][$key]['role'] = $word1;
			unset($joined[$randopenid]);
		}
		$this->saveCache();
	}

	private function saveCache() {
		$data = serialize($this->cache_data);
		if (!file_put_contents($this->openrtx_session_info['session_guid'], $data)) {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", file='{$this->openrtx_session_info['session_guid']}'", 500);
		}
	}

	public function vote($openid) {
		if ($this->cache_data['status'] === 'playing') {
			if (!array_key_exists($openid, $this->cache_data['user_info'])) {
				throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", openid not exists, '{$openid}'", 502);
			}
			$this->cache_data['vote_count'][$openid] += 1;
			foreach ($this->cache_data['user_info'] as $key => $each) {
				if ($openid === $this->current_uuid) {
					$this->cache_data['user_info'][$key]['vote_list'][] = $openid;
				} else {
					$this->cache_data['user_info'][$key]['vote_list'][] = null;
				}
			}
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
	}

	private function action($method, $args) {
		$return = array(
			'result' => 0,
			'data' => array(
				1,
				2,
				3,
			),
		);
		return json_encode($return);
	}

	public function run() {
		$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_set_nonblock($server);
		socket_bind($server, '0.0.0.0', 80);
		socket_listen($server);
		$buffer = null;
		$connectionsInfo = array();
		$connections = $reads = $writes = array();
		while (true) {
			$reads = $connections;
			$reads[(int) $server] = $server;
			if (socket_select($reads, $writes, $except = NULL, NULL)) {
				foreach ($reads as $sockKey => $sock) {
					if ($sock === $server) {
						$client = socket_accept($server);
						$clientKey = (int) $client;
						$connections[$clientKey] = $client;
						$connectionsInfo[$clientKey] = array(
							'connected' => false,
							'uuid' => '',
						);
						l("client {$clientKey} comming.");
					} else {
						$length = socket_recv($sock, $buffer, 2048, 0);
						var_dump($length, decode($buffer));
						$clientKey = (int) $sock;
						l("client {$clientKey} selected.");
						if ($length < 10) {
							l("client {$clientKey} length < 10.");
							socket_close($sock);
							unset($connectionsInfo[$clientKey], $connections[$clientKey]);
							continue;
						}
						//记录连接建立
						if ($connectionsInfo[$clientKey]['connected'] === false) {
							$buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
							$key = trim(substr($buf, 0, strpos($buf, "\r\n")));
							$new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
							$new_message = "HTTP/1.1 101 Switching Protocols\r\n";
							$new_message .= "Upgrade: websocket\r\n";
							$new_message .= "Sec-WebSocket-Version: 13\r\n";
							$new_message .= "Connection: Upgrade\r\n";
							$new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
							socket_write($sock, $new_message, strlen($new_message));
							$connectionsInfo[$clientKey]['connected'] = true;
							l("client {$clientKey} connected.");
							continue;
						} elseif ($connectionsInfo[$clientKey]['connected'] === true) {
							l("client {$clientKey} reading.");
							// $data = json_decode($buffer, true);
							// // if ($data === false) {
							// // 	socket_close($sock);
							// // 	unset($reads[$sockKey]);
							// // 	unset($connections[$clientKey]);
							// // 	l("client {$clientKey} data decode failed.");
							// // }
							@$result = $this->action($data['method'], $data['params']);
							$result = json_encode($result);
							$result = encode($result);
							var_dump($connections);
							socket_write($sock, $result, strlen($result));
						}
					}
				}
				foreach ($writes as $sockKey => $sock) {
					//socket_write($sock, $result, strlen($result));
				}
			}
		}
	}
}
function l($a) {
	echo $a . "\n";
}

function decode($frame) {
	$len = ord($frame[1]) & 127;
	if ($len === 126) {
		$ofs = 8;
	} elseif ($len === 127) {
		$ofs = 14;
	} else {
		$ofs = 6;
	}
	$text = '';
	for ($i = $ofs; $i < strlen($frame); $i++) {
		$text .= $frame[$i] ^ $frame[$ofs - 4 + ($i - $ofs) % 4];
	}
	return $text;
}
function encode($text) {
	$b = 129;
	$len = strlen($text);
	if ($len < 126) {
		return pack('CC', $b, $len) . $text;
	} elseif ($len < 65536) {
		return pack('CCn', $b, 126, $len) . $text;
	} else {
		return pack('CCNN', $b, 127, 0, $len) . $text;
	}
}