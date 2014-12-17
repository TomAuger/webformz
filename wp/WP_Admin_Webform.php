<?php

require_once( '../core/Valz_Webform.php' );

/*
 * Extends Valz_Webform in an attempt to make admin-page forms easier to deal with.
 * Adds the field_prefix to any field_ids that are passed to addField, in order to avoid
 * collisions with core data that may already be on the form.
 */

class WP_Admin_Webform extends Valz_Webform {
	// protected $default_class = 'regular-text'; // default class added to input fields

	protected $field_prefix = '_zg_';

	protected $savelabel = 'Save Changes';

	// dirty fields was breaking array $_POST values - ie. name="some_key[]"
	protected $dirty_fields = false;

	public function __construct( $field_prefix = '_zg_', $args = array() ){
		$this->field_prefix = esc_attr( $field_prefix );

		$options = get_options_wildcard( $this->field_prefix, 'prefix' );

		/** @TODO if we have multiple meta values for the same key, we're screwed right now */
		$this->user_input = array_merge( $options, $_REQUEST );

		parent::__construct( $args );
	}

	/**
	 * Remove the prefix from any provided field or an array of fields. Returns whatever data type was passed (string or array).
	 * Use this if the field id corresponds to the meta key that will be used to update the data.
	 *
	 * @param mixed $field_or_fields String or Array
	 * @return mixed String or Array, depending on what was fed to it.
	 */
	public function strip_prefix( $field_or_fields ){
		if ( is_scalar( $field_or_fields ) ) $fields_array[0] = $field_or_fields;
		else $fields_array = $field_or_fields;

		foreach ( $fields_array as &$field ){
			$field = substr( $field, strlen( $this->field_prefix ) );
		}

		if ( is_scalar( $field_or_fields ) ) return $fields_array[0];
		else return $fields_array;
	}

	/**
	* Establishes the default field formatting templates for Admin-related forms.
	* Override parent method
	*/
	protected function define_templates(){
		switch ( $this->mode ){

			// Extend this by adding additional modes as the need comes up

			default: // Standard options-type table
				$template = '
					<tr>
						<th><label><!label></label></th>
						<td><!field><!description></td>
					</tr>
				';

				$raw_text_template = '
					<tr>
						<td colspan="2"><!html></td>
					</tr>
				';
				$header_template = '
					<tr>
						<th colspan="2"><h2><!html></h2></th>
					</tr>
				';
				$separator_template = '
					<tr>
						<td colspan="2"><hr/></td>
					</tr>
				';
				break;
		}

		return compact( 'template', 'raw_text_template', 'header_template', 'separator_template' );
	}

	/**
	 * Override parent method.
	 * Prepend the field_prefix to any IDs that are passed
	 *
	 * @param array $args
	 */
	public function addField( $args = array() ){
		if ( isset( $args['id'] ) ) $args['id'] = $this->field_prefix . $args['id'];

		// sets default text class
		if( ! isset( $args['class'] ) && in_array( $args['type'], array( 'text', 'textarea' ) ) )
			  $args['class'] = 'regular-text';

		parent::addField( $args );
	}





	/**
	 * Update options based on form entries
	 * $autop (Boolean) - allows you to enable wpautop on the content of this webform.
	 */
	public function update_options_from_input(){
		foreach ( $this->fields as $field ){
			if( ! $field['ignore'] ) {
				$data = $_REQUEST[$field['id']];
				$this->sanitize( $data, $field['sanitization'] );
				// Removed: wp_kses_data( $data ) - data is removing html tags - changed to post
				update_option( $field['id'], $data );
			}
		}
	}

	/**
	 * Override - added the table wrapper to the form content output.
	 * @param bool $echo
	 * @return string
	 */

	public function printForm($echo = true){
	$form = '';

	$form = '<form';
		$form .= echo_if_not_empty($this->id, ' id="%s"', false);
		$form .= echo_if_not_empty($this->name, ' name="%s"', false);
		$form .= echo_if_not_empty($this->method, ' method="%s"', false);
		$form .= echo_if_not_empty($this->action, ' action="%s"', false);
		$form .= echo_if_not_empty($this->form_class, ' class="%s"', false);
	$form .= '>';

	$form .= "<table class='form-table'>$this->content</table>";

	// The Form action buttons...
	$form .= $this->get_submit_button();

	$form .= "</form>";

	if ($echo){
		echo $form;
	} else {
		return $form;
	}
}
}
