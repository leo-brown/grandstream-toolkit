#!/usr/bin/php
<?php

if(!($host=@$argv[1]) || !($type=@$argv[2])){
	die("Usage: notify host type\n");
}

switch($type){
	case 'screen': $event='x-gs-screen'; break;
}

$msg=
"NOTIFY sip:anyone SIP/2.0
From: \"Grandstream Toolkit\"
Via: SIP/2.0/UDP
To: \"Grandstream Device\"
Call-ID: dummy@grandstream
CSeq: 102 NOTIFY
User-Agent: Grandstream Toolkit
Event: {$event}
Content-Length: 0";

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_sendto($sock, $msg, strlen($msg), 0, $host, 5060);
socket_close($sock);

?>
