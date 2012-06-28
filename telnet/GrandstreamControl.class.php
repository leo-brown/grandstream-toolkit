<?php

/*
 * @desc   Grandstream device control class using 'telnet' connection
 * @author Leo Brown
 * @date   2008-08-17
 */
Class GrandstreamControl{

	// device status
	var $socket;
	var $ready=false;

	var $model;

	// device delays
	var $delays=array(
		'end'=>15000,
		'default'=>2000
	);

	// device default path
	var $defaultPath='handsfree';

	/*
	 * @desc   Grandstream Control Constructor (PHP4 valid)
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  $host Device to connect to
	 * @param  $pass Password to authenticate with
	 * @param  $path Default audio path (handsfree, headset, handset)
	 */
	function GrandstreamControl($host,$pass='admin',$path=null){
		$this->socket = @fsockopen($host, 23, $errno, $errstr, 3);
		if ($this->socket) {
			$this->model = $this->getModel(fgets($this->socket, 128));
			$this->login($pass);
		}
		if($path) $this->defaultPath=$path;
	}

	/*
	 * @desc   Get device Model Name
	 * @author Leo Brown
	 * @date   2009-03-02
	 * @param  $model String as "Grandstream GXP2020 Command Shell"
	 */
	function getModel($model){
		$matches=array();
		preg_match('/ (GXP[0-9]+) /', $model, @$matches);
		return $matches[1];
	}

	/*
	 * @desc   Login to Grandstream device
	 * @author Leo Brown
	 * @date   2009-03-02
	 * @param  $pass Password to authenticate with
	 */
	function login($pass='admin'){
		$result = $this->send($pass, 4);
		$this->ready=true;
	}


	/*
	 * @desc   Grandstream command send mechanism
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  Data to send to device
	 * @param  How much data is required from device, in lines,
	 *         or expected response termination string, if string.
	 */
	function send($data,$return=false){

		fwrite($this->socket, $data."\r\n");

		// delay
		$callers=debug_backtrace();
		$caller=$callers[1]['function'];

		if(array_key_exists($caller, $this->delays)){
			usleep($this->delays[$caller]);
		}
		else{
			usleep($this->delays['default']);
		}

		$result='';
		for($r=0;$r<$return;$r++){
			$result[] = fgets($this->socket, '128');
		}

		return $result;

	}

	/*
	 * @desc   Leaves the current mode
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @todo   Detect current mode, and exit all menu levels but do
	 *         not leave interface
	 */
	function end(){
		$this->send('e');
	}

	// takes about 2s per 4kB, end can be up to 2048
	function getMemory($start=0, $end=5){

		// need to offset a single line - "GXP2020>", pending review
		$capture=513;

		// get 8MB as 2048 blocks of 4096 bytes - largest dump allowed
		$memory=array();
		for($n=$start;$n<$end;$n++){

			$r = $this->send('d '.($n*2048).' 4096', $capture);
			array_shift($r);
			foreach($r as $line){
				$memory .= $this->decodeMemory($line);
			}

			// Normal capture size is 4096 bytes / 16 bytes per line
			$capture = 512;

		}

		return $memory;

	}

	/*
	 * @desc   Decode a number of double byte entries to a binary string
	 * @author Leo Brown
	 * @date   2009-03-02
	 * @param  $line Line as "00000F78: 0E00 503D 0522 5940 2990 807D FFFF 1924"
	 */
	function decodeMemory($line){

		$matches=array();
		preg_match_all('/ ([0-9A-F]+)/', $line, @$matches);
		$bytes=implode($matches[1]);

		$binary = '';
		for($b=0;$b<strlen($bytes);$b+=2){
			$binary .= pack('H',
				substr($bytes,$b,2)
			);
		}
		return $binary;

	}

	/*
	 * @desc   Switch device mode (audio, amplifier, etc)
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  $mode Mode to switch to
	 */
	function mode($mode=''){
		switch($mode){
			case 'audio':     $o='a'; break;
			case 'amplifier': $o='c'; break;
		}
		$this->send($o);
	}

	/*
	 * @desc   Reset audio path to device default (set at instantiation)
	 * @author Leo Brown
	 * @date   2008-08-17
	 */
	function resetPath(){
		$this->path($this->defaultPath);
	}

	/*
	 * @desc   Switch the active audio path on the device
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  $path Audio path to use on the device
	 */
	function path($path=null){
		$this->mode('audio');
		switch($path){
			case 'handset':   $o='s'; break;
			case 'handsfree': $o='f'; break;
			case 'headset':   $o='d'; break;
			default:          $o='n'; break;
		}
		$this->send('p '.$o);
		$this->end();
	}

	/*
	 * @desc   Change device volumne
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  $vol New device volume -60 is mute and 25 is normal for handsfree
	 */
	function volume($vol=25){
		$this->mode('amplifier');
		$this->send('1c '.$vol);
		$this->end();
	}

	/*
	 * @desc   Play a tone through the device
	 * @author Leo Brown
	 * @date   2008-08-17
	 * @param  $tone Frequency of tone to play
	 * @param  $vol  Gain on the tone amplification
	 * @param  $len  Duration of tone in milliseconds
	 * @param  $path Path to set, if not to use default
	 */
	function play($tone, $vol=5, $len=100, $path=null){
		if($path) $this->path($path);
		$this->mode('audio');
		$this->send("t 1 $vol $tone");
		usleep($len*1000);
		$this->send('t 0');
		$this->end();
	}

	/*
	 * @desc   Close connection with the device on deconstruction
	 * @author Leo Brown
	 * @date   2008-08-17
	 */
	function __deconstruct(){
		$this->end();
		fclose($this->socket);
	}

}

?>
