#!/usr/bin/php
<?php

if(!($host=@$argv[1]) || !($text=@$argv[2])){
        die("Usage: message host text\n");
}

$msg="MESSAGE sip:grandstream_device SIP/2.0
Via: SIP/2.0/UDP
Max-Forwards: 70
From: sip:user1@domain.com;tag=49394
To: sip:grandstream_device
Call-ID: asd88asd77a@1.2.3.4
CSeq: 1 MESSAGE
Content-Type: text/plain
Content-Length: ".strlen($text)."


{$text}";

$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_sendto($sock, $msg, strlen($msg), 0, $host, 5060);
socket_close($sock);

?>
