<?php

/**
 * Class: ZG_Theme_Options
 * Description: 
 * This is a base class to be extended inside a theme. 
 * Do not initialize this class directly.
 * 
 * @TODO store options in a class level variable, so we are not doing a get_option
 * call in every input field function.
 * @TODO encapsulate helper functions at the bottom of the file into the class
 * 
 * @author Eric Holmes, Zeitguys, Inc.
 * @version 1.0.1
 * 
 * Changelog:
 * -------------------------------------
 * 1.0.1 - Tomas added a permissions check on saving_theme_options() 
 */

// To help stop any fatal errors in case the theme name has not been defined yet.
if( ! defined( 'ZG_THEME_NAME' ) )
	define( 'ZG_THEME_NAME', 'Zeitguys' );

class ZG_Theme_Options {
	
	const MENU_REQUIRED_CAPABILITY = 'edit_theme_options'; // the capability required to see the options page
	
	static $options_page_name;
	static $options_nonce;
	static $settings_options_group;
	static $settings_options_db_field;
	
	
	/**
	 * Create the settings page, and manage all the saving of the options etc.
	 */
	public function __construct(){
		self::$options_page_name = ZG_THEME_NAME . '-options';
		self::$options_nonce = ZG_THEME_NAME . '_nonce';
		self::$settings_options_group = ZG_THEME_NAME . '-options_group';
		self::$settings_options_db_field = ZG_THEME_NAME . '-options';

		add_action( 'admin_init', array( $this, 'register_theme_settings' ) );
		
		// Handle options saving
		add_action( 'admin_init', array( $this, 'saving_theme_options' ) );
		
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
	}

	public function add_options_page(){
		/* Changing away from add_theme_page here - not as "native", but allows
		 * us to add subpages to it - giving a all-in-one customization area.
		 */
		
		$new_page = add_menu_page(
			__( ZG_THEME_NAME . ' Theme Options', ZG_TEXTDOMAIN ),
			__( ZG_THEME_NAME . ' Options', ZG_TEXTDOMAIN ), // menu name
			self::MENU_REQUIRED_CAPABILITY,
			self::$options_page_name,
			array( $this, 'options_page_callback' ),
			'',
			'59.1' // directly after first separator - sneaky!
		);
		add_action( 'load-' . $new_page, array( $this, 'setup_options_page_help' ) );
		add_action( 'admin_print_styles-' . $new_page, array( $this, 'enqueue_options_page_scripts' ) );
	}
	
	public function enqueue_options_page_scripts(){
		wp_enqueue_script( 'farbtastic' );
		wp_enqueue_style( 'farbtastic' );
		wp_enqueue_script( 'theme-options', plugin_dir_url( __FILE__ ) . '/js/theme-options.js', array( 'farbtastic', 'jquery' ) );
	}

	/**
	 * Creates the help tabs as well as the help sidebar for this options page.
	 */
	public static function setup_options_page_help(){
		die( 'function ZG_Theme_Options::setup_options_page_help() must be overridden in extended class.' );
	}


	/**
	 * Class to be used for registering theme settings 
	 */
	public static function register_theme_settings(){
		die( 'function ZG_Theme_Options::register_theme_settings() must be overridden in extended class.' );
	}
	
	/**
	 * class to be used for options page callbacks
	 */
	public static function options_page_callback(){
		?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2><?php _e( ZG_THEME_NAME . ' Theme Options', ZG_TEXTDOMAIN ) ?></h2>
		<form method="post" action="">
			<table class="form-table">
			<?php
			do_action( 'theme_options_page' );
			wp_nonce_field( 'save', self::$options_nonce, false );
			echo '<input name="action" value="update" type="hidden">';
			echo '<tr><th></th><td>' . get_submit_button( null, 'primary', 'submit', false ) . '</td></tr>'; ?>
			</table>
		</form>
	</div>
		<?php
	}
	
	/**
	 * This function allows us to hook into our Theme Options page, and handle 
	 * 
	 * @global type $pagenow
	 * @return type
	 */
	function saving_theme_options() {
		global $pagenow;	
		if( 'admin.php' != $pagenow )
			return;
		if( self::$options_page_name != $_GET['page'] )
			return;
		if( ! isset( $_POST['action'] ) || 'update' != $_POST['action'])
			return;
		if( ! wp_verify_nonce( $_POST[self::$options_nonce], 'save' ) )
			return;
		if( ! current_user_can( 'edit_theme_options' ) )
			return;
		do_action( 'save_theme_options' );
	}

	
	
	
	/**
	 * Renders a number input field
	 * 
	 * @param array $args
	 * - string id
	 */
	public static function number_input_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		$id = $args['id'];
		$options = get_option( self::$settings_options_db_field );
		// Default value
		if ( empty( $options[$id] ) ) $options[$id] = '';
		echo '<input type="number" step="1" name="' . self::$settings_options_db_field . '[' . $id . ']" id="' . $id . '" class="small-text" value="' . esc_attr( $options[$id] ) . '" />';
	}
	
	/**
	 * Renders a dropdown menu field
	 * 
	 * @param array $args
	 * - string id
	 * - array options
	 */
	public static function dropdown_input_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		if ( ! empty( $args['options'] ) ) new WP_Error( 'options_not_defined', __( "You must pass an options array in the \$args parameter.", ZG_TEXTDOMAIN ) );
		extract( $args );
		
		$wp_options = get_option( self::$settings_options_db_field );
		// Default value
		if ( empty( $wp_options[$id] ) ) $wp_options[$id] = '';
		
		echo '<select id="' . $id . '" name="' . self::$settings_options_db_field . '[' . $id . ']">';
		foreach( $options as $key => $value ) {
			echo '<option value="' . $key . '"'. ($key == $wp_options[$id] ? ' selected="selected"' : '') . '>'. $value . '</option>';
		}
		echo '</select>';
		
	}
	
	/**
	 * Renders a checkbox input field
	 * 
	 * @param array $args
	 * - string id
	 */
	public static function checkbox_input_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		$id = $args['id'];
		$options = get_option( self::$settings_options_db_field );
		// Default value
		$checked = $options[$id] == 1 ? ' checked="checked"' : '';
		echo '<label for="' . $id . '"><input type="checkbox" name="' . self::$settings_options_db_field . '[' . $id . ']" id="' . $id . '" value="1" ' . $checked . '/></label>';
	}
	
	/**
	 * Renders a email input field
	 * 
	 * @param array $args
	 * - string id
	 */
	public static function email_input_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		$id = $args['id'];
		$options = get_option( self::$settings_options_db_field );
		// Default value
		if ( empty( $options[$id] ) ) $options[$id] = '';
		echo '<input type="text" size="30" name="' . self::$settings_options_db_field . '[' . $id . ']" id="' . $id . '" value="' . esc_attr( $options[$id] ) . '" />';
	}

	/**
	 * Renders a textarea input field
	 * 
	 * @param array $args
	 * - string id
	 */
	public static function textarea_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		$id = $args['id'];
		$options = get_option( self::$settings_options_db_field );
		// Default value
		if ( empty( $options[$id] ) ) $options[$id] = '';
		echo '<textarea width="130" name="' . self::$settings_options_db_field . '[' . $id . ']" id="' . $id . '" />' . esc_attr( $options[$id] ) . '</textarea>';
	}

	/**
	 * Renders a colourpicker field
	 * 
	 * @param array $args
	 * - string id
	 */
	public static function color_input_field( $args ){
		if ( ! defined( $args['id'] ) ) new WP_Error( 'id_not_defined', __( "You must pass an ID in the \$args parameter.", ZG_TEXTDOMAIN ) );
		$id = $args['id'];
		$options = get_option( self::$settings_options_db_field );
		// Default value
		if ( empty( $options[$id] ) ) $options[$id] = '#ffffff';
		echo '<div class="color-picker" style="position:relative;">';
			echo '<input type="text" name="' . self::$settings_options_db_field . '[' . $id . ']" id="' . $id . '" value="' . esc_attr( $options[$id] ) . '" />';
			echo '<div style="position:absolute;" id="colorpicker-' . $id . '" class="colorpicker"></div>';
		echo '</div>';
	}
}


/**
 * Helpers, that really, should be in core...
 *
 * @global array $wp_settings_sections
 * @param string $id
 * @return array|boolean
 */
function get_settings_section( $id ){
	global $wp_settings_sections;

	foreach( $wp_settings_sections as $page_name => $page ){
		if ( $page[$id] ) {
			$page[$id]['page'] = $page_name;
			return $page[$id];
		}
	}

	return false;
}

/**
 * 
 * @param string $id
 * @return array|boolean
 */
function get_settings_section_page ( $id ){
	$section = get_settings_section( $id );
	if ( $section )
		return $section['page'];

	return false;
}