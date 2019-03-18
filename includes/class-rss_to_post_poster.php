<?php
// for production testing require the next line
//require plugin_dir_path( __FILE__ ) . "../../../wp-load.php";
//define('WP_DEBUG', false);
//wp-includes/kses.php

class RSS_To_Post_Poster {
	 	
	private $params = array();
	private $plugin_path = ""; 
	private $img_dir = ""; 
	private $release_dir = ""; 
	private $debug = false;
	private $gen_file = false;
	private $lock_fn = "lock";
	private $testing = false;		// set to false for production
	private $check_fn = "";			// file name to check the last release
	private $title = "";			// current release title used and stored in last_release directory
	private $node_types = array ("element", "attribute", "text", "namespace", "processing-instruction", "comment", "document-nodes");
	private $rss_feeds = array();
	
	//-------------------------------------------------------------------------------
	function __construct($gen_file = false, $testing = false) {
		
		$this->gen_file = $gen_file;
		$this->testing = $testing;
	// Production
		if ($this->testing === false) {
			//$this->plugin_path = ABSPATH . 'wp-content/rss-to-post';
			$this->plugin_path = plugin_dir_path( __DIR__ );
			$this->plugin_url = plugin_dir_url("rss-to-post.php"). "rss_poster/";
			$this->img_dir = $this->plugin_path. "img/";
			$this->release_dir = $this->plugin_path. "last_release/";
		} else {
	// Testing
			$this->plugin_path = "../";
			$this->plugin_url = "../";
			$this->img_dir =  $this->plugin_path. "img/";
			$this->release_dir = $this->plugin_path. "last_release/";
		}
	}

	//-------------------------------------------------------------------------------	
	function __destruct() {
		
	}
	
	//-------------------------------------------------------------------------------	
	private function run_frequency($fn) {
		$ob = simplexml_load_file($fn);
		$json = json_encode($ob);
		$params = json_decode($json, true);
		$result = array();
		// Flattens the multidimentional associative array adds a digit to duplicates!
		// array_walk_recursive($this->params, function($v, $k) use (&$result){ static $i = 2; isset($result[$k]) ? $result[$k.$i] = $v : $result[$k] = $v;});
		
		array_walk_recursive($params, function($v, $k) use (&$result){ $i = ""; for (; isset($result[$k."$i"]); $i++); $result[$k.$i] = $v; });

		if (isset($result["frequency"])) {
			error_log("Requested frequency: ". $result["frequency"]);
		} else {
			throw new Exception("ERR: undefined <frequency> tag");
		}
		$rf = new StdClass();
		$rf->fr = $result["frequency"];
		if (isset($result["start_offset"])) {
			$rf->st = time() + $result["start_offset"];
		} else {
			$rf->st = time();
		}

		return($rf);
	}
	
	//-------------------------------------------------------------------------------	
	public function set_jobs() {

		$files = glob(plugin_dir_path( dirname( __FILE__ ) ) . 'streams/*.xml');
		
		foreach($files as $fn) {
			try {
				$rf = $this->run_frequency($fn);
				//error_log(print_r($rf, true));
				$args = array();
				array_push($args, $fn);
			if (!wp_schedule_event($rf->st, $rf->fr, 'rss_poster_event', $args)) {
				error_log("Failed to set: event for: ". basename($fn));
			}
				error_log("Added: ". basename($fn));
			} catch (Exception $e) {
				error_log($fn. " Invalid XML detected or missing <frequency> tag");
			}
		}
	}
	
	//-------------------------------------------------------------------------------	
	public function rss_rip_event($fn) {
		//$this->write_log("rss_rip_event: ". basename($fn));
		if ($fn == "") { $this->write_log("fn is an empty string!"); return; }
		
		$this->write_log("rss_rip_event: ". $fn);
		//error_log("rss_rip_event: ". basename($fn));
		$this->do_update($fn);
	}
	//-------------------------------------------------------------------------------	
	public function clear_jobs() {

		$this->write_log("clear_jobs");
		
		$files = glob(plugin_dir_path( dirname( __FILE__ ) ) . 'streams/*.xml');
		foreach($files as $fn) {
			$this->write_log("Clearing job: ". basename($fn));
			$args = array();
			array_push($args, $fn);
			wp_clear_scheduled_hook('rss_poster_event', $args);	
			error_log("Cleared: ". $fn);
		}
	}
	
	//-------------------------------------------------------------------------------
	/*
	public function run_update() {
	
		//$this->write_log("Plugin path: ". $this->plugin_path);
	
		if (!file_exists($this->lock_fn)) touch($this->lock_fn);
		else return;
		
		$files = glob($this->plugin_path. "streams/*.xml");
		
		foreach($files as $fn) {
			try {
				$this->do_update($fn);
			} catch (Exception $e) {
				unlink($this->lock_fn);
				error_log("Notice: rss-to-post - ". $e->getmessage());
				//echo "ERR: class-rss-to-post-poster::run_update() - ". $e->getmessage(). "\n";
				//return;
			}
			error_log("-----------------------------------------------------------------------------------------");
		}				
		unlink($this->lock_fn);
	}
	*/
	//-------------------------------------------------------------------------------
	public function do_update($fn) {
															// load the XML describing the link and extract all the elements
		try {
			$this->write_log("Processing: $fn"); //return;
			$ob = @simplexml_load_file($fn);
			$json = json_encode($ob);
			$this->params = json_decode($json, true);
			$result = array();
			// Flattens the multidimentional associative array adds a digit to duplicates!
			// array_walk_recursive($this->params, function($v, $k) use (&$result){ static $i = 2; isset($result[$k]) ? $result[$k.$i] = $v : $result[$k] = $v;});
			
			array_walk_recursive($this->params, function($v, $k) use (&$result){ $i = ""; for (; isset($result[$k."$i"]); $i++); $result[$k.$i] = $v; });

			$this->params = $result;
			if (isset($this->params["options"])) {
				$this->params["img.options"] = array_map('strtolower', isset($this->params["options"]) ? explode(",", str_replace(' ', '', $this->params["options"])) : array());
			} else {
				$this->params["img.options"] = array("no_image");
			}
print_r($this->params["img.options"]);
			
			if (isset($this->params["debug"])) {
				$this->write_log("Found debug setting");
				$this->debug = (!strcmp(strtoupper($this->params["debug"]), "YES"));
			} 
			
			//$this->write_log(print_r($result, true));
			$this->write_log("\n". str_pad(" ". $this->params["name"]. " ", 100, "-", STR_PAD_BOTH));
			
																					// check bare minimal requirements for posting an article are met 
																					// url of RSS where to find a headline and an article 
			if (isset($this->params["rss_url"]) && $this->params["rss_url"] === "") {
				throw new Exception("No rss_url defined in XML file: ". $fn);
			}
			
			$this->write_log("Processing rss: ". $this->params["rss_url"]);
			$stream = $this->get_stream_tags($fn);
			$item = $this->get_stream_items($stream);

			if (!$this->article_posted($item["title"])) {
				$this->write_log("Processing link: ". $item["link"]);
				if (isset($this->params["mode"]) && !strcmp(strtolower($this->params["mode"]), "simple")) {
																						// simple update using only rss information
					$this->simple_update($stream, $item);

				} else if (isset($this->params["mode"]) && !strcmp(strtolower($this->params["mode"]), "rip")) { 
																						// deep update taking info off the article page
					$this->rip_update($stream, $item);
				} else throw new Exception("No/wrong operating mode specified in xml configuration file. Missing <mode>simple | rip</mode>");
			}
		} catch (Exception $e) {
			$this->write_log("Stream: ". $fn. " caused an exception!");
			$this->write_log($e->getMessage());
		}
		$this->write_log("\n". str_pad("-", 100, "-", STR_PAD_BOTH));
	}

	//--------------------------------------------------------------------------------------
	// Uses RSS content to rip out the whole article if requested
	//
	private function rip_update($stream, $item) {
	
		$this->write_log("RIP MODE SELECTED!");
//print_r($item); 
		
		if (strlen($item["link"])) {
			
			$xmlStory = new \DOMDocument(null, 'UTF-8');
// Test link			
			//$item["link"] = "http://www.khaosodenglish.com/life/entertainment/2018/10/16/review-a-star-is-born-is-dizzyingly-wonderful/";
			//$this->write_log("Loading: ". $item["link"]);
			if (($html = file_get_contents($item["link"])) == FALSE)
				throw new Exception("file_get_contents() - failed to get ". $item["link"]);
			
			@$xmlStory->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
			$title = $xmlStory->getElementsByTagName('title')->item(0)->nodeValue;
			
			$xpath = new \DOMXPath($xmlStory);												// Create xpath for serching various items
			$a = new StdClass();															// The article to post
			$a->post_category = array_map('strtolower', isset($this->params["category"]) ? explode(",", str_replace(' ', '', $this->params["category"])) : array());			
			$a->link 	 = $item["link"];
			$a->headline = $this->get_element("headline", $xpath, "getHTML");
			if (!strlen($a->headline)) {
				$this->write_log("Didn't manage to load a headline");
				return;
			}

			$a->pub_date = $this->get_element("date", $xpath, "nodeValue");
			$a->author 	 = $this->get_element("author", $xpath, "getHTML");
			$a->author = trim(str_replace(array('\n', '\r'), array('',''), $a->author));
			$a->excerpt  = $this->get_element("excerpt", $xpath, "textContent");
			$a->imgs 	 = $this->get_media($xpath, $a->link);
$this->write_log("IMAGES FOUND: ". count($a->imgs));			
			$a->featured_img_url = $this->assign_feature_img($a);							// set ths as the featured image
			$this->check_restricted_authors($a->author);									// thrown an exception if authors not compliant
			$a->para = $this->get_element("article", $xpath, "article_reader");

			if (!count($a->para)) {
				throw new Exception("rip_update() - Didn't manage to load the article");
			}
			$this->write_log("Article retrieved");
			$this->set_category_and_tags($a);
			$this->write_log("Set categoty & tags");
			$a->body = $this->layout_article($a, "rip");
			$this->write_log("Article layout completed");
			//print_r($a); exit;
			if (!$this->check_requirements($a)) {
				$this->write_log("Article requirements FAILED");
				return;
			}
			$this->write_log("Article requirements PASSED");
			$this->post_article($a);
			$this->write_log("Article posted");
			//$this->write_log("AUTHOR: $a->author");
		}
	}	

	//--------------------------------------------------------------------------------------
	// Mostly uses just the RSS content
	//
	private function simple_update($stream, $item) {
		
		$this->write_log("SIMPLE MODE SELECTED!");

		$a = new StdClass();
		$a->post_category = array_map('strtolower', isset($this->params["category"]) ? explode(",", str_replace(' ', '', $this->params["category"])) : array());			
		//$this->write_log(print_r($item, true));
		$a->link = $item["link"];
		//$this->write_log("Processing: ". $item["link"]);
		$a->headline = $item["title"];
		$a->pub_date = $item["pubDate"];
		$a->author = "";
		//$a->post_category = array_map('strtolower', isset($this->params["category"]) ? explode(",", str_replace(' ', '', $this->params["category"])) : array());
		$a->imgs = array();
		$xp = null;

		if (strlen($item["description"])) {
			if($item["description"] != strip_tags($item["description"])) {				// we have a description that is a DOM
				$this->write_log("NOTE: description is a DOM");
				$doc = new \DOMDocument();
				$doc->validateOnParse = true;
				$doc->loadHTML(mb_convert_encoding($item["description"], 'HTML-ENTITIES', 'UTF-8'));
				$xp = new \DOMXPath($doc);
				$n = $xp->query("/");													// the root we expect to be the text description

				if ($n != FALSE) {
					if (strlen($n->item(0)->textContent)) {
						$a->excerpt = $n->item(0)->textContent;
						$this->write_log("Text description found: ". $a->excerpt);						
					} else {
						$this->write_log("Text description NOT found");
					}
				}
			} else {																	// plain text description i.e. not a DOM
				$a->excerpt = $item["description"];
			}
			
			$a->imgs = $this->get_imgs($xp, $a->link, $item);
			$a->featured_img_url = $this->assign_feature_img($a);
/*			
			if ($img_count = count($a->imgs)) {
				$img_ndx = 0;
				$this->write_log("img count: ". $img_count);
				if (isset($this->params["use_image"]) && is_numeric($this->params["use_image"])) {
					$img_ndx = min(max($this->params["use_image"] - 1, 0), $img_count - 1);
				}
				$this->write_log("Using img_ndx: ". $img_ndx);
					
				$a->featured_img_url = $a->imgs[$img_ndx]->url;
			}
			else {
				$this->write_log("Warn: No feature image available.");
			}
*/
			$this->set_category_and_tags($a);
			$a->body = $this->layout_article($a, "simple");
			$this->write_log("Article layout completed");
			if (!$this->check_requirements($a)) {
				$this->write_log("Article requirements FAILED");
				return;
			}
			$this->write_log("Article requirements PASSED");
			
			//error_log(print_r($a, true));
			$this->post_article($a);
		}
	}
	
	//--------------------------------------------------------------------------------------
	// Process image options directives if there are any
	//	
	function assign_feature_img($a) {
		$img_url = "";
																								// randomly assign a featured image
		if ($img_count = count($a->imgs)) {
			$img_ndx = 0;
			$this->write_log("img count: ". $img_count);
			if (isset($this->params["use_image"]) && is_numeric($this->params["use_image"])) {	// if <use_image> is set then use that 
				$img_ndx = min(max($this->params["use_image"] - 1, 0), $img_count - 1);			// if there are less images than that 
																								// requested then use the last image
			} else {
				$this->write_log("Using img_ndx: ". $img_ndx);
				//$img_ndx = rand(0, img_count - 1);
			}
//$this->write_log("assign_feature_img() - using image index: ". $img_ndx);
			$img_url = $a->imgs[$img_ndx]->url;
		}
		else {
			$this->write_log("Warn: No image available to set as feature image assigning a stock image.");
			$c = array();
			foreach($a->post_category as $i) {
				if (strcmp(strtolower($i), "featured_random"))
					array_push($c, $i);
			}
//$this->write_log("Count: ". count($c));
			$ndx = max(0, rand(0, count($c) -1));
//$this->write_log("ndx: ". $ndx);
			$sc = $c[$ndx];
//$this->write_log("USING CATEGORY: ". $sc);
			$files = glob($this->img_dir. "stock/". $sc. "/*.jpg");
			if ($fc = count($files)) {
				$i = rand(0, $fc - 1);
//$this->write_log("Selecting stock image: ". $files[$i]);
				$img_url =	$this->plugin_url. "img/stock/". $sc. "/". basename($files[$i]);
			}
		}
//$this->write_log("assign_feature_img() - IS RETURNING: ". $img_url);
		return($img_url);
	}
	
	//--------------------------------------------------------------------------------------
	// Process image options directives if there are any
	//	
	private function get_imgs($xp, $link, $item) {
		$imgs = array();
		if (isset($this->params["img.options"])) {
			foreach($this->params["img.options"] as $o) {
				if (!strcmp($o, "search_rss")) {
					$imgs = array_merge($this->get_image_from_rss($item, $xp), $imgs);
					//$imgs = array_values($imgs + $this->get_image_from_rss($item, $xp));
					//$this->write_log("Found: ". count($imgs). " image/s");
				} else if (!strcmp($o, "search_article")) {
					$imgs = array_merge($this->get_image_from_article($link), $imgs, $this->image_scanner($link));
					//$imgs = array_values($imgs + $this->get_image_from_article($link) + $this->image_scanner($link));
				} else if (!strcmp($o, "no_image")) {
					// do nothing
				} else if (!strcmp($o, "no_post_without") && !sizeof($imgs)) {
					// don't post without an image
					$this->write_log("Warn: get_imgs() - Found not images and no_post_without is set.");
					
				}
			}
			//$this->write_log("Images found: ". count($imgs). " image/s\n". print_r($imgs, true));
			$this->write_log("Images found: ". count($imgs). " image/s");
			
		}
		return($imgs);
	}
	
	//--------------------------------------------------------------------------------
	// image_scanner - finds all images in a section of html
	//--------------------------------------------------------------------------------
	function image_scanner($link) {

		$imgs =  array();
		if (isset($this->params["image_scan"]) && strlen($this->params["image_scan"])) {
			if (($html = file_get_contents($link)) === FALSE)
				throw new Exception("file_get_contents() - failed to get ". $link);
		
			$doc = new \DOMDocument();
			@$doc->loadHTML($html);
			$xp = new \DOMXPath($doc);
			$nl = $xp->query($this->params["image_scan"]);							// start path
			$html = "";
			if (($nl != FALSE) && ($nl->length != 0)) {
				if ($nl->length == 0) 
						echo "Node with zero items was returned by: $sp\n";

				$newdoc = new DOMDocument;
				foreach($nl as $node)
				{
					$newnode = $newdoc->importNode($node, true);
					$newdoc->appendChild($newnode);
				}
				$html = $newdoc->saveHTML();

				echo "------------------------------------------------------------------\n";
				
				$d = new \DOMDocument();
				@$d->loadHTML($html);
				$tags = $d->getElementsByTagName('img');
				foreach ($tags as $t) {
					if (($url = $t->getAttribute('src')) && strlen($url)) {
						if ((($p = strpos($url, "jpg")) && ($n = strlen("jpg"))) || 
							(($p = strpos($url, "jpeg")) && ($n = strlen("jpeg")))) {
							$url = substr($url, 0, $p + $n);
							$img = new stdClass();
							$img->url = $url;
							echo $img->url. "\n";
							array_push($imgs, $img);
						}
					}
				}

				$tags = $d->getElementsByTagName('em');
				echo "Found: ". count($tags). "\n";
				foreach ($tags as $t) {
					print_r($t);
				}
			}
			if (!count($imgs)) $this->write_log("Image scanner didn't find any images on path: ". $this->params["image_scan"]);
		} else {
			$this->write_log("NOTE: better image search results can be obtained by defining an appropiate <image_scan> xpath value under <alt_media>	");
		}
		return($imgs);
	}
		
	//--------------------------------------------------------------------------------------
	// tried to get the image from the rss description field
	//	
	function get_image_from_article($link) {
		
		$this->write_log("Trying to get image from article.");
		if (($html = file_get_contents($link)) === FALSE)
			throw new Exception("file_get_contents() - failed to get ". $item["link"]);
		$doc = new \DOMDocument();
		@$doc->loadHTML($html);
		$xpath = new \DOMXPath($doc);
		return($this->get_media($xpath, $link));
	}
	//--------------------------------------------------------------------------------------
	// tried to get the image from the rss description field
	//	
	function get_image_from_rss($item, $xp) {
		
		$this->write_log("Trying to get image from rss.");
		$imgs = array();
		if (isset($item["media"]) && strlen($item["media"])) { 
			$this->write_log("Getting image from rss thumbnail.");
			$img = new stdClass();
			$img->url = $item["media"];
			array_push($imgs, $img);
		}
		
		if ($xp) {
			$n = $xp->query("//img/@src");											// hunt about for a pic
			$this->write_log("Searching for image url in description DOM");
			if ($n != NULL && ($n->length > 0) &&
				strlen($n->item(0)->textContent) && 
				strpos($n->item(0)->textContent, "http") !== FALSE && 
				strpos($n->item(0)->textContent, ".jpg") !== FALSE) {
				$img = new stdClass();
				$img->url = $n->item(0)->textContent;
				array_push($imgs, $img);
			} 
		}

		return($imgs);
	}
	
	//--------------------------------------------------------------------------------------
	// Check the author/s found against any possible list of restricted/required authors
	//
	private function check_restricted_authors($author) {
		
		$author = strtolower($author);
		
		if (!strlen($author) && 
			((isset($this->params["allowed_authors"]) && $this->params["allowed_authors"] != "") || 
			(isset($this->params["exclude_authors"]) && $this->params["exclude_authors"] != ""))) {
				throw new Exception("No author found but have allowed_authors and/or exclude_authors to test against!");
		}
		
		if (isset($this->params["allowed_authors"]) && strlen($this->params["allowed_authors"])) {
			$search_authors = array_map('strtolower', array_map('trim', str_getcsv($this->params["allowed_authors"], ",", "'")));
			$found = false;
			foreach ($search_authors as $s) {
				if (strpos($author, $s) !== FALSE) { 
					$this->write_log("Article has a required author.");
					$found = true;
					break;
				}
			}
			if (!$found) throw new Exception("Article doen't have a required author... rejecting.");
		}
		
		if (isset($this->params["exclude_authors"]) && $this->params["exclude_authors"] != "") {
			$search_authors = array_map('strtolower', array_map('trim', str_getcsv($this->params["exclude_authors"], ",", "'")));
			foreach ($search_authors as $s) {
				if (strpos($author, $s) !== FALSE) { 
					throw new Exception("Article has an excluded author... rejecting.");
				}
			}
		}
		$this->write_log("Author accepted");
	}
	
	//--------------------------------------------------------------------------------------
	// Search the xpath for an element and return the requested item from the node
	//
	private function get_element($key, $xpath, $retrieve) {
	 
	 $result = "";
	 
		for ($i = ""; isset($this->params[$key. $i]) && !strlen($result); $i++) {
			$path = $this->params[$key. $i];
			if (isset($path) && strlen($path)) {
				$n = $xpath->query($path);
	 
				if ($n->length === 0) { 
					//throw new Exception ("Couldn't retrieve anything with path: ". $path."\n". print_r($n, true));
					//$this->write_log("Couldn't retrieve anything with path: ". $path."\n". print_r($n, true));
					$this->write_log("Couldn't retrieve anything with path: ". $path);
					return(null);
					//return("");
				}
				if (!strcmp($retrieve, "getHTML")) {
					$result = call_user_func(array($this, $retrieve), $n);
				} else if (!strcmp($retrieve, "article_reader")) {
					$result = call_user_func(array($this, $retrieve), $n);
				} else {
					$result = trim($n->item(0)->$retrieve);
				}
			} else {
				throw new Exception("No path specified: ". $path);
			}
		}
/*		
		if (is_object($result) || is_array($result))
			$this->write_log("RETRIEVED: $retrieve ". print_r($result, true). "\n");
		else
			$this->write_log("RETRIEVED: $retrieve ". $result. "\n");
*/
		return($result);
	}
	
	//--------------------------------------------------------------------------------------
	// Get the first item (rss info only) from the stream
	//
	private function article_reader($n) {

		$para = array();
		foreach($n as $p) {
			$html = "";
			if (strlen($p->textContent)) {
				foreach ($p->childNodes as $child) {
					$html .= $p->ownerDocument->saveHTML($child);
				}
			}
			array_push($para, $html);
		}
		return ($para);
	}
	
	//--------------------------------------------------------------------------------------
	// Get the first item (rss info only) from the stream
	//
	private function get_stream_items($stream) {
	
		$item = array();
		$xmlDoc = new DOMDocument();
		//$xmlDoc->load($stream["rss_url"]);
		@$xmlDoc->load($stream["rss_url"]);
		$this->write_log("Loaded document");
		$x = $xmlDoc->getElementsByTagName('item');
		if (empty($x) || empty($x->item(0))) {
			$this->write_log('$x: '. print_r($x, true));
			throw new Exception("xmlDoc->getElementsByTagName('item') - returned null for stream: ". $stream["rss_url"]);
		}
			
//$this->write_log("x->item(0): ". print_r($x->item(0), true));
		foreach($stream as $k => $si) {
			if (strcmp($k, "rss_url") && strcmp($k, "alt_media")) {
				$this->write_log("getElementsByTagName: ". $si);
				
				try {
					$n = $x->item(0)->getElementsByTagName($si);
				} catch (Exception $e) {
					$this->write_log("Failed: getElementsByTagName: ". $si. "\n". print_r($e, true));
				}
				if ($n->length) {
					$item[$si] = trim($n->item(0)->textContent);
					if (!strcmp($si, "thumbnail")) {
						$item["media"] = trim($n->item(0)->getAttribute("url"));
					}
				} else {
					$this->write_log("NOTE: Got nothing for element >$si<\n");
				}
			}
		}
		return($item);
	}
	
	//--------------------------------------------------------------------------------------
	// Get the tags available in this particular rss stream 
	//
	private function get_stream_tags($fn) {
			
		$stream = array();
		$xmlParamsDoc = new DOMDocument();
		$xmlParamsDoc->load($fn);
		$rssTags = $xmlParamsDoc->getElementsByTagName('stream');
		foreach($rssTags->item(0)->childNodes as $n) {
			//print_r($n);
			if ($n->nodeType == 1) {
				//echo $n->textContent. "\n";
				//$stream[$n->tagName] = new stdClass();
				$stream[$n->tagName] = $n->textContent;
			}
		}
		return($stream);
	}
	
	//--------------------------------------------------------------------------------------
	// Check if this article has already been posted
	//
	private function article_posted($headline) {
	
		$this->check_fn = $this->release_dir. str_replace(array(' ','.',':'), array('','',''), $this->params["name"]). ".txt";
		error_log("CHECK_FN: ". $this->check_fn);
		$this->title = str_replace(array("'", '"', '`', "\/", "\\"), array('', '', '', '', ''), $headline);
		$this->write_log("Checking if release was already posted using file: $this->check_fn");
		
		$hash = hash('ripemd160', $this->title);
		if (file_exists($this->check_fn) && !strcmp(file_get_contents($this->check_fn), $hash)) {
			$this->write_log("Article already posted");
			return(true); 
		} else {
			$this->write_log("OK to post");
			if (!$this->testing) {
				file_put_contents($this->check_fn, $hash); 
				//file_put_contents($this->check_fn, $this->title); 
			}
		}
		return(false);
	}

	//--------------------------------------------------------------------------------------
	private function layout_article($a, $mode) {	
	
		$html = "";
		$img_ndx = 0;																			// use the first image retrieved
		if (count($a->imgs) > 1) { 																// if we have more than one image then 
			$img_ndx = rand(2, count($a->imgs) - 1);											// use on of the others in the article the 1st one will be featured
		}
		
		if (!strcmp($mode, "simple")) {		//------------------------------- simple mode
$this->write_log("layout_article() - simple mode");
			$html = 
				'<p style="line-height: 1.5; font-size: x-small; color: dodgerblue; font-family: Verdana; height: 8px;"><strong>From:&nbsp;'.
				$this->params["name"]. '</strong>
				<strong>'.date("jS M Y g:i a").'</strong></p>'. PHP_EOL
				.'<div><p>'. $a->excerpt. '</p></div>'. PHP_EOL;

				//if ((!empty($a->imgs[$img_ndx])) && strlen($a->imgs[$img_ndx]->url)) {
				if ((((count($a->imgs) == 1) && !in_array("featured", $a->post_category)) || 
					(count($a->imgs) > 1)) && 
					!empty($a->imgs[$img_ndx]) && strlen($a->imgs[$img_ndx]->url)) { 	
					$this->write_log("Placing image in article: ". $a->imgs[$img_ndx]->url);
					$html .= '<div><img src="'. $a->imgs[$img_ndx]->url. '" alt="img"></div>'. PHP_EOL;
				}
		} else if (!strcmp($mode, "rip")){	//-------------------------------- rip mode
$this->write_log("layout_article() - rip mode");
			$n = isset($this->params["paragraphs"]) ? $this->params["paragraphs"] : 0;				// number of paragraphs to use 0 = all
			
			$total_para = count($a->para);
			if ($n == 0) $n = $total_para;

			if (strlen($a->author) && isset($this->params["source_heading"]) && (!strcmp($this->params["source_heading"], "all"))) {
				
				$html = '<p style="line-height: 1.5; font-size: x-small; color: dodgerblue; font-family: Verdana; height: 8px;">
						<strong>From:&nbsp;'.$this->params["name"].'</strong>
						<strong>'.date("jS M Y g:i a").'</strong>
						<strong>Author:&nbsp;'. $a->author.'</strong>&nbsp;</p><p style="line-height: 1.0;">&nbsp;</p>'. PHP_EOL;
						
			} else if (isset($this->params["source_heading"]) && !strcmp($this->params["source_heading"], "publication")){
				
				$html = '<p style="line-height: 1.5; font-size: x-small; color: dodgerblue; font-family: Verdana; height: 8px;">
						<strong>From:&nbsp;'.$this->params["name"].'</strong>
						<strong>'.date("jS M Y g:i a").'</strong></p></p><p style="line-height: 1.5;">&nbsp;</p>'. PHP_EOL;		
			}
			//.'<div><p>'. $a->excerpt. '</p></div>'. PHP_EOL;
					
			$html .= "<div>";		
			for ($i = 0; $i < $n; $i++) {
																			// if we are a paragraph in and 
																			// there is an image ready
				//if (!empty($a->imgs[$img_ndx]) && ($a->imgs[$img_ndx]->pos != -1) && ($a->imgs[$img_ndx]->pos != 0) && ($i == $a->imgs[$img_ndx]->pos - 1)) { 				
				//$this->write_log("in array returned: ". in_array("featured", $a->post_category));
				//$this->write_log('$a->post_category: '. print_r($a->post_category, true));
				if ((((count($a->imgs) == 1) && !in_array("featured", $a->post_category)) || 
					(count($a->imgs) > 1)) && 
					!empty($a->imgs[$img_ndx]) && strlen($a->imgs[$img_ndx]->url)) { 				
					$this->write_log("Placing image in article: ". $a->imgs[$img_ndx]->url);
					$html .= "<div>". $a->imgs[$img_ndx]->html. "</div>". PHP_EOL;
				}
				
				if ($i < $n)
					$html .= "<p>". $a->para[$i]. "</p>". PHP_EOL;
				else if ($n < $total_para)
					$html .= "<p>". $a->para[$i]. "...</p>". PHP_EOL;
			}
			$html .= "</div>";
		} else {
			throw new Exception("Unknown mode: missing or wrong <mode>simple | rip</mode> setting in XML configuration file");
		}
	
	/*
			$a->body =  '<p style="FONT-SIZE: xx-small; COLOR: dodgerblue; FONT-FAMILY: Verdana; HEIGHT: 8px">'.
							$this->params["name"]. PHP_EOL .
							date("jS M Y g:i a").  PHP_EOL .
							'Author: '. $a->author. '</p>'. PHP_EOL .
						'<div>'. $html. '</div>';
	*/
		$html .= $this->add_source_link($a->link);
		
		return($html);
	}
	
	//--------------------------------------------------------------------------------------
	private function get_media($xpath, $channel_link) {

		$imgs = array();
		$retrieve_url = "";
		for ($n = 0, $i = ""; isset($this->params["image"."$i"]) && $this->params["image"."$i"] !== ""; $i++, $n++) {
			$img = $xpath->query($this->params["image".$i]);

			//if (($img->length == 0) || $img->item(0)->tagName != "img")) {
			if (($img->length == 0) || (isset($img->item(0)->tagName) && $img->item(0)->tagName != "img") || 
				(isset($img->item(0)->name) && $img->item(0)->name != "src")) {
				$this->write_log("No image on xpath: ". $this->params["image".$i]);
				continue; 
			}
			
			if ((isset($img->item(0)->tagName)) && (!strcmp($img->item(0)->tagName, "img"))) {
				$thisImg = new StdClass();
				//$imgs["image".$i]->src = "";
				$thisImg->url = $img->item(0)->getAttribute('src');
				$thisImg->typ = "IMG";
				$thisImg->cap = "";
				$thisImg->html = "";
				array_push($imgs, $this->process_img($xpath, $thisImg, $i, $channel_link));
				
			} else if (!strcmp($img->item(0)->name, "src")) {
				$thisImg = new StdClass();
				$thisImg->url = $img->item(0)->value;
				$thisImg->typ = "IMG";
				$thisImg->cap = "";
				$thisImg->html = "";
				array_push($imgs, $this->process_img($xpath, $thisImg, $i, $channel_link));
			} 
		}
		//$this->write_log("TOTAL Images found: ". count($imgs). "\n");

		return($imgs);
	}
	
	//--------------------------------------------------------------------------------------
	// gets an image's details
	//
	private function process_img($xpath, $thisImg, $i, $channel_link) {
		
		$thisImg->pos = isset($this->params["position". "$i"]) ? $this->params["position". "$i"] : -1;	// position image after n paragrahs
		
		$this->write_log("Image url found: ". $thisImg->url);

		if (strpos($thisImg->url, "http") === FALSE) {
			$host = parse_url($channel_link, PHP_URL_SCHEME). "://". parse_url($channel_link, PHP_URL_HOST);
			$retrieve_url = $host. $thisImg->url;
		} else {
			$retrieve_url = $thisImg->url;
		}
			
		if (strlen($retrieve_url) && (isset($this->params["copy_locally"]) && !strcmp(strtolower(trim($this->params["copy_locally"])), "yes"))) {																		
			$this->write_log("Coping image locally: ". $retrieve_url. " index: ". $n. "\n");
			file_put_contents($this->img_dir. basename($retrieve_url), file_get_contents($retrieve_url));
			$thisImg->url = $this->plugin_url. "img/". basename($retrieve_url);
		} else if (strlen($retrieve_url)) {
			$this->write_log("Using remote image: ". $retrieve_url);
			$thisImg->url = $retrieve_url;
		}
																										// if there is a caption try and retrieve it
		if (isset($this->params["caption".$i]) && $this->params["caption".$i] !== "") { 
			$cap = $xpath->query($this->params["caption".$i]);
			if ($cap->length) $thisImg->cap = preg_replace("/&#?[a-z0-9]+;/i", "", $cap->item(0)->textContent);
		}
		$thisImg->html = $this->prep_image($thisImg, $i);
		return($thisImg);
	}

	//--------------------------------------------------------------------------------------
	// Writes the article as an html file
	//
	private function generate_article_file($a) {
	
		if ($this->gen_file) {
			$this->write_log("Generating article file");
			$out = "<html>" .PHP_EOL .
				"<head>" .PHP_EOL .
				"<script>" .PHP_EOL .
				"</script>" .PHP_EOL .
				"</head>" .PHP_EOL .
				"<body>" .PHP_EOL .
				"<div><h1><p>".	$a->headline.	"</p></h1></div>" .PHP_EOL;
				
				//if (isset($this->params["mode"]) && !strcmp(strtolower($this->params["mode"]), "rip"))
					//$out .= "<p>".		$a->excerpt. 	"</p>" .PHP_EOL;
			$out .= $a->body. PHP_EOL;
				
				if (isset($a->featured_img_url) && strlen($a->featured_img_url))
					$out .= '<p>Featured Image</p><div><img src="'. $a->featured_img_url. '" alt="img"></div>'. PHP_EOL;
				
			$out .= "</body>" .PHP_EOL .
					"</html>" .PHP_EOL;
					
			file_put_contents("./article.html", $out);
		}
	}
	
	//--------------------------------------------------------------------------------------
	// Add a link to bottom pointing to the article source
	private function add_source_link($item_link) {
		
		$html = "";

		if (isset($this->params["source_string"]) && strlen($this->params["source_string"]) && strlen($item_link)) {
			$follow = '';
			if (isset($this->params["SEOfollow"]) && strlen($this->params["SEOfollow"])) {
				if (!strcmp(strtolower($this->params["SEOfollow"]), 'no')) {
					$follow = 'rel="nofollow"';
				} 
			}
			//<a href="http://www.washington.edu/news/2019/03/11/how-to-train-your-robot-to-feed-you-dinner/" class="ext" target="_blank" rel="noopener noreferrer nofollow">Read more at University of Washington News.<span class="ext" aria-label="(link is external)"></span></a>
			$html = '<p>'. PHP_EOL .
							'<p><a href="' . $item_link. '" '. $follow. ' target="_blank" rel="noopener noreferrer nofollow">' .$this->params["source_string"]. '</a></p>'. PHP_EOL .
					 '</p>'. PHP_EOL;
		}
		$this->write_log("Adding source link\n". $html);
		return($html);
	}
	
	//--------------------------------------------------------------------------------------
	// Sets up the category/s to post to & the tags to be used N.B. Make sure you call this 
	// function after having filled in the heading value of the article object
	private function set_category_and_tags(&$a) {
		
		//$a->post_category = array_map('strtolower', isset($this->params["category"]) ? explode(",", str_replace(' ', '', $this->params["category"])) : array());
		foreach($a->post_category as $k => $c) {
			if (!strcmp($c, "featured_random")) {
				if (rand(0, 1)) {
					array_push($a->post_category, "featured");
					unset($a->post_category[$k]);
					break;
				} else {
					unset($a->post_category[$k]);
					break;
				}
			}
		}
		$a->post_category = array_values($a->post_category);
		//print_r($a->post_category);	
		if (isset($this->params["tags"]) && strlen($this->params["tags"])) {
			if (!strcmp(strtolower($this->params["tags"]), "literal")) {
				$a->post_tags = array_map('strtolower', isset($this->params["tag_values"]) ? explode(" ", $this->params["tag_values"]) : array());
			} else if (!strcmp(strtolower($this->params["tags"]), "dynamic")) {
				$this->write_log("Setting empty tags");
				$a->post_tags = array(); // TODO create tags dynamically based on heading
			} 
		} else {
			$this->write_log("NO tags requested so setting empty tags");
			$a->post_tags = array();
		}
	}
	
	//--------------------------------------------------------------------------------------
	// Check to see if we are expecting to have any particular words in the text 
	// or if we are to exclude postings with certain words
	private function check_requirements($a) {
		
		$pass = true;
		if (isset($this->params["img.options"]) && sizeof($this->params["img.options"])) {
						// we are assuming featured_img_url contains a valid url
			//$pass =	(!strcmp(strtolower($this->params["img.options"]), "no_post_without") && (isset($a->featured_img_url) && strlen($a->featured_img_url)));
			$pass =	(in_array("no_post_without", $this->params["img.options"]) && (isset($a->featured_img_url) && strlen($a->featured_img_url)));
			// TODO!	
			// extend here for other options
		}
		if (isset($this->params["must_include_one"]) && strlen($this->params["must_include_one"])) 
			$pass &= $this->search($a->body, "must_include_one");
		
		if (isset($this->params["must_exclude"]) && strlen($this->params["must_exclude"])) 
			$pass &= $this->search($a->body, "must_exclude");
		
		return($pass);
	}
	
	//--------------------------------------------------------------------------------------
	// Search for inclusion or exclusion of words or phrases in the article
	//
	private function search($text, $type) {
		
		if (!strcmp($type, "must_include_one")) {
			//$search_strings = explode(" ", $this->params["must_include_one"]);
			$search_strings = array_map('trim', str_getcsv($this->params["must_include_one"], ",", "'"));
			$this->write_log("Search parameters: ". print_r($search_strings, true));
			foreach ($search_strings as $s)
				if (strpos($text, $s) !== FALSE) { 
					$this->write_log("Article has at least one required search word or sentence!");
					return(true); 
				}
			return(false);	
		} else if (!strcmp($type, "must_exclude")) {
			//$search_strings = explode(" ", $this->params["must_exclude"]);
			$search_strings = array_map('trim', str_getcsv($this->params["must_exclude"], ",", "'"));
			$this->write_log("Search parameters: ". print_r($search_strings, true));
			foreach ($search_strings as $s) {
				if (strpos($text, $s) !== FALSE) {
					$this->write_log("Article has an excluded search word or sentence!");
					return(false);
				}
			}
			return(true);
		}
		return(true);
	}
	
	//--------------------------------------------------------------------------------------
	// write debug messages
	private function write_log($msg) {
		if ($this->debug) {
			if ($this->testing === false) {
				error_log($msg);
			} else {
				//echo "<pre> $msg </pre>";
				echo "$msg\n";
			}	
		}
	}
	
	//--------------------------------------------------------------------------------------
	// Post an article
	private function post_article($article) {
										// Post article
	//$this->write_log("Category: ". print_r($article->post_category, true));
	//$this->write_log("Tags    : ". print_r($article->post_tags, true));
/*	
	$idObj = get_category_by_slug( 'business' );
	if ( $idObj instanceof WP_Term ) {
		error_log("CATEGORY: ". print_r($idObj, true));
	}
*/	
		//error_log(print_r($article, true));
			
		if ($this->testing) { 
			$this->generate_article_file($article);
			//file_put_contents($this->check_fn, $article->headline);
			return;
		} else { 
			$this->write_log("Posting new release");
			//file_put_contents($this->check_fn, $this->title); 	// both check_fn and title get set in article_posted
		}
																	// generate category id array from category id slugs
		$array_cat_id = array();
		foreach ($article->post_category as $c) {
			if (($idObj = get_category_by_slug($c)) != false) {
				if ( $idObj instanceof WP_Term ) {
					array_push($array_cat_id, $idObj->term_id);
				}
			}
			else $this->write_log("get_category_by_slug() - could not find category with slug '". $c. "'");
		}
		
		$article->headline = balanceTags(wp_kses_post($article->headline), true);
		$article->body = balanceTags(wp_kses_post($article->body), true);

		$post = array(
			'ID' => 0,										// ID zero for new post
			'post_author' => 1,								// id of the user who added the post
			//'post_date' => "",							// post date default is current date
			//'post_date_gmt' => "", //$pubDate,			// GMT post date defaults to post_date
			'post_content' => $article->body,				// (mixed) The post content. Default empty.
			'post_content_filtered' => "",					// (string) The filtered post content. Default empty.
			'post_title' => $article->headline,				// (string) The post title. Default empty.
			'post_excerpt' => $article->excerpt,			// (string) The post excerpt. Default empty.
			'post_status' => "publish",						// (string) The post status {draft|publish|pending}. Default 'draft'.
			'post_type' => "post",							// (string) The post type. Default 'post'.
			'comment_status' => "open",						// (string) Whether the post can accept comments. Accepts 'open' or 'closed'. Default is the value of 'default_comment_status' option.
			'ping_status' => "open",						// (string) Whether the post can accept pings. Accepts 'open' or 'closed'. Default is the value of 'default_ping_status' option.
			'post_password' => "", 							// (string) The password to access the post. Default empty.
			'post_name' => "",								// (string) The post name. Default is the sanitized post title when creating a new post.
			'to_ping' => "",								// (string) Space or carriage return-separated list of URLs to ping. Default empty.
			'pinged' => "",									// (string) Space or carriage return-separated list of URLs that have been pinged. Default empty.
			'post_modified' => "",							// (string) The date when the post was last modified. Default is the current time.
			'post_modified_gmt' => "",						// (string) The date when the post was last modified in the GMT timezone. Default is the current time.
			'post_parent' => 0,								// (int) Set this for the post it belongs to, if any. Default 0.
			'menu_order' => 0,								// (int) The order the post should be displayed in. Default 0.
			'post_mime_type' => "",							// (string) The mime type of the post. Default empty.
			'guid' => "",									// (string) Global Unique ID for referencing the post. Default empty.
			'post_category' => $array_cat_id,				// Official comment is actually wrong nned array of caegory ids ->(array) Array of category names, slugs, or IDs. Defaults to value of the 'default_category' option.									
			'tags_input' => $article->post_tags,			// (array) Array of tag names, slugs, or IDs. Default empty.									
			'tax_input' => array(),							// (array) Array of taxonomy terms keyed by their taxonomy name. Default empty.					
			'meta_input' => array()							// (array) Array of post meta values keyed by their post meta key. Default empty.									
			);
			
		// Production un-comment Testing - comment the next 3 lines
		$ret = wp_insert_post($post, true);
		$this->write_log("wp_insert_post returned: ". print_r($ret, true));
		if ($this->debug) file_put_contents("last_post_reply.txt", print_r($ret, true)); 			// for debugging purposes
		
		if (is_numeric($ret)) {
																									// check if we need to set a feature image
			$this->write_log("feature_image = ". $this->params["feature_image"]);
			$this->write_log("params: ". print_r($this->params, true));
			//if (isset($this->params["feature_image"]) && !strcmp(strtolower(trim($this->params["feature_image"])), "yes")) {
			if (isset($this->params["feature_image"]) && !strcmp(strtolower(trim($this->params["feature_image"])), "yes")) {
				if (isset($article->featured_img_url) && strlen($article->featured_img_url)) {
					$this->write_log("setting featured image to: ". $article->featured_img_url);
					$this->set_featured_image($ret, $article->featured_img_url);
				}
			}
		}
	}
	
	//--------------------------------------------------------------------------------------
	function assign_image($cat) {
	    $this->write_log("-----------------------\n". $this->img_dir. 'stock/*' . "\n-----------------------");
		$dirs = array_map('basename', array_filter(glob($this->img_dir. 'stock/*'), 'is_dir'));
		$this->write_log(print_r($dirs, true). "\n-----------------------");
		foreach ($cat as $c) {
			foreach ($dirs as $d) {
				if (!strcmp($c, $d)) {
					$this->write_log("Found an image directory that matches the article category");
					$files = glob($this->img_dir. "/stock/$c/*.jpg");
					$i = rand(0, count($files) - 1);
					$this->write_log("No featured image available selecting stock: ". $files[$i]);
					return($this->plugin_url. "img/stock/". $c. "/". basename($files[$i]));
				}
			}
		}
		$this->write_log("Coldn't find a suitable image from stock");
		return(null);
	}
	
	//--------------------------------------------------------------------------------------
	function set_featured_image($post_id, $url) {
		
		$this->write_log("Setting feature img for post_id: ". $post_id. " URL=". $url);
		$imgfile = $url;
		$filename = basename($imgfile);
		$upload_file = wp_upload_bits($filename, null, file_get_contents($imgfile));
		if (!$upload_file['error']) {
			$wp_filetype = wp_check_filetype($filename, null );
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent' => 0,
				'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $post_id );
			
			if (!is_wp_error($attachment_id)) {
				require_once(ABSPATH . "wp-admin" . '/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata($attachment_id,  $attachment_data);
			}
			
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	
	//--------------------------------------------------------------------------------------
	// Prepares an image for insertion into the document
	private function prep_image($img, $n) {

		//$this->write_log("Image url: ". $img->url);
		/*
				$html = '<div id="img1" style="width:480px; float:left; padding-left:0px; padding-right:20px; padding-top:0px; padding-bottom:0px;">
					<img alt="img" src="'. $img->url. '" style="float:left; margin:10px" width="460" height="313"/>
					<p><div style="padding-left:20px; FONT-SIZE: xx-small; COLOR: black; FONT-FAMILY: Verdana; HEIGHT: 8px">'. 
					$img->cap.'</p></div>';
		*/
		$width = isset($this->params["width$n"]) ? $this->params["width$n"] : "460px";
		$height = isset($this->params["height$n"]) ? $this->params["height$n"] : "313";
		$float = isset($this->params["float$n"]) ? $this->params["float$n"] : "left"; 
		$padding_left = isset($this->params["padding_left$n"]) ? $this->params["padding_left$n"] : "20px";
		$padding_right = isset($this->params["padding_right$n"]) ? $this->params["padding_right$n"] : "20px";
		$padding_top = isset($this->params["padding_top$n"]) ? $this->params["padding_top$n"] : "0px";
		$padding_bottom = isset($this->params["padding_bottom$n"]) ? $this->params["padding_bottom$n"] : "0px";
		$margin = isset($this->params["margin$n"]) ? $this->params["margin$n"] : "10px";
		
		$html = '<div id="img1" style="width:'. $width. '; float:'. $float. '; padding-left:'. $padding_left. 
					'; padding-right:'. $padding_right. '; padding-top:'. $padding_top. 
					'; padding-bottom:'. $padding_bottom. ';">
					<img alt="img" src="'. $img->url. '" style="float:'. $float. '; margin:'. $margin. '" width="'. 
					$width. '" height="'. $height. '"/>
					<p><div style="padding-left:20px; FONT-SIZE: xx-small; COLOR: black; FONT-FAMILY: Verdana; HEIGHT: 8px">'. 
					$img->cap.'</p></div>';
//echo $html; exit;
		return($html);
	}
	
	//--------------------------------------------------------------------------------------
	// Gets all the html from all the nodes and children
	private function getHTML(DOMNodeList $nl) { 
		$html = "";
		if ($nl != FALSE) {
			foreach($nl as $n) {
				foreach ($n->childNodes as $child) { 
					$html .= $n->ownerDocument->saveHTML($child);
				}
			}
		}
		////echo $html. "\n";
		if (strlen($html) == 0)
			$html = "";
			//$html = "None";

		return($html);
	}
}
?>