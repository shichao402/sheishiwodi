<?php
define('TMP', '.');
class InjectBase {
	private $map = array();
	private static $singleton = null;
	public function __construct() {

	}
	static public function getInstance() {
		if (self::$singleton === null) {
			self::$singleton = new InjectBase();
		}
		return self::$singleton;
	}
	public function inject($baseObject, $object, $name = null) {
		if ($name === null) {
			$name = $object->getName();
		}
		$this->map[$baseObject->getName()][$name] = $object;
	}

	public function get($baseObject, $objectName) {
		return $this->map[$baseObject->getName()][$objectName];
	}
}

class OpenRtx {
	private $appid = "APP_99DA446D3E0B1223FCCE0FE868";
	private $appkey = "45CE2FA19A569D82221EC7983F6FA5A9";
	private $auth = null;
	private $request_token = null;
	private $inputParams = array();
	private $host = "10.14.70.165:18810";
	//private $host = "rtxsdk.oa.tencent.com:18810";

	public function __construct() {
		$this->genAuth();
		$this->client_ip = NetworkService::getServerIP();
	}

	private function genAuth() {
		$key = md5($this->appid . $this->appkey, TRUE);
		$iv = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
		$auth_json = array("appid" => $this->appid, "timestamp" => time());
		$auth_json = json_encode($auth_json);
		$this->auth = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $auth_json, MCRYPT_MODE_CBC, $iv));
	}

	public function getInputParams() {
		$this->inputParams['sender'] = 'testsender';
		$this->inputParams['request_token'] = 'testrequest_token';
		return $this->inputParams;
	}

	public function request($action, $param) {
		@$this->request_token = $_GET['request_token'];
		@$this->inputParams = $_GET;

		$access_token = file_get_contents("access_token");
		$req = array(
			'appid' => $this->appid,
			'auth' => $this->auth,
			'client_ip' => $this->client_ip,
			'access_token' => $access_token,
		);
		$req = array_merge($req, $_GET);
		$req = array_merge($req, $param);
		$req = json_encode($req);
		$networkService = new networkService();
		$result = $networkService->curl("http://{$this->host}/v2/webapp.php?action=${action}", "test_cookies", array('req' => $req));
		if ($result['result'] !== 0) {
			if ($this->init()) {
				return $this->request($action, $param);
			} else {
				throw new Exception("Error Processing Request {$action} " . var_export($param, true), 1);
			}
		} else {
			return $result;
		}
	}

	public function init() {
		$req = array(
			'appid' => $this->appid,
			'auth' => $this->auth,
			'client_ip' => $this->client_ip,
			'request_token' => $this->request_token,
		);
		$req = json_encode($req);

		$result = $networkObject->curl("http://{$this->host}/v2/webapp.php?action=get_access_token", "test_cookies", array('req' => $req));

		if ($result['result'] === 0) {
			file_put_contents("access_token", $result["access_token"]);
			return true;
		} else {
			file_put_contents("access_token", "");
			return false;
		}
	}

	public function test() {
		//会话
		$action = "get_session_info";
		$param = array(
		);
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($result);
		if ($result['result'] !== 0) {
			$this->init();
		}
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($action);
		var_dump($result);
		if ($result['result'] !== 0) {
			return false;
		}
		$get_session_info = $result;

		//用户信息
		$action = "get_user_info";
		$param = array(
			"open_user" => array($get_session_info['session_info']['member'][0], $get_session_info['session_info']['owner']),
		);
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($action);
		var_dump($result);
		if ($result['result'] !== 0) {
			return false;
		}
		$get_user_info = $result;

		//应用消息
		$action = "send_app_msg";
		$param = array(
			"sender" => $get_session_info['session_info']['owner'],
			"session_guid" => $get_session_info['session_info']['session_guid'],
			"title" => "test title标题",
			"summary" => "test summary摘要",
			"url_param" => "test1=1&test2=2",
			"url_text" => "text text url text文字",
		);
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($action);
		var_dump($result);
		if ($result['result'] !== 0) {
			return false;
		}
		$send_app_msg = $result;

		//发送提醒
		$action = "alert";
		$param = array(
			"type" => "Tencent.RTX.Alert",
			"sender" => $get_session_info['session_info']['owner'],
			"receiver" => array($get_session_info['session_info']['member'][0], $get_session_info['session_info']['owner']),
			"session_guid" => $get_session_info['session_info']['session_guid'],
			"title" => "test title标题",
			"content" => "test content内容",
			"url_param" => "test1=1&test2=2&内容1=内容1",
			"url_text" => "text text url text文字",
		);
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($action);
		var_dump($result);
		if ($result['result'] !== 0) {
			return false;
		}
		$alert = $result;

		//发送文本信息
		$action = "send_msg";
		$param = array(
			"type" => "Tencent.RTX.IM",
			"sender" => $get_session_info['session_info']['owner'],
			"session_guid" => $get_session_info['session_info']['session_guid'],
			"content" => "test content 内容内容",
		);
		$result = $this->request($action, $param);
		$result = json_decode($result, true);
		var_dump($action);
		var_dump($result);
		if ($result['result'] !== 0) {
			return false;
		}
		$send_msg = $result;
	}
}

class NetworkService {
	private $info = null;
	static public function getClientIP() {
		if (getenv('HTTP_CLIENT_IP')) {
			$client_ip = getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
			$client_ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('REMOTE_ADDR')) {
			$client_ip = getenv('REMOTE_ADDR');
		} else {
			$client_ip = $_SERVER['REMOTE_ADDR'];
		}
		return $client_ip;
	}
	static public function getServerIP() {
		if (isset($_SERVER)) {
			if ($_SERVER['SERVER_ADDR']) {
				$server_ip = $_SERVER['SERVER_ADDR'];
			} else {
				$server_ip = $_SERVER['LOCAL_ADDR'];
			}
		} else {
			$server_ip = getenv('SERVER_ADDR');
		}
		return $server_ip;
	}

	public function curl($url, $cookie, $post = array()) {
		$this->info = null;
		$cookiefile = TMP . '/' . $cookie . '.cookies';
		$post = http_build_query($post);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url); // 登录提交的地址
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 是否自动显示返回的信息
		curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
		curl_setopt($curl, CURLOPT_POST, 1); // post方式提交
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // 要提交的信息
		$content = curl_exec($curl);
		$this->info = curl_getinfo($curl);
		curl_close($curl);
		return $content;
	}

	public function getLastInfo() {
		return $this->info;
	}
}
/**
 * 文件操作
 */
class FileHandle {
	private $handle = null;
	private $name = null;
	private $storePath = CACHE_ROOT;
	public function __construct($name) {
		$this->name = $name;

	}

	public function write() {

	}

}
