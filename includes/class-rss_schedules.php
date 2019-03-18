<?php

class RSS_Poster_Schedules {
	
	
	//-------------------------------------------------------------------------------
	function __construct() {
	}
	//-------------------------------------------------------------------------------	
	function __destruct() {
		
	}
	
	//------------------------------------------
	public function cron_add_1hour($schedules) {
		$schedules['Q1H'] = array( 'interval'=> 3600, 'display'=>  __('Once per Hour'));
		// $schedules['Q1H'] = array( 'interval'=> 660, 'display'=>  __('Once per Hour'));
		return $schedules;
	}
	//------------------------------------------
	public function cron_add_2hour($schedules) {
	   // Adds once weekly to the existing schedules.
	   $schedules['Q2H'] = array( 'interval'=> 7200, 'display'=>  __('Once Every 2 Hours'));
	   return $schedules;
	}
	//------------------------------------------
	public function cron_add_3hour($schedules) {
	   // Adds once weekly to the existing schedules.
	   $schedules['Q3H'] = array( 'interval'=> 10800, 'display'=>  __('Once Every 3 Hours'));
	   return $schedules;
	}
	//------------------------------------------
	public function cron_add_4hour($schedules) {
	   // Adds once weekly to the existing schedules.
	   $schedules['Q4H'] = array( 'interval'=> 14400, 'display'=>  __('Once Every 4 Hours'));
	   return $schedules;
	}	
	//------------------------------------------
	public function cron_add_5hour($schedules) {
	   // Adds once weekly to the existing schedules.
	   $schedules['Q5H'] = array( 'interval'=> 18000, 'display'=>  __('Once Every 5 Hours'));
	   return $schedules;
	}	
}
?>