<?php
class Socket_Server {
	const SOCK_IP = '0.0.0.0'; //本地地址
	const SOCK_PORT = 8999;
	const TIME_OUT = 60;
	const MAX_CONNECTS = 100;

	private $_sock;
	private $_conn = array();

	public function __construct() {
		@set_exception_handler(array('Socket_Server', 'exception_handler'));
		$this->_sock = $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if (!$sock) {
			throw new Exception(socket_strerror($sock));
		}

		$result = socket_bind($sock, self::SOCK_IP, self::SOCK_PORT);
		if (!$result) {
			throw new Exception(socket_strerror($result));
		}

		$result = socket_listen($sock);
		socket_set_nonblock($sock);
		if (!$result) {
			throw new Exception(socket_strerror($result));
		}

		//保持接收读写请求
		$this->accept();
		printf("***Socket bye!***\n");
		//socket_close($this->_sock);
	}

	private function accept() {
		$readfds = array(intval($this->_sock) => $this->_sock);
		$writefds = array();
		$writeDatas = array();
		while (true) {
			//接受一个Socket连接
			$connection = socket_accept($this->_sock);
			if (!empty($connection)) {
				$this->_conn[intval($connection)] = $connection;
				printf("***Socket {$connection} connected!***\n");
			}
			$readfds = $readfds + $this->_conn;
			$writefds = &$this->_conn;

			if (socket_select($readfds, $writefds, $null, self::TIME_OUT)) {
				//$this->writeLog('通了~' . var_export($connection, true));
				if ($connection < 0) {
					//throw new Exception(socket_strerror($connection));
					//$this->_write(CError::$ERRNO_CONF['SERVICE_FAILED']);
					break;
				}
				if (count($this->_conn) >= self::MAX_CONNECTS) {
					//$this->_write(CError::$ERRNO_CONF['OUT_OF_MAX_CONNECTS']);
					break;
				}
				echo "sleep ..\n";
				sleep(5);
			} else {
				continue;
			}

			foreach ($readfds as $key => $fd) {
				// $this->writeLog('out');
				//读取指定长度的数据
				while ($data = socket_read($fd, 1000, PHP_BINARY_READ)) {
					// $this->writeLog('in~');
					//——-这里写业务逻辑(可能会执行很长时间)——
					unset($readfds[$key]);
					break;
				}
			}

			foreach ($writefds as $key => $fd) {
				if (!empty($writeDatas[$key])) {
					socket_write($fd, $writeDatas[$key]);
					//$this->_write($writeDatas[$key]);//返回给pos的数据
					/**********结束一个连接*********/
					// $this->writeLog('out~');
					//因为没有使用缓冲，所以发送给客户端的信息不会因为写入缓冲返回成功而误以为已经成功发给客户端了。
					//关闭一个socket资源
					socket_close($this->_conn[$key]);
					unset($this->_conn[$key]);
					printf("***bye!***\n");
				}
			}
		}
	}
}

new Socket_Server();