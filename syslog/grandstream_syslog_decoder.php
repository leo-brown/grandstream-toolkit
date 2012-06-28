<?php

/*
 * @desc   Decodes a Grandstream SYSLOG stream
 * @author Leo Brown
 * @date   2008-08-18
 */
Class GrandstreamSYSLOG{

/*
 * @desc   Binds as a SYSLOG server for Grandstream devices
 * @author Leo Brown
 * @date   2008-08-19
 * @param  $address Address to bind to
 * @return Array boolean True on successful bind
 */
function bind($address){
	if(!function_exists('socket_create')) die("No socket support.\n");
	$this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	return !!@socket_bind($this->socket, $address, 514);
}

/*
 * @desc   Listen with callback
 * @author Leo Brown
 * @date   2008-08-19
 * @param  $callback mixed 
 * @param  $max_packets int Optional maximum packet count
 * @param  $message_types array Optional message types to filter for
 */
function listen($callback,$max_packets=0,$message_types=array()){
	set_time_limit (0);
	$current_packet=1;
	while(
	socket_recvfrom($this->socket, $buffer, 4096, 0, $host, $port) &&
        (!$max_packets || $current_packet <= $max_packets)
	){
		$result = $this->decodePacket($buffer, $host, $port);
		if(!$message_types || in_array($result['message']['type'],$message_types)){
			call_user_func(
				$callback,
				$result
			);
			$current_packet++;
		}
	}
}

/*
 * @desc   Decodes a Grandstream SYSLOG packet
 * @author Leo Brown
 * @date   2008-08-18
 * @param  $data Packet that was received
 * @param  $ip   IP the packet came from
 * @param  $port Port the packet was sent from
 * @return Array String array of packet contents
 */
function decodePacket($data,$ip,$port){

	if('GS_LOG'!=substr($data,4,6)) return $data;

	$result=array(
		'mac'          =>substr($data,13,17),
		'ip'           =>$ip,
		'port'         =>$port,
		'firmware'     =>$this->formatFirmware(substr($data,43,8)),
		'syslog_level' =>substr($data,1,2),
		'message'      =>$this->decodePacketMessage(substr($data,53)),
	);

	return $result;
}

/*
 * @desc   Decodes a SIP packet which is double-space line separated
 * @author Leo Brown
 * @date   2008-08-18
 * @param  $packet string SIP Packet to reassemble
 * @return String SIP packet, line separated
 */
function prepareSIP($packet){

	return $packet;

	/*
	// get method
	$start=strpos($packet,'  ');
	$method=substr($packet,0,$start);
	$packet=substr($packet,$start);

	// get SIP lines
	$packet=trim($packet);
	$matches=array();
	preg_match_all('([A-Za-z\-]]+: [A-Za-z0-9. ]+)  ',$packet,$matches);

	// rejoin
	$lines=$matches[0];
	array_unshift($lines,$method);
	return implode("\r\n",$lines);
	*/
}

/*
 * @desc   Decodes a Grandstream SYSLOG packet message payload
 * @author Leo Brown
 * @date   2008-08-18
 * @param  $message Message to decode
 * @return Array String array of message data
 */
function decodePacketMessage($message){

	$headers = $this->stripMessagePrefixes($message);

	// Send SIP message: 200 To 10.10.10.10:5060, sip_handle: 0x0047F0AA
	if(strpos($message,'Send SIP')===0) return array(
			'type'=>'sip_transmit',
			'code'=>$this->textMatch($message,'([0-9]+) To'),
			'host'=>$this->textMatch($message,'([0-9]+.[0-9]+.[0-9]+.[0-9]+)'),
			'port'=>$this->textMatch($message,'[0-9]:([0-9]+), '),
			'handle'=>substr($message,strpos($message,': ',18)+2)
	);
	// sip_len: 719, sip_handle: 0x0047F12A, REGISTER sip:blah.org .....
	if(strpos($message,'sip_len')===0) return array(
			'type'=>'sip_transmit_packet',
			'length'=>$this->textMatch($message,'sip_len: ([0-9]+),'),
			'handle'=>$this->textMatch($message,'sip_handle: ([^,]+),'),
			'packet'=>$this->prepareSIP($this->textMatch($message,'sip_handle: [^,]+, ([^$]+)'))
	);
	// Received SIP message: 200
	elseif(strpos($message,'Received SIP')===0) return array(
			'type'=>'sip_receive',
			'code'=>$this->textMatch($message,': ([0-9]+)')
	);
	// SIPReceive(567, Account5): SIP/2.0 200 OK  Via: SIP/2.0/UDP 
	elseif(strpos($message,'SIPReceive')===0) return array(
			'type'=>'sip_receive_packet',
			'length'=>$this->textMatch($message,'([0-9]+), Account'),
			'account'=>$this->textMatch($message,'Account([0-9])'),
			'packet'=>$this->prepareSIP($this->textMatch($message,': ([^$]+)$'))
	);
	// RTP session starts. Channel: 0 Local RTP port: 5004 Remote RTP endpoint: 10.10.10.10:15888
	if(strpos($message,'RTP session start')===0) return array(
			'type'=>'rtp_begin',
			'channel'  =>$this->textMatch($message,'Channel: ([0-9]+) '),
			'rtp_port' =>$this->textMatch($message,'RTP port: ([0-9]+) '),
			'endpoint' =>$this->textMatch($message,'([0-9]+.[0-9]+.[0-9]+.[0-9]+:[0-9])+')
	);
	// Start RTP Keep-alive: Channel 0 lport 0 account 0 stun 0 keep_alive 1 interval 20
	if(strpos($message,'Start RTP Keep-alive')===0) return array(
			'type'=>'rtp_keepalive_start',
			'channel'  =>$this->textMatch($message,'Channel ([0-9]+) '),
			'local_port' =>$this->textMatch($message,'lport ([0-9]+) '),
			'account'         =>$this->textMatch($message,'account ([0-9]+) '),
			'stun_index'      =>$this->textMatch($message,'stun ([0-9]+) '),
			'keepalive_count' =>$this->textMatch($message,'keep_alive ([0-9]+) '),
			'interval'        =>$this->textMatch($message,'interval ([0-9]+)')
	);
	// Stop RTP Keep-alive: Channel 0 lport 0
	if(strpos($message,'Stop RTP Keep-alive')===0) return array(
			'type'=>'rtp_keepalive_start',
			'channel'  =>$this->textMatch($message,'Channel ([0-9]+) '),
			'local_port' =>$this->textMatch($message,'lport ([0-9]+) ')
	);
	// sending notifies from [when ringing received / sip bye 200ok]
	elseif(strpos($message,'sending notifies')===0) return array(
			'type'=>'notify_send',
			'notify_type'=>$this->textMatch($message,'notifies from ([^$]+)')
	);
	// Session Info: Payload-Type=8, Frames/Packet=2, DTMF=0
	elseif(strpos($message,'Session Info: Payload-Type=8')===0) return array(
			'type'=>'send_dtmf',
			'dtmf'=>$this->textMatch($message,'DTMF=([0-9]+)')
	);
	// SIP dialog matched to channel 16
	elseif(strpos($message,'SIP dialog matched')===0) return array(
			'type'=>'sip_matched',
			'channel'=>$this->textMatch($message,'channel ([0-9]+)')
	);
	// SIP dialog not matched
	elseif(strpos($message,'SIP dialog not matched')===0) return array(
			'type'=>'sip_unmatched'
	);
	// New DHCP IP address: 1.2.3.4
	elseif(strpos($message,'New DHCP')===0) return array(
			'type'=>'network_lease',
			'ip'=>$this->textMatch($message,'([0-9]+.[0-9]+.[0-9]+.[0-9]+)')
	);
	// SIP_REGISTER: DNS A resolved to 1 records.  Record_1=1.2.3.4;
	elseif(strpos($message,'SIP_REGISTER: DNS')===0) return array(
			'type'=>'network_name',
			'record_type'=>$this->textMatch($message,'DNS ([A-Za-z]+) resolved'),
			'records_returned'=>$this->textMatch($message,'to ([0-9]+) records'),
			'ips'=>array($this->textMatch($message,'Record_1=([0-9]+.[0-9]+.[0-9]+.[0-9]+);'))
	);
	// RFC3581 Symmetric Routing: rport=5062, received=10.10.10.101
	elseif(strpos($message,'RFC3581 Symmetric Routing')===0) return array(
			'type'=>'network_route',
			'receive_port'=>$this->textMatch($message,'rport=([0-9]+),'),
			'this_ip'=>$this->textMatch($message,'([0-9]+.[0-9]+.[0-9]+.[0-9]+)')
	);
	//Local time updated via NTP, next NTP sync in 3600 seconds
	elseif(strpos($message,'Local time')===0) return array(
			'type'=>'network_time_update',
			'timeout'=>$this->textMatch($message,'([0-9]+) seconds')
	);
	//BLF: Setting MFK1 () status= DIALOG_STATE_CONFIRMED
	elseif(strstr($message,'DIALOG_STATE_CONFIRMED')!==FALSE) return array(
			'type'=>'blf_on',
			'button'=>$this->textMatch($message,'MFK([0-9]+) ')
	);
	//BLF: Setting MFK1 () status= DIALOG_STATE_TERMINATED
	elseif(strstr($message,'DIALOG_STATE_TERMINATED')!==FALSE) return array(
			'type'=>'blf_off',
			'button'=>$this->textMatch($message,'MFK([0-9]+) ')
	);
	//Grandstream GXP2020 gxp2020e.bin:1.1.6.16 boot55e.bin:1.1.6.5
	elseif(strpos($message,'Grandstream')===0) return array(
			'type'=>'boot_verify',
			'model'=>$this->textMatch($message,'Grandstream ([^ ]+)'),
			'firmware_file'=>$this->textMatch($message,'Grandstream [^ ]+ ([^:]+):'),
			'firmware_version'=>$this->textMatch($message,':([0-9]+.[0-9]+.[0-9]+.[0-9]+) '),
			'boot_file'=>$this->textMatch($message,' ([^:]+):[0-9]+.[0-9]+.[0-9]+.[0-9]+$'),
			'boot_version'=>$this->textMatch($message,'([0-9]+.[0-9]+.[0-9]+.[0-9]+)$')
	);
	// Starting driver syslog...
	elseif(strpos($message,'Starting driver')===0) return array(
			'type'=>'driver_start',
			'driver'=>$this->textMatch($message,'Starting driver ([^\.]+)')
	);
	// Provision attempt 1
	elseif(strpos($message,'Provision attempt')===0) return array(
			'type'=>'provision_attempt',
			'attempt_count'=>$this->textMatch($message,'([0-9]+)$')
	);
	// TFTP Option Parser: blkSize=1024 timeout=4 tsize=65532 grandstream_NAT=49339
	elseif(strpos($message,'TFTP Option Parser')===0) return array(
			'type'      =>'provision_fileoption',
			'block_size'=>$this->textMatch($message,'blkSize=([0-9]+) '),
			'timeout'   =>$this->textMatch($message,'timeout([0-9]+) '),
			'nat_port'  =>$this->textMatch($message,'grandstream_NAT=([0-9]+)'),
			'total_size'=>$this->textMatch($message,'tsize=([0-9]+)')
	);
	// Packet Dropped During Provision: <packet>
	elseif(strpos($message,'Packet Dropped During Provision')===0) return array(
			'type'=>'provision_packetreject',
			'packet'=>trim($this->textMatch($message,': ([^$]+)'))
	);
	// File Not Found: blah.bin
	elseif(strpos($message,'File Not Found')===0) return array(
			'type'=>'provision_fileerror',
			'file_name'=>trim($this->textMatch($message,': ([A-Za-z0-9.]*)'))
	);
	// Abort: Same as stored GET blah.bin
	elseif(strpos($message,'Abort: Same as stored')===0) return array(
			'type'=>'provision_filepreseved',
			'file_name'=>trim($this->textMatch($message,'GET ([A-Za-z0-9.]+)'))
	);
	// AccountName:REGISTERED for 3600 seconds;re-REGISTER in 3580 seconds
	elseif(strstr($message,'REGISTERED for')!==FALSE) return array(
			'type'=>'sip_registered',
			'account'=>$this->textMatch($message,'([^:]+):REGISTERED'),
			'interval'=>$this->textMatch($message,'for ([0-9]+) seconds'),
			'timeout'=>$this->textMatch($message,'in ([0-9]+) seconds')
	);
	// account.name SIP registration failed.  Retrying in 20 seconds. Server: 10.0.0.150
	elseif(strstr($message,'SIP registration failed')!==FALSE) return array(
			'type'=>'sip_register_fail',
			'account'=>$this->textMatch($message,'([A-Za-z0-9.]+) SIP registration'),
			'server'=>$this->textMatch($message,'Server: ([^$]+)'),
			'timeout'=>$this->textMatch($message,'in ([0-9]+) seconds')
	);
	// LCD Callmode: CALLMODE_NULL
	elseif(strstr($message,'LCD Callmode')!==FALSE) return array(
			'type'=>'callmode_change',
			'new_mode'=>$this->textMatch($message,'LCD Callmode: ([^$]+)$')
	);
	// Voc mode (0): CALLMODE_SPEAKERPHONE
	elseif(strstr($message,'Voc mode')!==FALSE) return array(
			'type'=>'voicemode_change',
			'node'=>$this->textMatch($message,'\(([0-9]+)\)'),
			'voice_mode'=>$this->textMatch($message,': ([^$]+)$')
	);
	// Aud path (0): AUD_PATH_HANDSET
	elseif(strstr($message,'Aud path')!==FALSE) return array(
			'type'=>'path_change',
			'node'=>$this->textMatch($message,'\(([0-9]+)\)'),
			'new_path'=>$this->textMatch($message,': ([^$]+)$')
	);
	// Tone start (0): 66
	elseif(strstr($message,'Tone start')!==FALSE) return array(
			'type'=>'tone_start',
			'node'=>$this->textMatch($message,'\(([0-9]+)\)'),
			'current_tone'=>$this->textMatch($message,': ([0-9]+)$')
	);
	// Tone stop (0)
	elseif(strstr($message,'Tone stop')!==FALSE) return array(
			'type'=>'tone_stop',
			'node'=>$this->textMatch($message,'\(([0-9]+)\)')
	);
	// [Status]-ON HOOK
	elseif(strstr($message,'[Status]-ON HOOK')!==FALSE) return array(
			'type'=>'on_hook'
	);
	// [Status]-ON HOOK
	elseif(strstr($message,'[Status]-OFF HOOK')!==FALSE) return array(
			'type'=>'off_hook'
	);

	// XML_APP / IdleScreen: Invalid Font detected. Setting font to f13h
	elseif(strstr($message,'XML_APP / IdleScreen: Invalid Font')!==FALSE) return array(
			'type'=>'invalid_font',
			'new_font'=>$this->textMatch($message,'([0-9][0-9][a-z])')
	);

	// DNS response received.
	elseif(strstr($message,'DNS response received')!==FALSE) return array(
			'type'=>'dns_received'
	);

	// Record found and about to process.
	elseif(strstr($message,'Record found and about to process')!==FALSE) return array(
			'type'=>'dns_found'
	);

	// Start processing DNS response message.
	elseif(strstr($message,'Start processing DNS')!==FALSE) return array(
			'type'=>'dns_processing'
	);

	// SUCCESSFUL. result_ptr and callback are not NULL; call callback function.
	elseif(strstr($message,'SUCCESSFUL. result_ptr')!==FALSE) return array(
			'type'=>'dns_correct'
	);

	else return array(
		'type'=>'unknown',
		'message'=>$message
	);
}


/*
 * @desc   Strips and returns Grandstream SYSLOG packet extra data
 * @author Leo Brown
 * @date   2008-08-18
 * @param  &$message Message to decode
 * @return Array Extra data returned
 */
function stripMessagePrefixes(&$message){

	$result = array();

	if($mallocstat=$this->textMatch($message,'\(([0-9]+\/[0-9]+\/[0-9]+)\)')){
		$message=substr($message,strlen($mallocstat)+3);
		$result['malloc']=$mallocstat;
	}
	if($datetime=$this->textMatch($message,'([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})')){
		$message=substr($message,strlen($datetime));
		$result['date']=$datetime;
	}

	return $result;
}

function textMatch($text, $pattern){
	$matches=array();
	$result = preg_match("/$pattern/",$text,$matches);
	if($result === FALSE ){
		print_r("failed to match '$pattern'");
	}
	return $matches[1];
}

function textCut($text, $from, $to){
	$matches=array();
	preg_match("/{$from}(.*){$to}/",$text,$matches);
	$match=reset($matches);
	return $match[0];
}

/*
 * @desc   Reads firmware format in 0x00000000 and translates to A.B.C.D
 * @author Leo Brown
 * @date   2008-08-18
 * @param  $firmcode Firmware revision in 0x00000000 format
 * @return Firmware in dotted decimal notation
 */
function formatFirmware($firmcode){
	$parts=array();
	for($n=0;$n<=6;$n+=2){
		$parts[]=substr($firmcode,$n,2);
	}
	foreach($parts as $i=>$part){
		$parts[$i]=hexdec($part);
	}
	return implode('.',$parts);
}

}
?>
