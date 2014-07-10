<?php
/**
 * Google Calendar Events Main Class
 *
 * @package   GCE
 * @author    Phil Derksen <pderksen@gmail.com>, Nick Young <mycorpweb@gmail.com>
 * @license   GPL-2.0+
 * @copyright 2014 Phil Derksen
 */


class Google_Calendar_Events {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '2.0.0-beta1';

	/**
	 * Unique identifier for the plugin.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'google-calendar-events';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		
		$this->includes();
		
		if( false === get_option( 'gce_upgrade_has_run' ) ) {
			$this->upgrade();
		}
		
		
		$this->setup_constants();
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_styles' ) );
		
		// AJAX
		add_action( 'wp_ajax_no_priv_gce_ajax', array( $this, 'gce_ajax' ) );
		add_action( 'wp_ajax_gce_ajax', array( $this, 'gce_ajax' ) );
		
		// Load plugin text domain
		add_action( 'plugins_loaded', array( $this, 'plugin_textdomain' ) );
		
	}
	
	/**
	 * Load the upgrade file
	 * 
	 * @since 2.0.0
	 */
	private function upgrade() {
		include_once( 'includes/admin/upgrade.php' );
		
		update_option( 'gce_version', self::VERSION );
	}
	
	/**
	 * Setup public constants 
	 * 
	 * @since 2.0.0
	 */
	public function setup_constants() {
		if( ! defined( 'GCE_DIR' ) ) {
			define( 'GCE_DIR', dirname( __FILE__ ) );
		}
		
		if( ! defined( 'GCE_PLUGIN_SLUG' ) ) {
			define( 'GCE_PLUGIN_SLUG', $this->plugin_slug );
		}
	}
	
	/**
	 * Include all necessary files
	 * 
	 * @since 2.0.0
	 */
	public static function includes() {
		global $gce_options;
		
		// First include common files between admin and public
		include_once( 'includes/gce-feed-cpt.php' );
		include_once( 'includes/class-gce-feed.php' );
		include_once( 'includes/class-gce-event.php' );
		include_once( 'includes/shortcodes.php' );
		include_once( 'includes/class-gce-display.php' );
		
		include_once( 'views/widgets.php' );
		
		// Now include files specifically for public or admin
		if( is_admin() ) {
			// Admin includes
			include_once( 'includes/admin/admin-functions.php' );
		} else {
			// Public includes
		}
		
		// Setup our main settings options
		include_once( 'includes/register-settings.php' );
		
		$gce_options = gce_get_settings();
	}
	
	/**
	 * Load public facing scripts
	 * 
	 * @since 2.0.0
	 */
	public function enqueue_public_scripts() {
		
		wp_enqueue_script( $this->plugin_slug . '-qtip', plugins_url( 'js/jquery-qtip.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
		wp_enqueue_script( $this->plugin_slug . '-public', plugins_url( 'js/gce-script.js', __FILE__ ), array( 'jquery', $this->plugin_slug . '-qtip' ), self::VERSION, true );
		
		wp_localize_script( $this->plugin_slug . '-public', 'gce', 
				array( 
					'ajaxurl' => admin_url( 'admin-ajax.php' )
				) );
	}
	
	/*
	 * Load public facing styles
	 * 
	 * @since 2.0.0
	 */
	public function enqueue_public_styles() {
		wp_enqueue_style( $this->plugin_slug . '-public', plugins_url( 'css/gce-style.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
	
	/**
	 * AJAX function for grid pagination
	 * 
	 * @since 2.0.0
	 */
	function gce_ajax() {
		if ( isset( $_GET['gce_feed_ids'] ) ) {
			$ids   = $_GET['gce_feed_ids'];
			$title = $_GET['gce_title_text'];
			$max   = $_GET['gce_max_events'];
			$month = $_GET['gce_month'];
			$year  = $_GET['gce_year'];

			$title = ( 'null' == $title ) ? null : $title;
			
			$args = array(
				'title_text' => $title,
				'max_events' => $max,
				'month'      => $month,
				'year'       => $year,
			);
			
			if ( 'page' == $_GET['gce_type'] ) {
				echo gce_print_calendar( $ids, 'grid', $args );
			} elseif ( 'widget' == $_GET['gce_type'] ) {
				$args['widget'] = 1;
				echo gce_print_calendar( $ids, 'grid', $args );
			}
		}
		die();
	}
	
	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    2.0.0
	 */
	public function plugin_textdomain() {
		// Set filter for plugin's languages directory
		$gce_lang_dir = dirname( plugin_basename( GCE_MAIN_FILE ) ) . '/languages/';
		$gce_lang_dir = apply_filters( 'gce_languages_directory', $gce_lang_dir );

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale',  get_locale(), 'gce' );
		$mofile        = sprintf( '%1$s-%2$s.mo', 'gce', $locale );

		// Setup paths to current locale file
		$mofile_local  = $gce_lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/gce/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			load_textdomain( 'gce', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			load_textdomain( 'gce', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'gce', false, $gce_lang_dir );
		}

	}
}
