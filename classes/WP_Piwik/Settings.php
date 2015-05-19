<?php

namespace WP_Piwik;

/**
 * Manage WP-Piwik settings
 *
 * @author Andr&eacute; Br&auml;kling
 * @package WP_Piwik
 */
class Settings {
	
	/**
	 *
	 * @var Environment variables and default settings container
	 */
	private static $wpPiwik, $defaultSettings;
	
	/**
	 *
	 * @var Define callback functions for changed settings
	 */
	private $checkSettings = array (
			'piwik_url' => 'checkPiwikUrl',
			'piwik_token' => 'checkPiwikToken',
			'site_id' => 'requestPiwikSiteID',
			'tracking_code' => 'prepareTrackingCode',
			'noscript_code' => 'prepareNocscriptCode' 
	);
	
	/**
	 *
	 * @var Register default configuration set
	 */
	private $globalSettings = array (
			// Plugin settings
			'revision' => 0,
			'last_settings_update' => 0,
			// User settings: Piwik configuration
			'piwik_mode' => 'http',
			'piwik_url' => '',
			'piwik_path' => '',
			'piwik_user' => '',
			'piwik_token' => '',
			'auto_site_config' => true,
			// User settings: Stats configuration
			'default_date' => 'yesterday',
			'stats_seo' => false,
			'dashboard_widget' => false,
			'dashboard_chart' => false,
			'dashboard_seo' => false,
			'toolbar' => false,
			'capability_read_stats' => array (
					'administrator' => true 
			),
			'perpost_stats' => false,
			'plugin_display_name' => 'WP-Piwik',
			'piwik_shortcut' => false,
			'shortcodes' => false,
			// User settings: Tracking configuration
			'track_mode' => 'disabled',
			'track_codeposition' => 'footer',
			'track_noscript' => false,
			'track_nojavascript' => false,
			'proxy_url' => '',
			'track_content' => 'disabled',
			'track_search' => false,
			'track_404' => false,
			'add_post_annotations' => false,
			'add_customvars_box' => false,
			'add_download_extensions' => '',
			'disable_cookies' => false,
			'limit_cookies' => false,
			'limit_cookies_visitor' => 34186669, // Piwik default 13 months
			'limit_cookies_session' => 1800, // Piwik default 30 minutes
			'limit_cookies_referral' => 15778463, // Piwik default 6 months
			'track_admin' => false,
			'capability_stealth' => array (),
			'track_across' => false,
			'track_across_alias' => false,
			'track_feed' => false,
			'track_feed_addcampaign' => false,
			'track_feed_campaign' => 'feed',
			// User settings: Expert configuration
			'cache' => true,
			'disable_timelimit' => false,
			'connection_timeout' => 5,
			'disable_ssl_verify' => false,
			'piwik_useragent' => 'php',
			'piwik_useragent_string' => 'WP-Piwik',
			'track_datacfasync' => false,
			'track_cdnurl' => '',
			'track_cdnurlssl' => '',
			'force_protocol' => 'disabled'
	), $settings = array (
			'name' => '',
			'site_id' => NULL,
			'noscript_code' => '',
			'tracking_code' => '',
			'last_tracking_code_update' => 0,
			'dashboard_revision' => 0 
	), $settingsChanged = false;
	
	/**
	 * Constructor class to prepare settings manager
	 *
	 * @param WP_Piwik $wpPiwik
	 *        	active WP-Piwik instance
	 */
	public function __construct($wpPiwik) {
		self::$wpPiwik = $wpPiwik;
		self::$wpPiwik->log ( 'Store default settings' );
		self::$defaultSettings = array (
				'globalSettings' => $this->globalSettings,
				'settings' => $this->settings 
		);
		self::$wpPiwik->log ( 'Load settings' );
		foreach ( $this->globalSettings as $key => $default ) {
			$this->globalSettings [$key] = ($this->checkNetworkActivation () ? get_site_option ( 'wp-piwik_global-' . $key, $default ) : get_option ( 'wp-piwik_global-' . $key, $default ));
		}
		foreach ( $this->settings as $key => $default )
			$this->settings [$key] = get_option ( 'wp-piwik-' . $key, $default );
	}
	
	/**
	 * Save all settings as WordPress options
	 */
	public function save() {
		if (! $this->settingsChanged) {
			self::$wpPiwik->log ( 'No settings changed yet' );
			return;
		}
		self::$wpPiwik->log ( 'Save settings' );
		foreach ( $this->globalSettings as $key => $value ) {
			if (is_plugin_active_for_network ( 'wp-piwik/wp-piwik.php' ))
				update_site_option ( 'wp-piwik_global-' . $key, $value );
			else
				update_option ( 'wp-piwik_global-' . $key, $value );
		}
		foreach ( $this->settings as $key => $value ) {
			update_option ( 'wp-piwik-' . $key, $value );
		}
		global $wp_roles;
		if (! is_object ( $wp_roles ))
			$wp_roles = new \WP_Roles ();
		if (! is_object ( $wp_roles ))
			die ( "STILL NO OBJECT" );
		foreach ( $wp_roles->role_names as $strKey => $strName ) {
			$objRole = get_role ( $strKey );
			foreach ( array (
					'stealth',
					'read_stats' 
			) as $strCap ) {
				$aryCaps = $this->getGlobalOption ( 'capability_' . $strCap );
				if (isset ( $aryCaps [$strKey] ) && $aryCaps [$strKey])
					$objRole->add_cap ( 'wp-piwik_' . $strCap );
				else
					$objRole->remove_cap ( 'wp-piwik_' . $strCap );
			}
		}
		$this->settingsChanged = false;
	}
	
	/**
	 * Get a global option's value
	 *
	 * @param string $key
	 *        	option key
	 * @return string option value
	 */
	public function getGlobalOption($key) {
		return isset ( $this->globalSettings [$key] ) ? $this->globalSettings [$key] : self::$defaultSettings ['globalSettings'] [$key];
	}
	
	/**
	 * Get an option's value related to a specific blog
	 *
	 * @param string $key
	 *        	option key
	 * @param int $blogID
	 *        	blog ID (default: current blog)
	 * @return \WP_Piwik\Register
	 */
	public function getOption($key, $blogID = null) {
		if ($this->checkNetworkActivation () && ! empty ( $blogID )) {
			return get_blog_option ( $blogID, $key );
		}
		return isset ( $this->settings [$key] ) ? $this->settings [$key] : self::$defaultSettings ['settings'] [$key];
	}
	
	/**
	 * Set a global option's value
	 *
	 * @param string $key
	 *        	option key
	 * @param string $value
	 *        	new option value
	 */
	public function setGlobalOption($key, $value) {
		$this->settingsChanged = true;
		self::$wpPiwik->log ( 'Changed global option ' . $key . ': ' . (is_array ( $value ) ? serialize ( $value ) : $value) );
		$this->globalSettings [$key] = $value;
	}
	
	/**
	 * Set an option's value related to a specific blog
	 *
	 * @param string $key
	 *        	option key
	 * @param int $blogID
	 *        	blog ID (default: current blog)
	 * @param string $value
	 *        	new option value
	 */
	public function setOption($key, $value, $blogID = null) {
		$this->settingsChanged = true;
		self::$wpPiwik->log ( 'Changed option ' . $key . ': ' . $value );
		if ($this->checkNetworkActivation () && ! empty ( $blogID )) {
			add_blog_option ( $blogID, $key, $value );
		} else
			$this->settings [$key] = $value;
	}
	
	/**
	 * Reset settings to default
	 *
	 * @param bool $resetAll
	 *        	set to true to clear authentification settings, too
	 */
	public function resetSettings($resetAll = false) {
		self::$wpPiwik->log ( 'Reset WP-Piwik settings' );
		global $wpdb;
		$keepSettings = array (
				'piwik_token' => $this->getGlobalOption ( 'piwik_token' ),
				'piwik_url' => $this->getGlobalOption ( 'piwik_url' ),
				'piwik_path' => $this->getGlobalOption ( 'piwik_path' ),
				'piwik_user' => $this->getGlobalOption ( 'piwik_user' ),
				'piwik_mode' => $this->getGlobalOption ( 'piwik_mode' )
		);
		if (is_plugin_active_for_network ( 'wp-piwik/wp-piwik.php' )) {
			delete_site_option ( 'wp-piwik_global-settings' );
			$blogs = $wpdb->get_results ( 'SELECT blog_id FROM ' . $wpdb->blogs . ' ORDER BY blog_id' );
			foreach ( $blogs as $blog )
				foreach ( $this->settings as $key => $value )
					delete_blog_option ( $blog->blog_id, 'wp-piwik-' . $key );
			if (! $resetAll)
				update_site_option ( 'wp-piwik_global-settings', $keepSettings );
		} else {
			foreach ( $this->globalSettings as $key => $value )
				delete_option ( 'wp-piwik_global-' . $key );
			foreach ( $this->settings as $key => $value )
				delete_option ( 'wp-piwik-' . $key );
		}
		$this->globalSettings = self::$defaultSettings ['globalSettings'];
		$this->settings = self::$defaultSettings ['settings'];
		if (! $resetAll) {
			self::$wpPiwik->log ( 'Restore connection settings' );
			foreach ( $keepSettings as $key => $value )
				$this->setGlobalOption ( $key, $value );
		}
		$this->save ();
	}
	
	/**
	 * Check if plugin is network activated
	 *
	 * @return boolean Is network activated?
	 */
	public function checkNetworkActivation() {
		if (! function_exists ( "is_plugin_active_for_network" ))
			require_once (ABSPATH . 'wp-admin/includes/plugin.php');
		return is_plugin_active_for_network ( 'wp-piwik/wp-piwik.php' );
	}
	
	/**
	 * Apply new configuration
	 *
	 * @param array $in
	 *        	new configuration set
	 */
	public function applyChanges($in) {
		$in = $this->checkSettings ( $in );
		self::$wpPiwik->log ( 'Apply changed settings:' );
		foreach ( self::$defaultSettings ['globalSettings'] as $key => $val )
			$this->setGlobalOption ( $key, isset ( $in [$key] ) ? $in [$key] : $val );
		foreach ( self::$defaultSettings ['settings'] as $key => $val )
			$this->setOption ( $key, isset ( $in [$key] ) ? $in [$key] : $val );
		$this->setGlobalOption ( 'last_settings_update', time () );
		$this->save ();
	}
	
	/**
	 * Apply callback function on new settings
	 *
	 * @param array $in
	 *        	new configuration set
	 * @return array configuration set after callback functions were applied
	 */
	private function checkSettings($in) {
		foreach ( $this->checkSettings as $key => $value )
			if (isset ( $in [$key] ))
				$in [$key] = call_user_func_array ( array (
						$this,
						$value 
				), array (
						$in [$key],
						$in 
				) );
		return $in;
	}
	
	/**
	 * Add slash to Piwik URL if necessary
	 *
	 * @param string $value
	 *        	Piwik URL
	 * @param array $in
	 *        	configuration set
	 * @return string Piwik URL
	 */
	private function checkPiwikUrl($value, $in) {
		return substr ( $value, - 1, 1 ) != '/' ? $value . '/' : $value;
	}
	
	/**
	 * Remove &amp;token_auth= from auth token
	 *
	 * @param string $value
	 *        	Piwik auth token
	 * @param array $in
	 *        	configuration set
	 * @return string Piwik auth token
	 */
	private function checkPiwikToken($value, $in) {
		return str_replace ( '&token_auth=', '', $value );
	}
	
	/**
	 * Request the site ID (if not set before)
	 *
	 * @param string $value
	 *        	tracking code
	 * @param array $in
	 *        	configuration set
	 * @return int Piwik site ID
	 */
	private function requestPiwikSiteID($value, $in) {
		if ($in ['auto_site_config'] && ! $value)
			return self::$wpPiwik->getPiwikSiteId();
		return $value;
	}
	
	/**
	 * Prepare the tracking code
	 *
	 * @param string $value
	 *        	tracking code
	 * @param array $in
	 *        	configuration set
	 * @return string tracking code
	 */
	private function prepareTrackingCode($value, $in) {
		if ($in ['track_mode'] == 'manually' || $in ['track_mode'] == 'disabled') {
			$value = stripslashes ( $value );
			if ($this->checkNetworkActivation ())
				add_site_option ( 'wp-piwik-manually', $value );
			return $value;
		}
		$result = self::$wpPiwik->updateTrackingCode ();
		$this->setOption ( 'noscript_code', $result ['noscript'] );
		return $result ['script'];
	}
	
	/**
	 * Prepare the nocscript code
	 *
	 * @param string $value
	 *        	noscript code
	 * @param array $in
	 *        	configuration set
	 * @return string noscript code
	 */
	private function prepareNocscriptCode($value, $in) {
		if ($in ['track_mode'] == 'manually')
			return stripslashes ( $value );
		return $value;
	}
}