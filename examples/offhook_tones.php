#!/usr/bin/php -q
<?php
/*
 * @desc   Interpret Grandstream SYSLOG messages and dispatch tones
 * @author Leo Brown
 * @date   2008-08-19
 */

// dependencies
require_once('../syslog/grandstream_syslog_decoder.php');
if(!class_exists('XmlWriter')) require_once('../syslog/XmlWriter.class.php');
require_once('../telnet/GrandstreamAudioControl.class.php');

// establish socket
$s=new GrandstreamSYSLOG();
$s->bind($address=@$argv[1]);

// messages to listen for
$messages=array(
	'off_hook',
	'callmode_change'
);

// start listening on callback
$s->listen('playTones',null,$messages);

$device_states = array();
function playTones($result){

	global $device_states;

	$ip=$result['ip'];
	switch($result['message']['type']){
		case 'callmode_change':
			$device_states[$ip]=$result['message']['new_mode'];
		break;

		// GXP unfortunately sends callmode/voicemode/audio path changes AFTER
		// off-hook for handset. Handsfree and headset set mode FIRST.
		// Approach is to assume handset
		case 'off_hook':
			switch($device_states[$ip]){
				case 'CALLMODE_SPEAKERPHONE':	$path='handsfree';	break;
				default:			$path='handset';	break;
			}
			$g=new GrandstreamAudioControl($ip,'admin',$path);
			$g->pulse();

		break;
	}

}
?>
