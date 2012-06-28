#!/usr/bin/php
<?php

// plays two double beeps - similar to Nokia 'new message' tone

if(!($host=@$argv[1])){
        die("Usage: beep host\n");
}

require_once('../telnet/GrandstreamAudioControl.class.php');

$g=new GrandstreamAudioControl($host);
$g->path('handsfree');

$g->play($f=1500, $vol=1, $len=200, $path=null);
usleep(20000);
$g->play($f=1500, $vol=1, $len=200, $path=null);

usleep(700000);

$g->play($f=1500, $vol=1, $len=200, $path=null);
usleep(20000);
$g->play($f=1500, $vol=1, $len=200, $path=null);
sleep(1);

?>
