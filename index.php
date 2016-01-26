<?php
ini_set(‘display_errors’, true);
error_reporting(E_ALL);

include "common.php";

//初始化房间信息

//获取会话信息
$openRtxObject = new OpenRtx();
$action = "get_session_info";
$param = array();
$result = $this->request($action, $param);
$result = json_decode($result, true);
$get_session_info = $result;

//获取会话中所有人的信息
$action = "get_user_info";
$param = array(
	"open_user" => $get_session_info['session_info']['member'],
);
$result = $this->request($action, $param);
$result = json_decode($result, true);
$get_user_info = $result;

//加载会话缓存
if (file_exists($get_session_info['session_guid'])) {
	$session_info = json_decode(file_get_contents($get_session_info['session_guid']), true);
} else {
	//初始化会话缓存
	$session_info = array(
		'session_guid' => $get_session_info['session_guid'],
		'user_info' => $get_user_info['user_info'],
	);

	foreach ($get_user_info['user_info'] as $each) {
		$session_info['user_info'] = $each;
		//扮演角色
		$session_info['user_info']['role'] = null;
		//推了谁
		$session_info['user_info']['push_list'] = array();
		//被推了的次数
		$session_info['user_info']['be_push_count_list'] = array();
	}
}
