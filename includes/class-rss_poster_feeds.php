<?php
class Rss_poster_feeds {
	protected $feed_fn = array();
	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	 
	public function __construct() {
		
		$files = glob(plugin_dir_path( dirname( __FILE__ ) ) . '/streams/*.xml');
		
		foreach($files as $fn) {
			array_push($this->feed_fn, $fn);
			error_log($fn);
		}				
	}
	
	//-------------------------------------------------------------------------------	
	function __destruct() {
		
	}
	
	public static function get_feed() {
		return("Bollocks");
	}
	
}

?>