<?php

require_once('WP_Widget_Webform.php');

/**
 * Base class for Zeitguys widgets.
 * Developers should override display_widget_content, update and form.
 *
 * @version 1.4
 * 
 * 
 * @changelog
 * V1.4: (IFOA) Passing the $instance argument to add_admin_form_content.
 * 
 *
 * @since HALCO, June 2011
 */
class ZG_Widget extends WP_Widget {

	private $default_title;

	/**
	* @param string $widget_name: the human-readable formatted name of the widget (usually the same as the class name, with spaces instead of underscores).
	* @param string $description: the description of the widget that is displayed in the Admin > Appearance > Widgets page.
	* @param array $control_options Optional Passed to wp_register_widget_control()
	*	 - width: required if more than 250px
	*	 - height: currently not used but may be needed in the future
	*/
	public function __construct($widget_name = 'ZG Widget', $description = 'Generic Widget base class description. Developers must override $widget_options to set description and classname.', $control_options = array()){
		// Build various identifiers using the $widget_name as a base
		$widget_name_chunks = preg_split('/\W+/', $widget_name);
		$this->default_title = implode('', array_slice($widget_name_chunks, 1));

		// todo: figure out a neater way to avoid having to re-define widget_name_chunks
		$widget_name_chunks = preg_split('/\W+/', strtolower($widget_name));
		$id_base = implode('_', $widget_name_chunks);
		$classname = implode('-', $widget_name_chunks);

		$widget_options = array(
			'classname' => $classname,
			'description' => $description
		);

		parent::__construct($id_base, $widget_name, $widget_options, $control_options);
	}




	/**
	 * Abstract method: must be overridden in subclass. Handles the "body" of the widget
	 * when displayed in WordPress.
	 * @param array $admin_options (known as '$instance' to most plugin developers)	contains the widget options as defined by the widget form in Admin.
	 */
	protected function display_widget_content($admin_options){
		echo '<p class="zg_error">ZG_Widget::display_widget_content() should be overridden in subclass.</p>';
	}

	/**
	 * Called from within ZG_Widget::update. Should be overridden in the subclass in the same way 'update' is over-ridden in standard WP plugin development.
	 * @param array $new_instance the data submitted by the user in the widget form on Admin > Appearance > Widgets.
	 * @param array $old_instance the current widget options (before the user made any changes to the form).
	 */
	protected function update_admin_options($new_instance, $old_instance){
		$instance = $old_instance;

		return $instance;
	}

	/**
	 * Called from within ZG_Widget::form. Should be overridden in the subclass. Uses Valz_Webform.pm to add content to the Admin > Appearance > Widgets form for this widget instance.
	 * @param object &$valz_webform implicitly passed by reference. Contains the already-initialized Valz::Webform object to which we'll add form content using $form->addField().
	 */
	protected function add_admin_form_content( $valz_webform, $options = array() ){
		// should be overridden by subclass.
	}


	public function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );

		// display the widget
		echo $this->add_classname( $instance['title'], $before_widget );

		if (! isset( $instance['hide_title'] ) ){
			echo $before_title . $instance['title'] . $after_title;
		}

		echo '<div class="widget-content">';	
		$this->display_widget_content( $instance );
		echo '</div>';

		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		$instance = $old_instance;

		$this->update_instance_fields($instance, $new_instance, array(
			'hide_title'
		));

		$instance['title'] = $new_instance['title'] ? wp_kses($new_instance['title'], array() ) : $this->default_title . '-' . $this->number;

		return $this->update_admin_options($new_instance, $instance);
	}

	public function form($instance) {

		// Display the input form in the widget
		$form = new Valz_WidgetForm($this, $instance);

		$form->addField(array('id'=>'hide_title', 'label'=>__('Hide Title'), 'type'=>'checkbox'));
		$form->addField(array('id'=>'title', 'label'=>__('Title'), 'class'=>'widefat'));

		$this->add_admin_form_content( $form, $instance );

		echo $form->content;
	}


	/**
	 * Shortcut for updating fields that don't require any checking
	 */
	protected function update_instance_fields(&$instance, $new_instance, $field_list){
		foreach($field_list as $field_name){
			$instance[$field_name] = $new_instance[$field_name];
		}
	}
	/**
	 * Quick utility to take any delimited list and output it into a comma-delimited string (no spaces)
	 * @param string $keywords any string of keywords (eg: "keyword1 keyword2,  keyword3,keyword4")
	 */
	protected function clean_keyword_list($keywords = ""){
		return implode(',', preg_split('/[^a-zA-Z0-9_\-]+/', $keywords));
	}

	/**
	 * Returns $before_widget with our-custom-class-name appended to the class="" attribute.
	 * Used with echo in place of echo $before_widget
	 */
	protected function add_classname($title, $before_widget){
		return preg_replace('/\bclass\s*=\s*"([^"]+)"/i', 'class="$1 ' . sanitize_title($title) . '"', $before_widget);
	}
	
	/**
	 * Convenience function to return, as an array, the entire field parameters for the given field_id
	 *
	 * @param string $field_id The unqualified ID of the field we're querying, as supplied in the 'id' attribute of $form->addField()
	 * @return array The entire field parameters
	 */
	protected function get_form_field( $field_id ){
		return $this->form->fields[$this->get_field_id( $field_id )];
	}

	/**
	 * Convenience function to return just the value of a specific field, given its field_id
	 *
	 * @param string $field_id The unqualified ID of the field we're querying, as supplied in the 'id' attribute of $form->addField()
	 * @return string The value of the form field
	 */
	protected function get_field_value( $field_id ){
		$field = $this->get_form_field( $field_id );
		if ( is_array( $field ) ){
			return $field['value'];
		}
	}
}
