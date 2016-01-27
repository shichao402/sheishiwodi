<?php
ini_set('display_errors', true);
error_reporting(E_ALL);

include "common.php";
include "sheishiwodi.php";

$o = new SSWD();
$o->run();
