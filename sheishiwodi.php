<?php
class SSWDException extends Exception {
	public $object = array();
	public $args = array();

	public function __construct($object, $args, $message, $code, $previous) {
		$this->object = $object;
		$this->args = $args;
		parent::__construct($message, $code, $previous);
	}
	static public function create($object, $args, $message, $code, $previous) {
		return new SSWDException($object, $args, $message, $code, $previous);
	}
}

class SSWD extends DI {
	private $openrtx_session_info = null;
	private $openrtx_user_info = null;
	private $openrtx_object = null;

	private $cache_data = null;

	private $game_round = 0;

	private $game_turn_uuid_key = null;
	private $game_turn_uuid_list = array();

	private $game_status = null;
	private $game_status_flow = array(
		'waiting' => array('prepare_play'),
		'prepare_play' => array('playing'),
		'playing' => array('waiting'),
	);
	private $user_status_flow = array(
		'not_join' => array('joined'),
		'joined' => array('ready'),
		'ready' => array('playing'),
		'playing' => array('dead', 'not_join'),
		'dead' => array('not_join', 'joined'),
	);

	public function __construct() {
		$this->inject(new WSServer(), 'server_object');
		//$this->inject(new OpenRtx(), 'auth_object');
		//$this->initGame();
	}

	public function dispatch($sockKey, $buffer) {
		$buffer = json_decode($buffer, true);
		$action = $buffer['action'];
		$params = $buffer['params'];
		//call action with params

		call_user_func_array(array($this, $action), $params);
	}

	public function run() {
		$server = $this->injection('server_object');
		while (true) {
			try {
				$server->handleConnections();
				while (false !== ($reads = $server->fetchReads())) {
					list($sockKey, $buffer) = $reads;
					var_dump($buffer);
					//游戏处理客户端请求过来的逻辑

				}
			} catch (Exception $e) {

			}
		}
	}

	private function gameStatusFlow($custom_status) {
		if (array_key_exists($this->game_status, $this->game_status_flow)) {
			$nextStatus = $this->game_status_flow[$this->game_status][$custom_status];
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", invalid status flow", 502);
		}
	}

	private function userStatusFlow($current_status, $custom_status) {
		if (array_key_exists($current_status, $this->user_status_flow)) {
			$nextStatus = $this->user_status_flow[$current_status][$custom_status];
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", invalid status flow", 503);
		}
	}

	private function gameTurnFlow() {
		$next = $this->game_turn_uuid_key + 1;
		if (array_key_exists($next, $this->game_turn_uuid_list)) {
			$this->game_turn_uuid_key = $next;
			return true;
		} else {
			return false;
		}
	}

	public function createGame() {
		$params = $this->openrtx_object->getInputParams();
		$this->current_uuid = $params['sender'];
		//获取会话信息
		$action = "get_session_info";
		$param = array();
		$result = $this->openrtx_object->request($action, $param);
		$result = json_decode($result, true);
		$this->openrtx_session_info = $result;

		//获取会话中所有人的信息
		$action = "get_user_info";
		$param = array(
			"open_user" => $this->openrtx_session_info['session_info']['member'],
		);
		$result = $this->openrtx_object->request($action, $param);
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
				//描述
				$each['description_list'] = array();

				$this->cache_data['user_info'][$each['openid']] = $each;
				$this->cache_data['vote_count'][$each['openid']] = 0;
			}
			//立刻持久化
			$this->saveCache();
		}
		return 0;
	}

	public function joinGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "joined";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
		return 0;
	}

	public function quitGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "not_join";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
		return 0;
	}

	public function readyGame() {
		if ($this->cache_data['status'] === 'waiting') {
			$this->cache_data['user_info'][$this->current_uuid]['status'] = "ready";
			$this->saveCache();
		} else {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", status='{$this->cache_data['status']}'", 501);
		}
		return 0;
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
		return 0;
	}

	private function saveCache() {
		$data = serialize($this->cache_data);
		if (!file_put_contents($this->openrtx_session_info['session_guid'], $data)) {
			throw new SSWDException($this, func_get_args(), "Error Processing " . __METHOD__ . ", file='{$this->openrtx_session_info['session_guid']}'", 500);
		}
		return 0;
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
		return 0;
	}

	public function commitDescription($description) {
		//@todo 校验用户是不是现在可以发言
		$openid = $this->game_turn_uuid_list[$this->game_turn_uuid_key];
		$this->cache_data['user_info'][$openid]['description_list'][$this->game_round] = $description;
		return 0;
	}

	private function callClientAction($method_name, $params) {

	}

	private function callServerAction($method_name, $params) {
		if (!is_array($params)) {
			$params = array($params);
		}
		try {
			$result = call_user_func_array(array($this, $method), $params);
		} catch (SSWDException $e) {
			$result = $e->getCode();
		}
		$this->callClientAction();
		$return = array(
			'result' => (int) $result,
			'method' => $method,
			'data' => array(

			),
		);
		return json_encode($return);
	}

	private function action($method, $args) {
		if (!is_array($args)) {
			$args = array($args);
		}
		try {
			$result = call_user_func_array(array($this, $method), $args);
		} catch (SSWDException $e) {
			$result = $e->getCode();
		}
		$return = array(
			'result' => (int) $result,
			'method' => $method,
			'data' => array(
				1,
				2,
				3,
			),
		);
		return json_encode($return);
	}

}
function l($a) {
	echo $a . "\n";
}