#!/usr/bin/php -q
<?php
/*
 * @desc   Interpret Grandstream SYSLOG onhook/powerup and request it picks up a new idle screen
 * @author Leo Brown
 * @date   2009-02-21
 */

// dependencies
require_once('../syslog/grandstream_syslog_decoder.php');
require_once('../telnet/GrandstreamAudioControl.class.php');

// establish socket
$s=new GrandstreamSYSLOG();
if(!$s->bind($address=@$argv[1])) exit();

// tapping driver_start, currently only for syslog, but could be reviewed
$messages=array(
	'on_hook',
	'driver_start'
);

// start listening on callback
$s->listen('notify',null,$messages);

$device_states = array();
function notify($result){
	$argv[1]=$result['ip'];
	$argv[2]='screen';
	require '../sip/notify.php';
}
?>
