<?php
class Router extends DI {
	private $host = null;
	private $port = null;
	private $server = null;
	private $connections = array();
	private $connectionsInfo = array();
	private $reads = array();
	private $writes = array();
	private $select_timeout = 3;
	public function __construct($host = '0.0.0.0', $port = '8080') {
		$this->host = $host;
		$this->prot = $port;
		$this->server = $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_set_option($server, SOL_SOCKET, SO_KEEPALIVE, 1);
		socket_set_nonblock($server);
		socket_set_option($server, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 3, 'usec' => 0));
		socket_set_option($server, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 3, 'usec' => 0));
		socket_bind($server, $host, $port);
		socket_listen($server);
	}

	private function pushConnection($sock) {
		$clientKey = (int) $client;
		$this->connections[$clientKey] = $sock;
		$this->connectionsInfo[$clientKey] = array(
			'connected' => false,
			'sock' => $sock,
			'lastlifetime' => time(),
			'data' => '',
		);
	}

	private function makeWebSocket($sock, &$buffer) {
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
	}

	private function checkHeartbeat($sock) {
		$clientKey = (int) $sock;
		if (time() - $this->connectionsInfo[$clientKey]['lastlifetime'] > $this->heartbeat_timeout) {
			l("client {$clientKey} lifetime > {$this->heartbeat_timeout}.");
			socket_close($sock);
			unset($this->connectionsInfo[$clientKey], $this->connections[$clientKey]);
		} else {
			$this->connectionsInfo[$clientKey]['lastlifetime'] = time();
		}
	}

	private function disconnect() {
		socket_close($sock);
		unset($connectionsInfo[$clientKey], $connections[$clientKey]);
	}

	private function receiveData($sockKey) {
		$sock = $this->connections[$sockKey];
		if (false === $sock) {
			throw new Exception("connections read sock not exists", 104);
		}

		$length = socket_recv($sock, $buffer, 2048, 0);
		$clientKey = (int) $sock;

		l("client {$clientKey} selected. length = {$length}");
		if ($length < 7) {
			l("client {$clientKey} length < 7.");
			$this->disconnect($sock);
			l("client {$clientKey} force disconnect");
			return false;
		}

		$buffer = json_decode($this->decode($buffer), true);
		if ($buffer === false) {
			l("client {$clientKey} decode failed");
			$this->disconnect($sock);
			l("client {$clientKey} force disconnect");
		}
		return $length;
	}

	private function writeData() {
		$sock = each($this->writes);
		if (false === $sock) {
			return false;
		}
		$buffer = $this->encode($buffer);
		$length = socket_write($sock, $buffer, strlen($buffer));
		$clientKey = (int) $sock;
		l("write to client {$clientKey}, length {$length}");
		return $length;
	}

	public function waitConnection() {
		$this->reads = $this->connections;
		$this->reads[(int) $this->server] = $this->server;
		$except = null;
		$error = socket_select($this->reads, $this->writes, $except, $this->select_timeout);
		if (0 !== ($errorcode = socket_last_error())) {
			l($errorcode);
			l($socket_strerror($errorcode));
			return false;
		} else {
			return true;
		}
	}

	public function handleConnections() {
		if ($this->waitConnection()) {
			foreach ($this->reads as $sockOffset => $sock) {
				$clientKey = (int) $sock;
				if ($sock === $this->server) {
					$client = socket_accept($this->server);
					$this->pushConnection($client);
					unset($this->reads[$sockOffset]);
					l("client {$clientKey} comming.");
					continue;
				} else {
					$buffer = "";
					$length = socket_recv($sock, $buffer, 2048, 0);
					l("client {$sockKey} selected. length = {$length}");

					//长度过小
					if ($length < 7) {
						l("client {$sockKey} length < 7.");
						$this->disconnect($sock);
						l("client {$sockKey} force disconnect");
						unset($this->reads[$sockOffset]);
						continue;
					}

					if (false === $this->connectionsInfo[$clientKey]['connected']) {
						l("client {$clientKey} is now making connnection.");
						$this->makeWebSocket($sock, $buffer);
						unset($this->reads[$sockOffset]);
					} else {
						l("client {$clientKey} is now reading.");
						$this->connectionsInfo[$clientKey]['reads'] = $buffer;
					}
				}
			}
		}
	}

	public function fetchReads() {
		$sock = current($this->reads);

		//sock有效性
		if ($sock === false) {
			throw new Exception("reads socket key not exists", 100);
		}
		$sockKey = key($this->reads);
		if ($sockKey === false) {
			throw new Exception("reads socket key not exists", 101);
		}

		$buffer = $this->connectionsInfo[$sockKey]['reads'];
		next($this->reads);
		return array($sockKey, $buffer);
	}

	public function nextReads() {
		$next = next($this->reads);
		if ($next === false) {
			return false;
		} else {
			return true;
		}
	}

	public function fetchWrites() {
		$sock = current($this->writes);
		if ($sock === false) {
			throw new Exception("writes socket key not exists", 110);
		}
		$sockKey = key($this->writes);
		if ($sockKey === false) {
			throw new Exception("writes socket key not exists", 111);
		}
		return array($sockKey, $sock);
	}

	public function nextWrites() {
		$next = next($this->reads);
		if ($next === false) {
			return false;
		} else {
			return true;
		}
	}

	public function getWritesCount() {
		return count($this->writes);
	}
	public function getReadsCount() {
		return count($this->reads);
	}

	public function solveReads() {
		foreach ($this->reads as $sockKey => $sock) {
			if ($sock === $this->server) {
				$client = socket_accept($this->server);
				$this->pushConnection($client);
				l("client {$clientKey} comming.");
				continue;
			} else {
				$length = $this->receiveData($sock, $buffer);
				if ($this->connectionsInfo[$clientKey]['connected'] === true) {
					l("client {$clientKey} is now reading.");
					$data = $buffer;
					if ($data === false) {
						l("client {$clientKey} data decode failed. data = '{$data}'");
						continue;
					}
					if ($data['method'] === 0) {
						$result = json_encode(array('method' => 0, 'params' => array()));
					} else {
						call_user_func_array(array($this->injection('SSWD'), $data['method']), $data['params']);
					}
				} else {
					//建立websocket连接
					$this->makeWebSocket($sock, $buffer);
				}
			}
		}
	}

	public function solveWrites() {
		foreach ($this->writes as $sockKey => $sock) {

		}
	}

	public function run() {
		while (true) {
			if ($this->waitConnection()) {
				$this->solveWrites();
				$this->solveReads();
			}
		}
	}
	private function decode($frame) {
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
	private function encode($text) {
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
}