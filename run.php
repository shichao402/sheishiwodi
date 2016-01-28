<?php
ini_set('display_errors', true);
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

include "common.php";
include "sheishiwodi.php";

$o = new SSWD();
$o->run();
