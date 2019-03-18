<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.anthonydawson.com
 * @since      1.0.0
 *
 * @package    Rss_poster
 * @subpackage Rss_poster/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Rss_poster
 * @subpackage Rss_poster/includes
 * @author     Anthony Dawson <aegdaw@gmail.com>
 */
class Rss_poster {

	protected $feeds;
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Rss_poster_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PLUGIN_NAME_VERSION' ) ) {
			$this->version = PLUGIN_NAME_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'rss_poster';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Rss_poster_Loader. Orchestrates the hooks of the plugin.
	 * - Rss_poster_i18n. Defines internationalization functionality.
	 * - Rss_poster_Admin. Defines all hooks for the admin area.
	 * - Rss_poster_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_poster-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_poster-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-rss_poster-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-rss_poster-public.php';

		// AEGD
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_to_post_poster.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_schedules.php';
		//require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_poster_feeds.php';
		
		// END AEGD	

		$this->loader = new Rss_poster_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Rss_poster_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Rss_poster_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Rss_poster_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Rss_poster_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// AEGD
		//require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-rss_schedules.php';
		$schedules = new RSS_Poster_Schedules();
		$this->loader->add_filter( 'cron_schedules', $schedules ,'cron_add_1hour');
		$this->loader->add_filter( 'cron_schedules', $schedules ,'cron_add_2hour');
		$this->loader->add_filter( 'cron_schedules', $schedules ,'cron_add_3hour');
		$this->loader->add_filter( 'cron_schedules', $schedules ,'cron_add_4hour');
		$this->loader->add_filter( 'cron_schedules', $schedules ,'cron_add_5hour');

		//$feeds = new Rss_poster_feeds();
		//$fn = $feeds->{'get_feed'}();
		//$this->loader->add_action( 'init', $feeds , 'do_update', 10, 1);
		//global $poster;
		$poster = new RSS_To_Post_Poster();
		$this->loader->add_action( 'activate_plugin', $poster , 'set_jobs', 10, 1);
		$this->loader->add_action( 'deactivate_plugin', $poster , 'clear_jobs', 10, 1);
		$this->loader->add_action( 'rss_poster_event', $poster , 'rss_rip_event', 10, 1);
		
		//$this->loader->add_action( 'init', $poster , 'set_jobs', 10, 1);
		
		//$this->loader->add_action( 'rss_poster_event', $poster , 'do_update', 10, 1);
		// END AEGD	

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Rss_poster_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
