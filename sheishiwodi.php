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
		$this->openRtxObject = new OpenRtx();
		$this->initGame();
	}

	public function initGame() {
		$this->current_uuid = $this->openRtxObject->getInputParams()['sender'];
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

	private function do($method, $args) {

	}
	public function run() {
		$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($server, '127.0.0.1', 8080);
		socket_listen($server);
		$this->master = $server;
		$this->sockets = array($this->master);
		while (true) {
			$changes = $this->sockets;
			socket_select($changes, $write = NULL, $except = NULL, NULL);
			foreach ($changes as $sock) {
				if ($sock == $this->master) {
					$client = socket_accept($this->master);
					//$key=uniqid();
					$this->sockets[] = $client;
					$this->users[] = array(
						'socket' => $client,
						'shou' => false,
					);
				} else {
					$len = socket_recv($sock, $buffer, 2048, 0);
					$found = false;
					foreach ($this->users as $k => $v) {
						if ($sock == $v['socket']) {
							$found = true;
							break;
						}
					}
					if ($found == false) {
						$k = false;
					}
					//用户断开
					if ($len < 7) {
						$name = $this->users[$k]['ming'];
						$k = array_search($sock, $this->sockets);
						socket_close($sock);
						unset($this->sockets[$k]);
						unset($this->users[$k]);
						$this->send2($name, $k);
						continue;
					}

					if (!$this->users[$k]['shou']) {
						//初始化连接
						$buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
						$key = trim(substr($buf, 0, strpos($buf, "\r\n")));
						$new_key = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
						$new_message = "HTTP/1.1 101 Switching Protocols\r\n";
						$new_message .= "Upgrade: websocket\r\n";
						$new_message .= "Sec-WebSocket-Version: 13\r\n";
						$new_message .= "Connection: Upgrade\r\n";
						$new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
						socket_write($this->users[$k]['socket'], $new_message, strlen($new_message));
						$this->users[$k]['shou'] = true;
					} else {
						//发送消息
						$buffer = $this->uncode($buffer);
						$this->send($k, $buffer);
					}
				}
			}

		}
	}
}
