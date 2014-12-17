<?php
require_once('../core/Valz_Webform.php');

/*
 * WidgetForm implements Valz::Webform specifically for use in
 * WordPress widgets.
 *
 * @version 1.1
 * @since litontour
 */
class Valz_WidgetForm extends Valz_Webform {
	protected $mode = 'widget_form';
	protected $dirty_fields = false; // override base class

	// custom templates
	protected $checkbox_template;

	private $widgetObj;
	private $instance;
	private $field_id; // holds the original ID of the field

	/**
	 * @param WP_Widget $widget_instance the widget instance to which this form applies (typically $instance in the context of a widget method)
	 */
	function __construct($widget, $instance, $args = array()){
		$this->instance = $instance;
		$this->widgetObj = $widget;

		parent::__construct($args);
	}


	/**
	 * Overrides parent method to set up custom templates for this type of form
	 */
	protected function define_templates(){
		$this->template = '<p><label for="<!id>"><!label><!field></label></p>';
		$this->raw_text_template = '<p><!html></p>';
		$this->header_template = '<h2><!label></h2>';

		$this->checkbox_template = '<p><!field> <label for="<!id>"><!label></p>';
	}

	/**
	 * Overrides addField to ensure that the value is the value of the widget instance for that field
	 */
	public function addField($args = array()){
		if ( isset( $args['id'] ) ){

			// substitute ID with the widget-generated ID
			$this->field_id = $args['id'];
			$args['id'] = $this->widgetObj->get_field_id($this->field_id);
			$args['name'] = isset( $args['name'] ) ? $this->widgetObj->get_field_name($args['name']) : $this->widgetObj->get_field_name($this->field_id);
			$args['value'] = isset( $args['value'] ) ? $args['value'] : "";
			if ( isset( $this->instance[$this->field_id] ) )
				$args['value'] = $this->instance[$this->field_id];
		}

		parent::addField($args);
	}
}
