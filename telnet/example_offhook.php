#!/usr/bin/php
<?php

if(!($host=@$argv[1])){
        die("Usage: beep host\n");
}

require_once('../telnet/GrandstreamAudioControl.class.php');

$g=new GrandstreamAudioControl($host);
$g->path('handsfree');

print "Generating Pulses \n";
// $t=array(5, 180000, 100000);
$g->pulse();

sleep(2);

print "Generating volume sweep \n";
// $timings=array(3, 15000, 2, 5, 30);
// $timings=array(3, 3000, 1, 5, 30);
$g->sweep();

?>
