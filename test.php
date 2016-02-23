<?php
function getNum($action) {
	while (1) {
		$num = rand(1000, 9999);
		$stored = $action->send($num);
		if ($stored > 500) {
			break;
		}
	}
}
function storage() {
	$r = 0;
	while (1) {
		$num = (yield $r);
		if ($num) {
			echo $num . "\n";
			file_put_contents("./car.txt", $num . "\n", FILE_APPEND);
			$r++;
		}
	}
}
$S = storage();
getNum($S);