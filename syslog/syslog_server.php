#!/usr/bin/php -q
<?php
/*
 * @desc   Grandstream SYSLOG server/interpreter
 * @author Leo Brown
 * @date   2008-08-18
 */

// dependencies
require_once('grandstream_syslog_decoder.php');
if(!class_exists('XmlWriter')) require_once('XmlWriter.class.php');

// show help
if (in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
	die("Usage: syslog_server [bind_ip] [xml|csv|url|php] [on_hook,off_hook...] <packet_count>\n");
}

if(!($address=@$argv[1])){
	$address='0.0.0.0';
}

// format
if(!$format=$argv[2]){
	$format='csv';
}

$types       = @$argv[3];
$max_packets = @$argv[4];

// establish socket
$s=new GrandstreamSYSLOG();
$s->bind($address);

// prepare XML
if('xml'==$format){
	$xml = new XmlWriter();
	$xml->push('messages');
}

// messages to listen for
$messages=array_filter(explode(',',$types));

// start listening on callback
$s->listen('printPacket',$max_packets,$messages);

function printPacket($result){

	global $format, $xml;

	switch($format){

		case 'php':
		var_export($result);
		break;

		case 'csv':
		$line=date('Y-m-d').','.date('H:i:s').','.$result['ip'].','.$result['message']['type'].',';
		unset($result['message']['type']);
		unset($result['message']['packet']);
		foreach($result['message'] as $field=>$value){
			$line.="$value,";
		}
		print substr($line,0,-1)."\n";
		break;

		case 'url':
		$line='date='.date('Y-m-d').'&';
		$line.='time='.date('H:i:s').'&';
		$line.="host={$result['ip']}&";
		$line.="type={$result['message']['type']}&";
		unset($result['message']['type']);
		unset($result['message']['packet']);
		foreach($result['message'] as $field=>$value){
			$line.="$field=$value&";
		}
		print substr($line,0,-1)."\n";
		break;

		case 'xml':
			$xml->push('syslog_message');
			$xml->element('ip', $result['ip']);
			$xml->element('mac', $result['mac']);
			$xml->element('firmware', $result['firmware']);
			$xml->push('message');
			foreach($result['message'] as $field=>$value) {
				if($field=='packet'){
					$xml->cdataElement($field, $value);
				}
				elseif($field && $value) $xml->element($field, $value);
			}
			$xml->pop();
			$xml->pop();
		break;

	}
}

// finalise output
if('xml'==$format){
	$xml->pop('messages');
	print $xml->getXml();
}
?>
