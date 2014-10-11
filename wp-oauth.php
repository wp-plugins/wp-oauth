<?php

/*
Plugin Name: WP-OAuth
Plugin URI: http://github.com/perrybutler/wp-oauth
Description: A WordPress plugin that allows users to login or register by authenticating with an existing Google, Facebook, LinkedIn, Github, Reddit or Windows Live account via OAuth 2.0. Easily drops into new or existing sites, integrates with existing users.
Version: 0.1.1
Author: Perry Butler
Author URI: http://glassocean.net
License: GPL2
*/

// start the user session for persisting user/login state during ajax, header redirect, and cross domain calls:
session_start();

// instantiate the login class ONCE and maintain a single instance (singleton):
global $wpoa;
$wpoa = WPOA::get_instance();

// main class:
Class WPOA {

	// singleton class pattern:
	protected static $instance = NULL;
	public static function get_instance() {
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}
	
	// define the settings used by this plugin; these will get registered by wordpress and inserted into the database:
	public $settings = array(
		'wpoa_show_login_messages',
		'wpoa_http_util',
		'wpoa_title',
		'wpoa_logo_links_to_site',
		'wpoa_logo_image',
		'wpoa_bg_image',
		'wpoa_show_provider_buttons',
		'wpoa_hide_login_form',
		'wpoa_google_api_enabled',
		'wpoa_google_api_id',
		'wpoa_google_api_secret',
		'wpoa_facebook_api_enabled',
		'wpoa_facebook_api_id',
		'wpoa_facebook_api_secret',
		'wpoa_linkedin_api_enabled',
		'wpoa_linkedin_api_id',
		'wpoa_linkedin_api_secret',
		'wpoa_github_api_enabled',
		'wpoa_github_api_id',
		'wpoa_github_api_secret',
		'wpoa_reddit_api_enabled',
		'wpoa_reddit_api_id',
		'wpoa_reddit_api_secret',
		'wpoa_windowslive_api_enabled',
		'wpoa_windowslive_api_id',
		'wpoa_windowslive_api_secret',
		'wpoa_delete_settings_on_uninstall',
	);
	
	// do something during plugin activation:
	function wpoa_activate() {
	}
	
	// do something during plugin deactivation:
	function wpoa_deactivate() {
	}

	// when the plugin class gets created, fire the initialization:
	function __construct() {
		add_action('init', array($this, 'init'));
	}

	// initialize the plugin's functionality by hooking into wordpress:
	function init() {
		// hook the query_vars and template_redirect so we can stay within the wordpress context no matter what (avoids having to use wp-load.php)
		add_filter('query_vars', array($this, 'wpoa_qvar_triggers'));
		add_action('template_redirect', array($this, 'wpoa_qvar_handlers'));
		// hook activation and deactivation for the plugin:
		register_activation_hook(__FILE__, array($this, 'wpoa_activate'));
		register_deactivation_hook(__FILE__, array($this, 'wpoa_deactivate'));
		// hook scripts and styles for frontend pages:
		add_action('wp_enqueue_scripts', array($this, 'wpoa_init_frontend_scripts_styles'));
		// hook scripts and styles for backend pages:
		add_action('admin_enqueue_scripts', array($this, 'wpoa_init_backend_scripts_styles'));
		add_action('admin_menu', array($this, 'wpoa_settings_page'));
		add_action('admin_init', array($this, 'wpoa_register_settings'));
		$plugin = plugin_basename(__FILE__);
		add_filter("plugin_action_links_$plugin", array($this, 'wpoa_settings_link'));
		// hook scripts and styles for login page:
		add_action('login_enqueue_scripts', array($this, 'wpoa_init_login_scripts_styles'));
		if (get_option('wpoa_logo_links_to_site') == true) {add_filter('login_headerurl', array($this, 'wpoa_logo_link'));}
		add_filter('login_message', array($this, 'wpoa_customize_login_screen'));
		// hooks used globally:
		add_action('show_user_profile', array($this, 'wpoa_linked_accounts'));
		add_action('wp_ajax_wpoa_logout', array($this, 'wpoa_logout'));
		add_action('wp_ajax_wpoa_unlink_account', array($this, 'wpoa_unlink_account'));
		add_action('wp_ajax_nopriv_wpoa_unlink_account', array($this, 'wpoa_unlink_account'));
		add_shortcode('wpoa_login_form', 'wpoa_login_form');
		// push login messages into the DOM if the setting is enabled:
		if (get_option('wpoa_show_login_messages') !== false) {
			add_action('wp_footer', array($this, 'wpoa_push_login_messages'));
			add_filter('admin_footer', array($this, 'wpoa_push_login_messages'));
			add_filter('login_footer', array($this, 'wpoa_push_login_messages'));
		}
	}
	
	// init scripts and styles for use on FRONTEND PAGES:
	function wpoa_init_frontend_scripts_styles() {
		// here we "localize" php variables, making them available as a js variable in the browser:
		$wpoa_cvars = array(
			// basic info:
			'ajaxurl' => admin_url('admin-ajax.php'),
			'template_directory' => get_bloginfo('template_directory'),
			'stylesheet_directory' => get_bloginfo('stylesheet_directory'),
			'plugins_url' => plugins_url(),
			'plugin_dir_url' => plugin_dir_url(__FILE__),
			'url' => get_bloginfo('url'),
			// other:
			'show_login_messages' => get_option('wpoa_show_login_messages'),
		);
		wp_enqueue_script('wpoa-cvars', plugins_url('/cvars.js', __FILE__));
		wp_localize_script('wpoa-cvars', 'wpoa_cvars', $wpoa_cvars);
		// we always need jquery:
		wp_enqueue_script('jquery');
		// load the core plugin scripts/styles:
		wp_enqueue_script('wpoa-script', plugin_dir_url( __FILE__ ) . 'wp-oauth.js', array());
		wp_enqueue_style('wpoa-style', plugin_dir_url( __FILE__ ) . 'wp-oauth.css', array());
	}
	
	// init scripts and styles for use on BACKEND PAGES:
	function wpoa_init_backend_scripts_styles() {
		// here we "localize" php variables, making them available as a js variable in the browser:
		$wpoa_cvars = array(
			// basic info:
			'ajaxurl' => admin_url('admin-ajax.php'),
			'template_directory' => get_bloginfo('template_directory'),
			'stylesheet_directory' => get_bloginfo('stylesheet_directory'),
			'plugins_url' => plugins_url(),
			'plugin_dir_url' => plugin_dir_url(__FILE__),
			'url' => get_bloginfo('url'),
			// other:
			'show_login_messages' => get_option('wpoa_show_login_messages'),
		);
		wp_enqueue_script('wpoa-cvars', plugins_url('/cvars.js', __FILE__));
		wp_localize_script('wpoa-cvars', 'wpoa_cvars', $wpoa_cvars);
		// we always need jquery:
		wp_enqueue_script('jquery');
		// load the core plugin scripts/styles:
		wp_enqueue_script('wpoa-script', plugin_dir_url( __FILE__ ) . 'wp-oauth.js', array());
		wp_enqueue_style('wpoa-style', plugin_dir_url( __FILE__ ) . 'wp-oauth.css', array());
		// load the default wordpress media screen:
		wp_enqueue_media();
	}
	
	// init scripts and styles for use on the LOGIN PAGE:
	function wpoa_init_login_scripts_styles() {
		// here we "localize" php variables, making them available as a js variable in the browser:
		$wpoa_cvars = array(
			// basic info:
			'ajaxurl' => admin_url('admin-ajax.php'),
			'template_directory' => get_bloginfo('template_directory'),
			'stylesheet_directory' => get_bloginfo('stylesheet_directory'),
			'plugins_url' => plugins_url(),
			'plugin_dir_url' => plugin_dir_url(__FILE__),
			'url' => get_bloginfo('url'),
			// login specific:
			'hide_login_form' => get_option('wpoa_hide_login_form'),
			'logo_image' => get_option('wpoa_logo_image'),
			'bg_image' => get_option('wpoa_bg_image'),
			'login_message' => $_SESSION['WPOA']['RESULT'],
			'show_login_messages' => get_option('wpoa_show_login_messages'),
		);
		wp_enqueue_script('wpoa-cvars', plugins_url('/cvars.js', __FILE__));
		wp_localize_script('wpoa-cvars', 'wpoa_cvars', $wpoa_cvars);
		// we always need jquery:
		wp_enqueue_script('jquery');
		// load the core plugin scripts/styles:
		wp_enqueue_script('wpoa-script', plugin_dir_url( __FILE__ ) . 'wp-oauth.js', array());
		wp_enqueue_style('wpoa-style', plugin_dir_url( __FILE__ ) . 'wp-oauth.css', array());
	}

	// define the querystring variables that should trigger an action:
	function wpoa_qvar_triggers($vars) {
		$vars[] = 'connect';
		$vars[] = 'code';
		return $vars;
	}
	
	// handle the querystring triggers:
	function wpoa_qvar_handlers() {
		if (get_query_var('connect')) {
			$provider = get_query_var('connect');
			$this->wpoa_include_connector($provider);
		}
		elseif (get_query_var('code')) {
			$provider = $_SESSION['WPOA']['PROVIDER'];
			$this->wpoa_include_connector($provider);
		}
	}
	
	// load the provider script that is being requested by the user or being called back after authentication:
	function wpoa_include_connector($provider) {
		// normalize the provider name (no caps, no spaces):
		$provider = strtolower($provider);
		$provider = str_replace(" ", "", $provider);
		// include the provider script:
		switch ($provider) {
			case 'google':
				include 'login-google.php';
				break;
			case 'facebook':
				include 'login-facebook.php';
				break;
			case 'linkedin':
				include 'login-linkedin.php';
				break;
			case 'github':
				include 'login-github.php';
				break;
			case 'reddit':
				include 'login-reddit.php';
				break;
			case 'windowslive':
				include 'login-windowslive.php';
				break;
		}
	}
	
	// logout the wordpress user:
	function wpoa_logout() {
		$user = null; 		// nullify the user
		session_destroy(); 	// destroy the php user session
		wp_logout(); 		// logout the wordpress user
	}
	
	// add a settings link to the plugins page:
	function wpoa_settings_link($links) { 
		$settings_link = "<a href='options-general.php?page=WP-OAuth.php'>Settings</a>"; // CASE SeNsItIvE filename!
		array_unshift($links, $settings_link); 
		return $links; 
	}

	// force the login screen logo to point to the site instead of wordpress.org:
	function wpoa_logo_link() {
		return get_bloginfo('url');
	}
	
	// adds basic http auth to a given url string:
	function wpoa_add_basic_auth($url, $username, $password) {
		$url = str_replace("https://", "", $url);
		$url = "https://" . $username . ":" . $password . "@" . $url;
		return $url;
	}
	
	// match the oauth identity to an existing wordpress user account:
	function match_wordpress_user($oauth_identity) {
		// attempt to get a wordpress user id from the database that matches the $oauth_identity['id'] value:
		global $wpdb;
		$usermeta_table = $wpdb->usermeta;
		$query_string = "SELECT $usermeta_table.user_id FROM $usermeta_table WHERE $usermeta_table.meta_key = 'wpoa_identity' AND $usermeta_table.meta_value LIKE '%" . $oauth_identity['provider'] . "|" . $oauth_identity['id'] . "%'";
		$query_result = $wpdb->get_var($query_string);
		// attempt to get a wordpress user with the matched id:
		$user = get_user_by('id', $query_result);
		return $user;
	}
	
	// links a third-party account to an existing wordpress user account:
	function wpoa_link_account($user_id) {
		if ($_SESSION['WPOA']['USER_ID'] != '') {
			add_user_meta( $user_id, 'wpoa_identity', $_SESSION['WPOA']['PROVIDER'] . '|' . $_SESSION['WPOA']['USER_ID'] . '|' . time());
		}
	}

	// unlinks a third-party provider from an existing wordpress user account:
	function wpoa_unlink_account() {
		// get wpoa_identity row index that the user wishes to unlink:
		$wpoa_identity_row = $_POST['wpoa_identity_row'];
		// get the current user:
		global $current_user;
		get_currentuserinfo();
		$user_id = $current_user->ID;
		// delete the wpoa_identity record from the wp_usermeta table:
		global $wpdb;
		$usermeta_table = $wpdb->usermeta;
		$query_string = $wpdb->prepare("DELETE FROM $usermeta_table WHERE $usermeta_table.user_id = $user_id AND $usermeta_table.meta_key = 'wpoa_identity' AND $usermeta_table.umeta_id = %d", $wpoa_identity_row);
		$query_result = $wpdb->query($query_string);
		// notify client of the result;
		if ($query_result) {
			echo json_encode( array('result' => 1) );
		}
		else {
			echo json_encode( array('result' => 0) );
		}
		// wp-ajax requires death:
		die();
	}
	
	// shows the user's linked providers on the 'Your Profile' page where they may be managed:
	function wpoa_linked_accounts() {
		// get the current user:
		global $current_user;
		get_currentuserinfo();
		$user_id = $current_user->ID;
		// get the wpoa_identity records:
		global $wpdb;
		$usermeta_table = $wpdb->usermeta;
		$query_string = "SELECT * FROM $usermeta_table WHERE $user_id = $usermeta_table.user_id AND $usermeta_table.meta_key = 'wpoa_identity'";
		$query_result = $wpdb->get_results($query_string);
		// list the wpoa_identity records:
		echo "<div id='wpoa-linked-accounts' class='wpoa-settings'>";
		echo "<h3>Linked Accounts</h3>";
		echo "<p>Manage the linked accounts which you have previously authorized to be used for logging into this website.</p>";
		echo "<table class='form-table'>";
		echo "<tr valign='top'>";
		echo "<th scope='row'>Your Linked Providers</th>";
		echo "<td>";
		if ( count($query_result) == 0) {
			echo "<p>You currently don't have any accounts linked.</p>";
		}
		echo "<div class='wpoa-linked-accounts'>";
		foreach ($query_result as $wpoa_row) {
			$wpoa_identity_parts = explode('|', $wpoa_row->meta_value);
			$oauth_provider = $wpoa_identity_parts[0];
			$oauth_id = $wpoa_identity_parts[1]; // keep this private, don't send to client
			$time_linked = $wpoa_identity_parts[2];
			$local_time = strtotime("-" . $_COOKIE['gmtoffset'] . ' hours', $time_linked);
			echo "<div>" . $oauth_provider . " on " . date('F d, Y h:i A', $local_time) . " <a class='wpoa-unlink-account' data-wpoa-identity-row='" . $wpoa_row->umeta_id . "' href='#'>Unlink</a></div>";
		}
		echo "</div>";
		echo "</td>";
		echo "</tr>";
		echo "<tr valign='top'>";
		echo "<th scope='row'>Link Another Provider</th>";
		echo "<td>";
		echo $this->wpoa_login_form_content('buttons-row');
		echo "</div>";
		echo "</td>";
		echo "</td>";
		echo "</table>";
	}

	// shortcode which allows adding the login form to any post or page:
	function wpoa_login_form( $atts ){
		$a = shortcode_atts( array(
			'layout' => 'links-column',
		), $atts );
		$html = wpoa_login_form_content($a['layout']);
		return $html;
	}
	
	// gets the content to be used for displaying the login form:
	function wpoa_login_form_content($layout = 'links-column') {
		$html = "";
		$html .= "<div class='wpoa-login-form wpoa-layout-$layout'>";
		$html .= "<nav>";
		if (get_option('wpoa_google_api_enabled')) {$html .= "<a href='#' onclick='loginGoogle(); return false;'>Google</a>";}
		if (get_option('wpoa_facebook_api_enabled')) {$html .= "<a href='#' onclick='loginFacebook(); return false;'>Facebook</a>";}
		if (get_option('wpoa_linkedin_api_enabled')) {$html .= "<a href='#' onclick='loginLinkedIn(); return false;'>LinkedIn</a>";}
		if (get_option('wpoa_github_api_enabled')) {$html .= "<a href='#' onclick='loginGithub(); return false;'>Github</a>";}
		if (get_option('wpoa_reddit_api_enabled')) {$html .= "<a href='#' onclick='loginReddit(); return false;'>Reddit</a>";}
		if (get_option('wpoa_windowslive_api_enabled')) {$html .= "<a href='#' onclick='loginWindowsLive(); return false;'>Windows Live</a>";}
		$html .= "</nav>";
		$html .= "</div>";
		return $html;
	}
	
	// show the login customizations per the admin's chosen settings:
	function wpoa_customize_login_screen() {
		$html = "";
		$html .= "<p id='wpoa-title'>" . get_option('wpoa_title') . "</p>";
		$html .= $this->wpoa_login_form_content('buttons-column');
		echo $html;
	}
	
	// login (or register and login) a wordpress user based on their oauth identity:
	function wpoa_login_user($oauth_identity) {
		// store the user info in the user session so we can grab it later if we need to register the user:
		$_SESSION["WPOA"]["USER_ID"] = $oauth_identity["id"];
		// try to find a matching wordpress user for the now-authenticated user's oauth identity:
		$matched_user = $this->match_wordpress_user($oauth_identity);
		// handle the matched user if there is one:
		if ( $matched_user ) {
			// there was a matching wordpress user account, log it in now:
			$user_id = $matched_user->ID;
			$user_login = $matched_user->user_login;
			wp_set_current_user( $user_id, $user_login );
			wp_set_auth_cookie( $user_id );
			do_action( 'wp_login', $user_login );
			// after login, redirect to the user's last location
			$this->wpoa_end_login("Logged in successfully!");
		}
		// handle the already logged in user if there is one:
		if ( is_user_logged_in() ) {
			// there was a wordpress user logged in, but it is not associated with the now-authenticated user's email address, so associate it now:
			global $current_user;
			get_currentuserinfo();
			$user_id = $current_user->ID;
			$this->wpoa_link_account($user_id);
			// after linking the account, redirect user to their last url
			$this->wpoa_end_login("Your account was linked successfully with your third party authentication provider.");
		}
		// handle the logged out user or no matching user (register the user):
		if ( !is_user_logged_in() && !$matched_user ) {
			// this person is not logged into a wordpress account and has no third party authentications registered, so proceed to register the wordpress user:
			include 'register.php';
		}
		// we shouldn't be here, but just in case...
		$this->wpoa_end_login("Sorry, we couldn't log you in. The login flow terminated in an unexpected way. Please notify the admin or try again later.");
	}
	
	// ends the login request by clearing the login state and redirecting the user back to their last page:
	function wpoa_end_login($msg) {
		$last_url = $_SESSION["WPOA"]["LAST_URL"];
		$_SESSION["WPOA"]["RESULT"] = $msg;
		$this->wpoa_clear_login_state();
		header("Location: " . $last_url);
		exit;
	}
	
	// pushes login messages into the dom where they can be extracted by javascript:
	function wpoa_push_login_messages() {
		$result = $_SESSION['WPOA']['RESULT'];
		$_SESSION['WPOA']['RESULT'] = '';
		echo "<div id='wpoa-result'>" . $result . "</div>";
	}
	
	// clears the login state:
	function wpoa_clear_login_state() {
		unset($_SESSION["WPOA"]["USER_ID"]);
		unset($_SESSION["WPOA"]["USER_EMAIL"]);
		unset($_SESSION["WPOA"]["ACCESS_TOKEN"]);
		unset($_SESSION["WPOA"]["EXPIRES_IN"]);
		unset($_SESSION["WPOA"]["EXPIRES_AT"]);
		unset($_SESSION["WPOA"]["LAST_URL"]);
	}
	
	// registers all settings that have been defined at the top of the plugin:
	function wpoa_register_settings() {
		foreach ($this->settings as $setting_name) {
			register_setting('wpoa_settings', $setting_name);
		}
	}
	
	// add the main settings page:
	function wpoa_settings_page() {
		add_options_page( 'WP-OAuth Options', 'WP-OAuth', 'manage_options', 'WP-OAuth', array($this, 'wpoa_settings_page_content') );
	}

	// render the main settings page content:
	function wpoa_settings_page_content() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		$blog_url = "http://" . rtrim($_SERVER['SERVER_NAME'], "/") . "/";
		?>
		<div class='wrap wpoa-settings'>
			<h2>WP-OAuth Settings</h2>
			<p>Manage settings for WP-OAuth here. Third-party authentication providers will require you to set up an "App" which in turn will provide an "ID" and "Secret" that can be used for securely accessing their API.</p>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----"><input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"></form>
			<form method='post' action='options.php'>
				<?php settings_fields('wpoa_settings'); ?>
				<?php do_settings_sections('wpoa_settings'); ?>
				<h3>General Settings</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Show Login Messages</th>
					<td><input type='checkbox' name='wpoa_show_login_messages' value='1' <?php checked(get_option('wpoa_show_login_messages') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>HTTP Utility</th>
					<td><select name='wpoa_http_util'><option value='curl' <?php selected(get_option('wpoa_http_util'), 'curl'); ?>>cURL</option><option value='stream-context' <?php selected(get_option('wpoa_http_util'), 'stream-context'); ?>>Stream Context</option></td>
					</tr>
				</table>
				<h3>Login Screen Customization</h3>
				<p>Here you may customize the default WordPress login screen.</p>
				<table class='form-table'>
				<tr valign='top'>
					<tr valign='top'>
					<th scope='row'>Title</th>
					<td>
					<input id='wpoa_title' type='text' size='36' name='wpoa_title' value="<?php echo get_option('wpoa_title'); ?>" />
					</td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Logo links to site</th>
					<td><input type='checkbox' name='wpoa_logo_links_to_site' value='1' <?php checked(get_option('wpoa_logo_links_to_site') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Logo image</th>
					<td>
					<input id='wpoa_logo_image' type='text' size='36' name='wpoa_logo_image' value="<?php echo get_option('wpoa_logo_image'); ?>" />
					<input id='wpoa_logo_image_button' type='button' value='Select' />
					</td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Background image</th>
					<td>
					<input id='wpoa_bg_image' type='text' size='36' name='wpoa_bg_image' value="<?php echo get_option('wpoa_bg_image'); ?>" />
					<input id='wpoa_bg_image_button' type='button' value='Select' />
					</td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Show provider buttons</th>
					<td><input type='checkbox' name='wpoa_show_provider_buttons' value='1' <?php checked(get_option('wpoa_show_provider_buttons') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Hide login form</th>
					<td><input type='checkbox' name='wpoa_hide_login_form' value='1' <?php checked(get_option('wpoa_hide_login_form') == 1); ?> /></td>
					</tr>
				</table>
				<h3>Login with Google+</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_google_api_enabled' value='1' <?php checked(get_option('wpoa_google_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Client ID</th>
					<td><input type='text' name='wpoa_google_api_id' value='<?php echo get_option('wpoa_google_api_id'); ?>' /></td>
					</tr>

					<tr valign='top'>
					<th scope='row'>Client Secret</th>
					<td><input type='text' name='wpoa_google_api_secret' value='<?php echo get_option('wpoa_google_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Visit the Google website for developers <a href='https://console.developers.google.com/project'>console.developers.google.com</a>.</li>
						<li>At Google, create a new Project and enable the Google+ API. This will enable your site to access the Google+ API.</li>
						<li>At Google, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new Project's Redirect URI. Don't forget the trailing slash!</li>
						<li>At Google, you must also configure the Consent Screen with your Email Address and Product Name. This is what Google will display to users when they are asked to grant access to your site/app.</li>
						<li>Paste your Client ID/Secret provided by Google into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Login with Facebook</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_facebook_api_enabled' value='1' <?php checked(get_option('wpoa_facebook_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>App ID</th>
					<td><input type='text' name='wpoa_facebook_api_id' value='<?php echo get_option('wpoa_facebook_api_id'); ?>' /></td>
					</tr>
					 
					<tr valign='top'>
					<th scope='row'>App Secret</th>
					<td><input type='text' name='wpoa_facebook_api_secret' value='<?php echo get_option('wpoa_facebook_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Register as a Facebook Developer at <a href='https://developers.facebook.com/'>developers.facebook.com</a>.</li>
						<li>At Facebook, create a new App. This will enable your site to access the Facebook API.</li>
						<li>At Facebook, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new App's Redirect URI. Don't forget the trailing slash!</li>
						<li>Paste your App ID/Secret provided by Facebook into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Login with LinkedIn</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_linkedin_api_enabled' value='1' <?php checked(get_option('wpoa_linkedin_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>API Key</th>
					<td><input type='text' name='wpoa_linkedin_api_id' value='<?php echo get_option('wpoa_linkedin_api_id'); ?>' /></td>
					</tr>
					 
					<tr valign='top'>
					<th scope='row'>Secret Key</th>
					<td><input type='text' name='wpoa_linkedin_api_secret' value='<?php echo get_option('wpoa_linkedin_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Register as a LinkedIn Developer at <a href='https://developers.linkedin.com/'>developers.linkedin.com</a>.</li>
						<li>At LinkedIn, create a new App. This will enable your site to access the LinkedIn API.</li>
						<li>At LinkedIn, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new App's Redirect URI. Don't forget the trailing slash!</li>
						<li>Paste your API Key/Secret provided by LinkedIn into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Login with Github</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_github_api_enabled' value='1' <?php checked(get_option('wpoa_github_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Client ID</th>
					<td><input type='text' name='wpoa_github_api_id' value='<?php echo get_option('wpoa_github_api_id'); ?>' /></td>
					</tr>
					 
					<tr valign='top'>
					<th scope='row'>Client Secret</th>
					<td><input type='text' name='wpoa_github_api_secret' value='<?php echo get_option('wpoa_github_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Register as a Github Developer at <a href='https://developers.github.com/'>developers.github.com</a>.</li>
						<li>At Github, create a new App. This will enable your site to access the Github API.</li>
						<li>At Github, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new App's Redirect URI. Don't forget the trailing slash!</li>
						<li>Paste your API Key/Secret provided by Github into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Login with Reddit</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_reddit_api_enabled' value='1' <?php checked(get_option('wpoa_reddit_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Client ID</th>
					<td><input type='text' name='wpoa_reddit_api_id' value='<?php echo get_option('wpoa_reddit_api_id'); ?>' /></td>
					</tr>
					 
					<tr valign='top'>
					<th scope='row'>Client Secret</th>
					<td><input type='text' name='wpoa_reddit_api_secret' value='<?php echo get_option('wpoa_reddit_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Register as a Reddit Developer at <a href='https://ssl.reddit.com/prefs/apps'>ssl.reddit.com/prefs/apps</a>.</li>
						<li>At Reddit, create a new App. This will enable your site to access the Reddit API.</li>
						<li>At Reddit, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new App's Redirect URI. Don't forget the trailing slash!</li>
						<li>Paste your Client ID/Secret provided by Reddit into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Login with Windows Live</h3>
				<table class='form-table'>
					<tr valign='top'>
					<th scope='row'>Enabled</th>
					<td><input type='checkbox' name='wpoa_windowslive_api_enabled' value='1' <?php checked(get_option('wpoa_windowslive_api_enabled') == 1); ?> /></td>
					</tr>
					
					<tr valign='top'>
					<th scope='row'>Client ID</th>
					<td><input type='text' name='wpoa_windowslive_api_id' value='<?php echo get_option('wpoa_windowslive_api_id'); ?>' /></td>
					</tr>
					 
					<tr valign='top'>
					<th scope='row'>Client Secret</th>
					<td><input type='text' name='wpoa_windowslive_api_secret' value='<?php echo get_option('wpoa_windowslive_api_secret'); ?>' /></td>
					</tr>
				</table>
				<p>
					<strong>Instructions:</strong>
					<ol>
						<li>Register as a Windows Live Developer at <a href='https://manage.dev.live.com'>manage.dev.live.com</a>.</li>
						<li>At Windows Live, create a new App. This will enable your site to access the Windows Live API.</li>
						<li>At Windows Live, provide your site's homepage URL (<?php echo $blog_url; ?>) for the new App's Redirect URI. Don't forget the trailing slash!</li>
						<li>Paste your Client ID/Secret provided by Windows Live into the fields above, then click Save Changes at the bottom of this page.</li>
					</ol>
				</p>
				<h3>Maintenance & Troubleshooting</h3>
				<table class='form-table'>				
					<tr valign='top'>
					<th scope='row'>Delete settings on uninstall</th>
					<td><p><input type='checkbox' name='wpoa_delete_settings_on_uninstall' value='1' <?php checked(get_option('wpoa_delete_settings_on_uninstall') == 1); ?> /><hr/><strong>Warning:</strong> This will delete all settings that may have been created in your database by this plugin, including all linked third-party login providers. This will not delete any WordPress user accounts, but users who may have registered with or relied upon their third-party login providers may have trouble logging into your site. Make absolutely sure you won't need the values on this page any time in the future, because they will be deleted permanently.<hr/><strong>Instructions:</strong> Check the box above, click the Save Changes button, then uninstall this plugin as normal from the Plugins page.</p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
} // END OF WPOA CLASS
?>