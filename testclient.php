<?php

while (true) {
	$connection = stream_socket_client("tcp://0.0.0.0:8999", $errno, $errstr, 30);

	$msg = "00666001068178603100319909323230313030";
	$msg1 = "201000100000|070000|0|14091234711160|104110154114660|01047325|000058|20140829102633|";
	$packet = pack('H*', $msg) . $msg1;
	printf("Writing to Socket");

	sleep(10); //模拟阻塞
	$res = fwrite($connection, "{$packet}\r\n");
	$buffer = fread($connection, 4048);
	printf("Response was: {$buffer}\r\n");

	printf("Done Reading from Socket");
	fclose($connection);
}
