<?php
require_once('GrandstreamControl.class.php');

Class GrandstreamAudioControl extends GrandstreamControl{

	function pulse($timings=array(7, 80000, 50000)){
		if($this->ready){
			for($n=0;$n<$timings[0];$n++){
				$this->resetPath();
				usleep($timings[1]);
				$this->path('null');
				usleep($timings[2]);
			}
		}
		$this->resetPath();
	}

	function sweep($timings=array(3, 3000, 1, 5, 30)){

		if(!$this->ready) return;

		for($c=0;$c<$timings[0];$c++){
			for($n=$timings[3];$n<$timings[4];$n+=$timings[2]){
				$this->volume($n);
				usleep($timings[1]);
			}
			for($n=$timings[4];$n>$timings[3];$n-=$timings[2]){
				$this->volume($n);
				usleep($timings[1]);
			}

			// pause if set
			if($v=@$timings[5]){
				$this->volume(-60);
				usleep($v);
			}
		}

		// restore volume nicely from low to default
		for($r=$n;$n<=25;$n+=$timings[2]){
			$this->volume($n);
			usleep($timings[1]);
		}
	}

}

?>
