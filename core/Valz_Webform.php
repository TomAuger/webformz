<?php
	//namespace Zeitguys;
	require_once('../lib/zg-utilities.php');

	/**
	 * Provide a simple API that generates various forms used in WordPress,
	 * particularly in Admin. Based on Valz::Webforms
	 * @author Tom Auger
	 * @version 0.5 (IFOA)
	 *
	 * @changelog
	 * V0.5:
	 * - added get_field() and get_field_value() to provide low-level direct access to a form's fields for manipulation of the form and data after it's been created.
	 * - expanded the 'time' input type to include separate hour and minute fields
	 * - added a 'placeholder' parameter to text fields and textareas to leverage the html5 placeholder attribute
	 * - added the 'description' and 'descriptionStyle' parameters, along with 'descriptionClass' property
	 * - forced the ID parameter for all field types except SEPARATOR and HEADER - throws an exception
	 * V0.4:
	 * - changed the value comparison on 'selected' SELECT items to a strict equality (===) to prevent equivocation between 'string_value' == 0
	 * - changed SELECT that use a single items array to convert values if the 'display' parameter is not present, and to convert labels if the 'display' parameter is present.
	 * V0.3:
	 * - added data sanitization parameter 'sanitization' with a number of constants and the public sanitize() method
	 * - added the read-only 'fields' property that is a complete collection of all fields with their field parameters, hashed on field id
	 * - enhanced jQuery inline datepicker's label to display the selected date
	 * - added an id="" attribute to <textarea> controls
	 * - header elements that are not provided an ID will be given one programatically
	 * V0.2:
	 * - added textarea input type and a very high level printForm() implementation
	 */
	 class Valz_Webform {
	 	/**
	 	 * Valid input types (used by the field_type / type parameter)
	 	 */
	 	const TEXT_TYPE = 'text';
	 	const TEXTAREA_TYPE = 'textarea';
	 	const CHECKBOX_TYPE = 'checkbox';
	 	const RADIO_BUTTON_TYPE = 'radio';
	 	const EMAIL_TYPE = 'email';
	 	const URL_TYPE = 'url';
	 	const DATE_TYPE = 'date';
	 	const TIME_TYPE = 'time';
	 	const FIELD_TYPE = 'field';
	 	const CURRENCY_TYPE = 'currency';
	 	const HIDDEN_FIELD_TYPE = 'hidden';

	 	const SELECT_TYPE = 'select';

	 	const LEGACY_CHECKBOX_TYPE = 'check';
	 	const LEGACY_SELECT_TYPE = 'picklist';

		// Layout "fields"
	 	const HEADER_FIELD_TYPE = 'header';
	 	const SEPARATOR_TYPE = 'separator';

		/*
		 * HTML5 input types
		 */
	 	const NUMBER_TYPE = 'number';


	 	/**
	 	 * Valid datepicker types (used when field_type == date)
	 	 */

		const JQUERYUI_DATEPICKER = 'jqueryui';
		const JQUERYUI_INLINE_DATEPICKER = 'jqueryui_inline';

	 	const CURRENT_VERSION = 6;

	 	/**
	 	 * Valid CSS classes
	 	 */
	 	const TEXT_CLASS = 'text';
	 	const TEXTAREA_CLASS = 'textarea';
	 	const TIME_CLASS = 'time';
	 	const CHECKBOX_CLASS = 'checkbox';
		const CURRENCY_CLASS = 'currency';
		const SELECT_CONTROL_CLASS = '';
		const REQUIRED_FIELD_CLASS = 'required';

		const OFRM_BUTTONS_SPAN_CLASS = 'savebuttons';


		/**
		 * Select array transformation constants
		 */
		const TITLE_CASE = 'title_case';

		/**
		 * Data Sanitization constants
		 */
		const SANITIZE_ALLOW_HTML = 'html'; // allow all HTML tags through
		const SANITIZE_POST = 'post'; // allow only HTML tags that are valid in posts
		const SANITIZE_DATA = 'data'; // allow a smaller subset of HTML tags eg valid in comments
		const SANITIZE_ALL = 'strict';

		/**
		 * get_field_attr() constants
		 */
		const RESULT_TYPE_SCALAR = 'scalar'; // Return a single value if appropriate (single if only 1 value requested, array if multiple values requested)
		const RESULT_TYPE_ARRAY = 'array'; // Return an array of key-value pairs, every time
		const RESULT_TYPE_OBJECT = 'object'; // Return an object
		const RESULT_TYPE_SINGLE = 'single'; // Return a single, scalar value regardless of how many attributes were requested



		// used by getters - allows read-only access to these restricted properties
		private $_read_only = array('content', 'field_list', 'fields', 'data');

	 	protected $mode = 'web_form'; // determines the basic type of form; this affects template selection among other things
	 	protected $default_class;
	 	protected $scrubbed = false;
	 	protected $dirty_fields = true; // if set, will add a hidden field to each item to handle empty fields
	 	protected $on_change;
	 	protected $on_click;
	 	protected $style; // inline style applied to each element
	 	protected $readonly = false;
	 	protected $currency_symbol = '$';
	 	protected $cols;
	 	protected $selection_text = 'Choose...'; // used with select input type

	 	// Form constructor options
	 	protected $id = ''; // form id
	 	protected $name = ''; // form name - defaults to form id
	 	protected $method = 'post'; // form post method (one of get / post)
	 	protected $action = ''; // form action
	 	protected $form_class = 'valz_webform'; // default class attached to each <form> element
		protected $descriptionClass = 'description'; // default class attached to each 'description' element

		static $last_id_number = 0; // used when programatically generating IDs

	 	protected $savelabel = 'Submit'; // default submit button label

		protected $default_text_sanitization_method = self::SANITIZE_ALL; // the sanitization method we will be using by default

	 	protected $template;
	 	protected $header_template;
	 	protected $raw_text_template;
	 	protected $separator_template;
	 	protected $user_input = array();


	 	private $content; // will contain the printable content of the form
	 	private $data = array(); // will contain all the data for the form
	 	private $field_list = array();// will contain a list of the ID for each field for the form, in the order in which they were created
		private $fields = array();// will contain an associative array of each field in the form, keyed to field ID, complete with all field params as defined when it was added
	 	private $version = self::CURRENT_VERSION;
	 	private $next_column;


	 	function __construct( $args = array() ){
	 		// set up default templates first
			if ( ! $templates = $this->define_templates() ) $templates = array();
			$this->set_templates( $templates );

			// now build all args into their corresponding class properties
			foreach( $args as $key => $val ) {
				// only accept keys that have explicitly been defined as class member variables
				if( property_exists( $this, $key ) ) {
					$this->{$key} = $val;
				}
			}
	 	}

	 	/**
	 	 * Alias for addField() method.
	 	 * @see addField
	 	 */
	 	public function add_field($args = array()){
	 	    $this->addField($args);
		}

	 	/**
	 	 * Adds a new field to the field list, adds its content to the content for this class.
	 	 * @return string $field_content the HTML content of this field
	 	 *
	 	 * @param string type|field_type must be a valid field type (see field type constants)
	 	 * @param string label (optional)(sorta) the label attached to this field
		 * @param string sanitization (optional) type of input sanitization that will be used on this field. If not specified, input could be quite strictly sanitized
	 	 * @param string default_label (optional) the label attached to this field type (from the db table definition)
	 	 * @param string checkbox_value (optional) if the item is a checkbox, this is the value that is sent with the form if it is checked
	 	 * @param bool   required (optional) if set, this field is considered a required field
	 	 * @param int    rowid (optional) the rowid for the record this field is a part of
	 	 * @param string selection_text (optional) text that is displayed as the default in a select input control
	 	 * @param array  items (required for select types) list of items to display in pick list - can either be a flat list or each item can be an array of ($value, $label) pairs.
	 	 * @param string display (optional for select types) transformation constant that describes how we transform the value into a human-friendly display text
		 * @param bool   add_rowid (optional) if set, adds a hidden "rowid" input tag to tag the row id of this row
	 	 * @param mixed  colspan (optional) if set, will let the field span multiple columns; use "*" to span entire form width
	 	 * @param string header_template (optional) overrides the default form's header template
	 	 * @param bool   no_template (optional)
	 	 * @param string datepicker_type (optional) sets the method used to create the date picker popup
	 	 * @param bool   select_on_focus (optional) if set, adds an onfocus handler that selects the text field when it gains focus
		 * @param string description (optional) if set, adds a description beside the field.
		 * @param string descriptionStyle (optional) tag structure for the description. Defaults to '<span {classes}>{description}</span>'
		 * @param string descriptionClass (optional) classes you want to add to the description tag. Defaults to 'description'.
	 	 */
	 	public function addField($args = array()){
			$fieldParms = $this->set_defaults( $args, array(
				'type' => "text",
				'checkbox_value' => "on",
				'datepicker_type' => self::JQUERYUI_DATEPICKER,
				'class' => "",
				'rowid' => 0
			) );

			// Set up aliases (to make porting easier)
			if (! isset( $fieldParms['field_type'] ) ) $fieldParms['field_type'] = $fieldParms['type'];

			// ID should be required for almost everything
			if ( ! isset ( $fieldParms['id'] ) ){
				if ( ! in_array( $fieldParms['field_type'], array(
					self::HEADER_FIELD_TYPE, self::SEPARATOR_TYPE
				)  ) ) throw new Exception( 'addField() requires an "id" parameter for most types of field.' );

				$fieldParms['id'] = "";
			}

			if (! isset( $fieldParms['field_name'] ) ) $fieldParms['field_name'] = $fieldParms['id']; // this is for legacy support. Field_name is deprecated but used extensively in the code
			if (! isset( $fieldParms['name'] ) ) $fieldParms['name'] = $fieldParms['field_name'];

			// Set up default content for the field. Will be used if the Field Type specified is not available or erroneous
			$fieldContent = "<span class=\"zg_api_error zg_error\">field_type {$fieldParms['field_type']} not supported.</span>";


			# Default value for the field is:
			# - whatever the user input for the field,
			# - OR the default value,
			# - OR an empty string, in that order of priority

			$fieldValue = select_defined(
				array( $this->user_input, $fieldParms['field_name']),
				array( $fieldParms, 'value' ),
				array( $fieldParms, 'default_value' )
			);

			$fieldValue = esc_attr($fieldValue);

			# Readonly is true if the whole form is readonly and the field parameter for readonly is not set,
			# OR if the field parameter says this field is readonly.
			$readonly = $this->readonly === true;
			$readonly = $readonly || ( isset( $fieldParms['readonly'] ) && true == $fieldParms['readonly'] );

			$export_value = $fieldValue; # this is the final formatted display value for export (no HTML) - defaults to fieldValue but may be modified by the field type

			$style = ""; // inline style
			$tags = ""; # used to add additional tags (scrubcode, style etc...) at the end of the elements/entities

			# Add scrub code if required.
			# This allows the form to remember which rows or fields are "dirty" (have been altered)
			# In row context, it will also add a "Changed" column heading and scrub radio box at the end of the row
			if ( $this->scrubbed || ( isset( $fieldParms['scrubbed'] ) && $fieldParms['scrubbed'] ) ) {
				$tags .= (stristr($fieldParms['field_type'], self::CHECKBOX_TYPE) || stristr($fieldParms['field_type'], self::RADIO_BUTTON_TYPE)) ? "onClick" : "onChange";
				$tags .= '="document.getElementById(\'' . $this->{id} . '\').record_dirty_' . $fieldParms['rowid'] . '.checked = true;"';
			}

			# add onChange or onClick code if present
			$onChange = select_non_empty( array( $fieldParms, 'on_change' ), $this->on_change );
			$onClick = select_non_empty( array( $fieldParms, 'on_click' ), $this->on_click );

			# escape quotes
			#$onChange =~ s/(['"`])/\\\1/gi;
			#$onClick =~ s/(['"`])/\\\1/gi;

			if ($onChange){
				if (stristr($tags, 'onChange')){ $tags = preg_replace('/onChange\s*=\s*"/', 'onChange="' . $onChange . ';', $tags); }
				else { $tags .= 'onChange="' . $onChange . '"'; }
			}
			if ($onClick){
				if (stristr($tags, 'onClick')){ $tags = preg_replace('/onClick\s*=\s*"/', 'onClick="' . $onClick . ';', $tags); }
				else { $tags .= 'onClick="' . $onClick .'"'; }
			}

			# add style code if required
			if ( ! empty( $this->style ) || ( isset( $fieldParms['style'] ) && ! empty( $fieldParms['style'] ) ) ){
				if ( strlen( $tags ) ) $tags .= " ";
				$style = select_non_empty( array( $fieldParms, 'style' ), $this->style );
				$tags .= 'style="' . $style . '"';
				$style = ' style="' . $style . '"';
			}






			/**
			 * The Big Switch statement (TM)
			 * @TODO refactor into a individual objects using a common interface
			 */
			 switch ($fieldParms['field_type']){
				case self::TEXT_TYPE:
				case self::EMAIL_TYPE:
				case self::URL_TYPE:
				case self::FIELD_TYPE:
				case self::CURRENCY_TYPE:
					# if we include the TABLE and DISPLAY parameters,
					# the field value is the lookup of the field value (presumably row_id) within TABLE
					# DISPLAY is the field from TABLE that will be shown instead of the row_id

					# in readonly mode, email displays a mailto:link and url displays, well a link
					if ($readonly){
						switch ($fieldParms['field_type']){
							case self::EMAIL_TYPE:
								$fieldContent = '<a href="mailto:' . $fieldValue . '">' . $fieldValue . '</a>';
								break;
							case self::URL_TYPE:
								$targetWindow = select_non_empty($fieldParms['target'], "valzwebform_url");
								$targetURL = $fieldValue;
								if (stripos($targetURL, 'http://') != 0) $targetURL = "http://" . $targetURL;
								$fieldContent = '<a href="'.$targetURL.'" target="'.$targetWindow.'">'.$fieldValue.'</a>';
								$export_value = $targetURL;
								break;
							case self::CURRENCY_TYPE:
								if (!$fieldValue || $fieldValue == '0.00') $fieldValue = "-.--" ;
								$prefix = select_defined($fieldParms['currency_symbol'], $this->currency_symbol);
								$fieldContent = '<span class="'.self::CURRENCY_CLASS.'">'.$prefix . $fieldValue.'</span>';
								$export_value = $prefix . $fieldValue;
								break;
							default:
								$fieldContent = $fieldValue;
						}

						# UPGRADE 3.0 - add support for "edit_action"
						#my $fieldClass = qq { class="} . ($this->version < 5 ? "form_$fieldParms['class']_link" : "link") . qq {" };
						#if ($fieldParms['edit_action'] && $fieldValue) $fieldContent .= qq {<a href="javascript:editPopup('$fieldParms['edit_action']','','$fieldValue','$this->user_id','$this->current_offset')" $fieldClass>$this->edit_button_icon</a>} ;
					} else {
						# EDITABLE text field
						if ($fieldParms['field_type'] == self::CURRENCY_TYPE){
							$prefix = select_defined( array( $fieldParms, 'currency_symbol' ), $this->currency_symbol );
						}
						# UPGRADE 3.0 - add support for "edit_action"
						#if ($fieldParms['edit_action'] && $fieldValue) my $editaction = qq {<a href="javascript:editPopup('$fieldParms['edit_action']','','$fieldValue','$this->user_id','$this->current_offset')" class="form_$fieldParms['class']_link">$this->edit_button_icon</a>} ;
						$editaction = "";

						$classTag = $this->generate_class_attribute(self::TEXT_CLASS, $fieldParms['class']);



						// Define the field content
						$fieldContent = "";
						if (isset($prefix)) $fieldContent .= '<span class="'.self::CURRENCY_CLASS.'">'.$prefix;
						// Add the ID in if it is provided
						$id = " ";
						if (isset($fieldParms['id'])) $id = ' id="' . $fieldParms['id'] . '" ';


						# add an onChange handler to be able to process a blank textfield
						if ($this->dirty_fields){
							$onChange = "this.nextSibling.value=1;";
							if (stristr($tags, 'onChange')){ $tags = preg_replace('/onChange\s*=\s*"/', 'onChange="' . $onChange . ';', $tags); }
							else { $tags .= ' onChange="' . $onChange . '"'; }

						}

						// Support the "placeholder" attribute, but only if the field is blank
						$placeholder = isset( $fieldParms['placeholder'] ) ? ' placeholder="' . $fieldParms['placeholder'] . '"' : '';

						if ( isset( $fieldParms['select_on_focus'] ) ){
							$onFocus = "this.select();";
							if ( stristr( $tags, 'onfocus' ) ) $tags = preg_replace( '/\bonfocus\s*=\s*"/i', 'onfocus="'.$onFocus.';', $tags );
							else $tags .= ' onfocus="'.$onFocus.'"';
						}

						$fieldContent .= '<input'.$id.'type="text" name="'.$fieldParms['name'].'" value="'.$fieldValue.'" '.$classTag.$placeholder.' '.$tags.' />';
						if ( $this->dirty_fields ) $fieldContent .= '<input type="hidden" name="'.$fieldParms['name'].'_dirty" value="" />'.$editaction;

						if (isset($prefix)) $fieldContent .= '</span>';
					}

				break; // text types


				case self::NUMBER_TYPE:
					# if we include the TABLE and DISPLAY parameters,
					# the field value is the lookup of the field value (presumably row_id) within TABLE
					# DISPLAY is the field from TABLE that will be shown instead of the row_id

					# in readonly mode, display the value
					if ($readonly){
							$fieldContent = $fieldValue;
					} else {

						# UPGRADE 3.0 - add support for "edit_action"
						#if ($fieldParms['edit_action'] && $fieldValue) my $editaction = qq {<a href="javascript:editPopup('$fieldParms['edit_action']','','$fieldValue','$this->user_id','$this->current_offset')" class="form_$fieldParms['class']_link">$this->edit_button_icon</a>} ;

						$classTag = $this->generate_class_attribute(self::NUMBER_TYPE . ' small-text', $fieldParms['class']);



						// Define the field content
						$fieldContent = "";
						// Add the ID in if it is provided
						$id = " ";
						if (isset($fieldParms['id'])) $id = ' id="' . $fieldParms['id'] . '" ';


						# add an onChange handler to be able to process a blank textfield
						if ($this->dirty_fields){
							$onChange = "this.nextSibling.value=1;";
							if (stristr($tags, 'onChange')){ $tags = preg_replace('/onChange\s*=\s*"/', 'onChange="' . $onChange . ';', $tags); }
							else { $tags .= ' onChange="' . $onChange . '"'; }

						}

						// Support the "placeholder" attribute, but only if the field is blank
						$placeholder = isset( $fieldParms['placeholder'] ) ? ' placeholder="' . $fieldParms['placeholder'] . '"' : '';

						if ( isset( $fieldParms['select_on_focus'] ) ){
							$onFocus = "this.select();";
							if (stristr($tags, 'onfocus')) $tags = preg_replace('/\bonfocus\s*=\s*"/i', 'onfocus="'.$onFocus.';', $tags);
							else $tags .= ' onfocus="'.$onFocus.'"';
						}

						// Number field attributes
						$min = isset( $fieldParms['min'] ) ? "min='{$fieldParms['min']}' " : '';
						$max = isset( $fieldParms['max'] ) ? "max='{$fieldParms['max']}' " : '';
						$step = isset( $fieldParams['step'] ) ? "step='{$fieldParams['step']}' " : '';

						$fieldContent .= '<input'.$id.'type="number" ' . $min . $max . $step . 'name="'.$fieldParms['name'].'" value="'.$fieldValue.'" '.$classTag.$placeholder.' '.$tags.' />';
						if ($this->dirty_fields) $fieldContent .= '<input type="hidden" name="'.$fieldParms['name'].'_dirty" value="" />'.$editaction;

						if (isset($prefix)) $fieldContent .= '</span>';
					}

				break; // text types


				case self::TEXTAREA_TYPE:
					if ($readonly){
						$fieldContent = $fieldValue;
					} else {
						$classTag = $this->generate_class_attribute(self::TEXTAREA_CLASS, $fieldParms['class']);

						if ( isset( $fieldParms['select_on_focus'] ) ){
							$onFocus = "this.select();";
							if ( stristr( $tags, 'onfocus' ) ) $tags = preg_replace( '/\bonfocus\s*=\s*"/i', 'onfocus="'.$onFocus.';', $tags );
							else $tags .= ' onfocus="'.$onFocus.'"';
						}

						$placeholder = isset( $fieldParms['placeholder'] ) ? ' placeholder="' . $fieldParms['placeholder'] . '"' : '';

						$fieldContent = '<textarea id="'.$fieldParms['id'].'" name="'.$fieldParms['name'].'" '.$classTag.$placeholder.' '.$tags.'>'.$fieldValue.'</textarea>';
					}
				break;


				case self::DATE_TYPE:
					if ($readonly){
						if ($fieldValue == '0000-00-00') $fieldValue = $this->null_date_string ;
						$fieldContent = $fieldValue;
						$export_value = $fieldValue;
					} else {
						if ($fieldValue == '0000-00-00') $fieldValue = "" ;
						// $fieldText; // declaration
						// $clearIcon; // declaration
						if ($fieldValue){
							$fieldText = $fieldValue;
							$clearIcon = $this->clear_date_icon;
						}
						else {
							$fieldText = "[pick]";
						}

						$class = 'class="date"';

						switch($fieldParms['datepicker_type']){
							case self::JQUERYUI_DATEPICKER:
								$fieldContent = '<input id="'.$fieldParms['id'].'" type="text" name="'.$fieldParms['name'].'" value="'.$fieldValue.'" '.$classTag.' '.$tags.' />';
								$fieldContent .= '<script>jQuery(document).ready(function($){$("#'.$fieldParms['id'].'").datepicker();});</script>';

							break;

							case self::JQUERYUI_INLINE_DATEPICKER:
								//$sqlDate = preg_replace('/^([^\/]+)\/([^\/]+)\/(.+?)/', '{$3}-{$1}-{$2}', $fieldValue);
								$fieldContent = '<div id="'.$fieldParms['id'].'-datepicker"></div>';
								$fieldContent .= '<input id="'.$fieldParms['id'].'" type="hidden" name="'.$fieldParms['name'].'" value="'.$fieldValue.'" class="datepicker-hidden" />';
								$fieldContent .= '<script type="text/javascript">jQuery(document).ready(function($){$("#'.$fieldParms['id'].'-datepicker").datepicker({altField:"#'.$fieldParms['id'].'", dateFormat:\'yy-mm-dd\', onSelect:function(d,i){$("label[for='.$fieldParms['id'].'] span.datepicker-date-text").html(d);}}).datepicker("setDate", \''.$fieldValue.'\');});</script>';

								// Add the date inside the label, but not if we've deliberately passed an empty label, which effectively should hide the label..._should_...
								if ( ! isset( $fieldParms['label'] ) || ! empty( $fieldParms['label'] ) ){
									$label = select_non_empty(
										array( $fieldParms, 'label' ),
										array( $fieldParms, 'default_label' ),
										'Date: '
									);
									$label .= '<span class="datepicker-date-text">' . $fieldValue . '</span>';
								}
							break;

							default: // (valz)
								$fieldContent = '<span '.$class.$style.'onClick="calendarPopup(this.firstChild)"><input type="hidden" name="'.$fieldParms['field_name'].'" value="'.$fieldValue.'" /><span>'.$fieldText.'</span></span><span style="margin-left:6px;cursor:pointer;" onClick="this.previousSibling.firstChild.value=\'0000-00-00\';this.previousSibling.firstChild.nextSibling.innerHTML=\'[pick]\';this.innerHTML=\'\';">'.$clearIcon.'</span> ';
						}
					}
				break; // date type



				case self::TIME_TYPE:
					if ($readonly){

					} else {
						$classTag = $this->generate_class_attribute( self::TEXT_CLASS, $fieldParms['class'] );

						$separator = isset( $fieldParms['separator'] ) ? $fieldParms['separator'] : " : ";
						$id = $fieldParms['id']; // save some typing!

						list( $hour, $min ) = explode( ":", $fieldValue );

						$fieldContent =
								'<input id="'.$id.'-h" value="'.$hour.'" '.$classTag.$tags. ' />' .
								$separator .
								'<input id="'.$id.'-m" value="'.$min.'" '.$classTag.$tags. ' />' .
								'<input type="hidden" id="'.$fieldParms['id'].'" name="'.$fieldParms['id'].'" value="'.$fieldValue.'" />';

						$fieldContent .= '
								<script type="text/javascript">' .
								'(function(){var id="'.$id.'",$=function(i){return document.getElementById(i)},h=$(id+"-h"),m=$(id+"-m"),d=$(id),ho=h.value,mo=m.value;h.onchange=m.onchange=function(){var hv=h.value,mv=m.value;if(isNaN(hv)||hv>23){h.value=ho;return false}if(isNaN(mv)||mv>59){m.value=mo;return false}h.value=hv=("0"+hv).slice(-2);m.value=mv=("0"+mv).slice(-2);d.value=hv+":"+mv;ho=h.value;mo=m.value}})()' .
								'</script>';
					}
				break;



				case self::CHECKBOX_TYPE:
				case self::LEGACY_CHECKBOX_TYPE:
					if ($readonly){
						$checkText = $fieldValue ? "Yes" : "No";
						$fieldContent = $checkText;
						$export_value = $checkText;
					} else {
						if ( ! isset( $fieldValue ) ) $fieldValue = 0; # don't leave it blank even if it's 0...

						$checked = $fieldValue ? "checked" : "";

						$classTag = $this->generate_class_attribute(self::CHECKBOX_CLASS, $fieldParms['class']);

						if ($this->dirty_fields){
							$fieldContent = '<input type="checkbox" name="'.$fieldParms['field_name'].'_'.$fieldParms['rowid'].'" '.$checked.' '.$classTag.' onMouseDown="this.nextSibling.value=this.checked?\'0\':\'1\';" '.$tags.' />';
							$fieldContent .= '<input type="hidden" id="'.$fieldParms['field_name'].'" name="'.$fieldParms['name'].'" value="'.$fieldValue.'" />';
						} else {
							$fieldContent = '<input id="'.$fieldParms['id'].'" name="'.$fieldParms['name'].'" type="checkbox" value="'.$fieldParms['checkbox_value'].'" '."$checked $classTag $tags />";
						}
						$export_value = $fieldValue ? "Yes" : "No";
					}
				break; // checkbox types



				case self::SELECT_TYPE:
				case self::LEGACY_SELECT_TYPE:
					if ($readonly){
						if ($fieldParms['items']){
							$fieldContent = '<p'.$style.'>'.$fieldValue.'</p>';
						} else {
							if (!$this->dbh) croak ("Valz_Webform error: Database not initialized for picklist field_type!") ;

// 							$sth = $this->dbh->prepare ("SELECT display_value, lang_indep_code FROM picklist WHERE picklist_name=? AND (lang_indep_code=? OR (lang_indep_code IS NULL AND row_id=?))");
// 							$sth->execute($fieldParms['picklist_code'], $fieldValue, $fieldValue);
//
// 							my ($display_value, $display_code) = $sth->fetchrow_array;
// 							if ($fieldParms['display'] == "code") $display_value = $display_code ;
//
// 							# display the literal value if there is no match
// 							if ($fieldValue) $display_value ||= qq {? "$fieldValue"} ;
//
// 							$fieldContent = qq {<p class="form_$fieldParms['class']_text_readonly" $style>$display_value</p>};
// 							$export_value = $display_value;
						}
					} else {
						$classTag = $this->generate_class_attribute(self::SELECT_CONTROL_CLASS, $fieldParms['class']);
						$fieldContent = '<select id="'.$fieldParms['id'].'" name="'.$fieldParms['name'].'"'.$classTag.$tags.'>';

						# handle "multiple values" from a multi-field edit
						if ($fieldValue == '[multiple values]') {
							$fieldContent .= '<option value="[multiple values]">[multiple values]</option>';
						} else {
							$select_text = select_defined( array( $fieldParms, 'selection_text' ), $this->selection_text );
							if ($select_text) $fieldContent .= '<option value="">'.$select_text.'</option>';
						}

						# If supplied, we get our list of options from the items parameter.
						// 'items' is either a flat list of option titles, in which case the value and the display text will be the same,
						// or it's a list of arrays, where the first index is the value and the second is the label. Perfect to prepare with array_map().
						if ($fieldParms['items']){
							foreach ($fieldParms['items'] as $item) {
								$value = ''; $id = ''; $selected = '';
								if (is_array($item)){
									$value = $item[0];
									$label = $item[1];
								} else {
									$value = $label = $item;

									// If we're provided with a display transformation constant, then transform items as we go...
									if ( $fieldParms['display'] ){
										switch($fieldParms['display']){
											case self::TITLE_CASE:
												$label = ucwords(preg_replace('/_+/', ' ', $label));
												break;
										}
									}
									//... otherwise, make sure the values are url-friendly
									else {
										$value = preg_replace('/[^a-z0-9_]/', '', preg_replace( '/\s+/', '_', strtolower( $value ) ) );
									}
								}

								if ($value === $fieldValue) $selected = " selected";

								$fieldContent .= '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
							}
						}
						else {
							# get the items from the picklist table
							//if (!$this->dbh) croak ("Valz::Webform error: Database not initialized for picklist field_type!") ;

// 							$sth = $this->dbh->prepare("DESCRIBE picklist");
// 							$sth->execute();
// 							// $new_style; // declaration
// 							while ($rowref = $sth->fetchrow_hashref){
// 								if ($rowref->{'Field'} == 'display_value') $new_style = 1 ;
// 							}
//
// 							if ($new_style){
// 								$display = $fieldParms['display'] == "code" ? "lang_indep_code" : "display_value";
// 								$sth = $this->dbh->prepare ("SELECT row_id, lang_indep_code, display_value FROM picklist WHERE picklist_name=? AND active=1 ORDER BY display_order, $display");
// 								$sth->execute($fieldParms['picklist_code']);
// 							}
// 							# UPGRADE 3.0 - backward compatibility with old NRX-style naming
// 							else {
// 								$sth = $this->dbh->prepare ("SELECT row_id, lang_indep_code, display_name FROM picklist WHERE parent_code=? AND active=1 ORDER BY display_order, display_name");
// 								$sth->execute($fieldParms['picklist_code']);
// 							}
//
// 							# If the value stored on the field is not in the picklist, we will add the OPTION to the pull-down menu
// 							# We need to know if we found a match, so...
// 							$foundMatch = 0;
// 							while (my ($rowid, $value, $display) = $sth->fetchrow_array) {
// 								if (!defined $value)) $value = $rowid ;
// 								// $selected; // declaration
// 								if (lc $value == lc $fieldValue){
// 									$selected = "selected";
// 									$foundMatch++;
// 								}
//
// 								if ($fieldParms['display'] == "code") $display = $value ;
// 								$fieldContent .= qq {<option value="$value" $selected>$display</option>};
// 							}
//
// 							if ($fieldValue && !$foundMatch)) $fieldContent .= qq {<option value="" selected>? "$fieldValue"</option>} ;

							$fieldContent .= '<option value="" selected>No Data</option>';
						}

						$fieldContent .= '</select>';
					}

				break; // select types





			} // switch


			/* Now, prepare the output */
			# override everything if it's a hidden field and just create the hidden field tag. Not sure if this will always generate valid W3-strict compliant HTML
			if ($fieldParms['field_type'] == self::HIDDEN_FIELD_TYPE) {
				$newContent = '<input type="hidden" id="'.$fieldParms['field_name'].'" name="'.$fieldParms['name'].'" value="'.$fieldValue.'"> ';
				$this->content .= $newContent;
				return $newContent;
			} else if( $fieldParms['field_type'] === self::SEPARATOR_TYPE ) {
				$newContent = $this->separator_template;
				$this->content .= $newContent;
				return $newContent;
			# or if the no_template flag is set, just output the fieldContent with no bells and whistles
			} else if ( isset( $fieldParms['no_template'] ) ){
				$this->content .= $fieldContent;
				return $fieldContent;

			# or, if it's not, then validate the field if we need to and apply the output template
			} else {
				# add the label or the default label; strip out linebreaks and substitute <br>s
				$label = select_defined(
					array( $fieldParms, 'label' ),
					array( $fieldParms, 'default_label' )
				);
				if ( ! empty( $label ) )
					$label = preg_replace('/\n/', '<br \/>', $label );

				# add the Required style and an asterisk if the field is a required field
				if ( isset( $fieldParms['required'] ) && ! $readonly ){
					$label = '<span class="'.self::REQUIRED_FIELD_CLASS.'">'.$label.'</span>';
					$fieldContent .= '<span class="'.self::REQUIRED_FIELD_CLASS.'">&nbsp;*</span>';
				}

// 				# Now validate the field if asked to do so (the "validate_field" flag is set on the field or the "validate_form" flag is set on the form)
// 				if ($fieldParms['validate_field'] || $this->validate_form) {
// 					if ($fieldParms['required'] && (!($this->user_input->{$fieldParms['field_name']}) || ($fieldParms['field_type'] == 'date' && $this->user_input->{$fieldParms['field_name']} == '0000-00-00')    )) { $fieldParms['invalid_field'] = 'required'; }
// 					else if ($fieldParms['validation_regexp'] && $this->user_input->{$fieldParms['field_name']} && !($this->user_input->{$fieldParms['field_name']} =~ /$fieldParms['validation_regexp']/)) { $fieldParms['invalid_field'] = 'regexp'}
// 				}
	# printw "f: $fieldParms['field_name'] v: $fieldValue u: $this->user_input->{$fieldParms['field_name']} vv: $fieldParms['invalid_field'] <br />";

// 				# validation results - these can be set either as a result of a 'validate_field' failure or manually directly in the addField method by passing invalid_field => 'reason'
// 				if ($fieldParms['invalid_field']) {
// 					local $_ = $fieldParms['invalid_field'];
// 					$fieldContent .= $this->version < 5 ? qq { <div class="form_$fieldParms['class']_invalid"> } : qq {<div class="invalid">};
// 					if (/regexp/i) { $fieldContent .= qq {Input not valid. Correct example: "$fieldParms['validation_example']".}; }
// 					if (/required/i) { $fieldContent .= qq {Required field is missing.}) else ; }
// 					if (/password/i) { $fieldContent .= qq {Passwords do not match.}) else ; }
// 					$fieldContent .= qq { </div> };
//
// 					# if a single field is invalid, the whole form is invalid, so...
// 					$this->invalid_form = 1;
// 				}

				# if the add_rowid flag is set, then add a hidden "rowid" input tag to tag the row id of this row
				if ( isset( $fieldParms['add_rowid'] ) ){
					$fieldContent = '<input type="hidden" name="rowid" value="'.$fieldParms['rowid'].'">' . $fieldContent;
				}


				// Populate the (HTML) content for this field
				$newContent = "";

				if (isset( $fieldParms['field_type'] ) && self::HEADER_FIELD_TYPE == $fieldParms['field_type'] ){
					$newContent = select_non_empty( array( $fieldParms, 'header_template' ), $this->header_template );
					// If no ID was given, assign one randomly
					if ( ! isset( $fieldParms['id'] ) || empty( $fieldParms['id'] ) ) $fieldParms['id'] = 'header-' . ( ++self::$last_id_number );
				} else {
					// Select the template.
					// If a template of the form {field_type}_template has been defined, use that,
					// otherwise use the default template
					$newContent = select_non_empty(
						array( $fieldParms, 'template' ), select_if_declared( $this, $fieldParms['field_type'] . '_template' ), $this->template );
				}

				$class = "";
				if ( isset( $fieldParms['class'] ) && ! empty( $fieldParms['class'] ) ) $class = ' class="'.$fieldParms['class'].'"';

				$newContent = preg_replace('/<!class>/i', $class, $newContent);
				$newContent = preg_replace('/<!label>/i', $label, $newContent);
				$newContent = preg_replace('/<!field>/i', $fieldContent, $newContent);
				$newContent = preg_replace('/<!columns>/ei', $this->cols * 2, $newContent);
				$newContent = preg_replace('/<!style>/i', $style, $newContent);
				$newContent = preg_replace('/<!fieldname>/i', $fieldParms['field_name'], $newContent);
				$newContent = preg_replace('/<!id>/i', $fieldParms['id'], $newContent);

				if ( ! isset ( $fieldParms['html'] ) ) $fieldParms['html'] = "";
				$newContent = preg_replace('/<!html>/i', $fieldParms['html'], $newContent);

				// 0.5 Descriptions
				$description = "";
				if ( isset( $fieldParms['description'] ) ){
					$descriptionClass = select_defined( array( $fieldParms, 'description' ), $this->descriptionClass );
					$descriptionStyle = ( isset( $fieldParms['descriptionStyle'] ) && ! empty( $fieldParms['descriptionStyle'] ) ) ? ' style="' . $fieldParms['descriptionStyle'] . '"' : "";
					$description = '<span class="' . $descriptionClass . '"' . $descriptionStyle . '>' . $fieldParms['description'] . '</span>';
				}
				$newContent = preg_replace( '/<!description>/i', $description, $newContent );

				# allow individual fields to span multiple columns in multi-column mode
				if ( isset( $fieldParms['colspan'] ) && $this->mode == 'multi-column' ){
					if ( $fieldParms['colspan'] == '*' ) {
						$colspan = ($this->cols - $this->next_column) * 2 - 1;
						$this->next_column = $this->cols;
					} else {
						$colspan = $fieldParms['colspan'] * 2 - 1;
						$this->next_column += $fieldParms['colspan'] - 1; # don't count this column
					}
					$newContent = preg_replace('/<!colspan>/i', 'colspan="'.$colspan.'"', $newContent);
				} else {
					$newContent = preg_replace('/<!colspan>/i', "", $newContent);
				}

				$this->content .= $newContent;


				// Add the field to the field_list...
				$this->field_list[] = $fieldParms['id'];
				// ...and to the full fields array
				$fieldParms['value'] = $fieldValue;
				$fieldParms['label'] = $label;
				$this->fields[$fieldParms['id']] = $fieldParms;

				return $newContent;
			}
	 	}



		/**
		 * Returns the complete field, either as an associative array or as an object.
		 *
		 * @param string $id The field_id
		 * @param string $result_type One of Valz_Webform::RESULT_TYPE_ARRAY (default) or Valz_Webform::RESULT_TYPE_OBJECT. Determines how the result is sent (as an array or an object)
		 * @return array|object The complete field.
		 */
		public function get_field( $id, $result_type = self::RESULT_TYPE_ARRAY ){
			return $this->fields[$id];
		}

		/**
		 * Get the value of a specific attribute of a form field.
		 *
		 * Depending on the type of $attr (and the specificed $result_type), this will either just return the value	requested, or all of the values requested either as an associative array, or an object.
		 *
		 * If RESULT_TYPE_SCALAR is chosen and only a single attribute was requested, will only return the value; if multiple attributes were requested, will return an array of values. If RESULT_TYPE_ARRAY is requested, then an array of key-value pairs will be returned. RESULT_TYPE_OBJECT produces a standard object. RESULT_TYPE_SINGLE returns only the first value, regardless of how many values were requested.
		 *
		 * No special consideration is given when an undefined attribute is requested. The result is just set to the empty string.
		 *
		 * @param string $field_id The id of the requested field
		 * @param string|array The attribute name(s) requested. Either a single attribute name, or an array of attribute nams.
		 * @param string $result_type One of Valz_Webform::RESULT_TYPE_SCALAR (default), Valz_Webform::RESULT_TYPE_ARRAY, Valz_Webform::RESULT_TYPE_OBJECT, Valz_Webform::RESULT_TYPE_SINGLE
		 *
		 * @return mixed The value(s) of the attributes requested, in either scalar, array or object format as specified by the $result_type argument.
		 */
		public function get_field_attr( $field_id, $attr, $result_type = self::RESULT_TYPE_SCALAR ){
			if ( $field = $this->get_field( $field_id ) ){
				$attrs = array();
				if ( is_array( $attr ) && count( $attr ) > 1 ){
					foreach ( $attr as $attribute ){
						if ( isset( $field[$attribute] ) ) $attrs[$attribute] = $field[$attribute];
						else ( $attrs[$attribute] = '' );
					}
				} else {
					if ( isset( $field[$attr] ) ) $attrs[$attr] = $field[$attr];
					else $attrs[$attribute] = '';
				}
			}

			if ( $attrs ){
				switch ( $result_type ){
					case self::RESULT_TYPE_OBJECT:
						return (object) $attrs;
					case self::RESULT_TYPE_ARRAY:
						return (array) $attrs;
					case self::RESULT_TYPE_SINGLE:
						return reset( $attrs );
					default:
						if ( count( $attrs ) > 1 ) return array_values( $attrs );
						return reset( $attrs );
				}
			}
		}

		/**
		 * Convenience function to just return the 'value' of the field specified by $id.
		 *
		 * @param string $id The $id of the requested field.
		 * @return string The field's 'value'
		 */
		public function get_field_value( $id ){
			return $this->get_field_attr( $id, 'value', self::RESULT_TYPE_SINGLE );
		}

		/**
		 * Set the 'value' attribute of the field identified by $id
		 *
		 * @param string $id The field id
		 * @param string $value The value to be assigned to the field's 'value' parameter.
		 */
		public function set_field_value( $id, $value ){
			if ( isset( $this->fields[$id] ) ){
				$this->fields[$id]['value'] = $value;
			}
		}


		public function get_user_input( $field_id = null ){
			if ( $field_id ){
				if ( isset( $this->user_input[$field_id] ) )
					return $this->user_input[$field_id];

				return null;
			}

			return $this->user_input;
		}


		public function set_user_input( $field_id, $value ){
			$this->user_input[$field_id] = $value;
		}



	 	/**
	 	 *	Leverages WordPress wp_nonce_field to create a onetime use form validation key
	 	 *	@param string $action action name (default = 'save')
	 	 *	@param string $name nonce (cookie) name (default = 'valz_webform_nonce')
	 	 *	@param bool $add_referrer_field whether to add another hidden field with _wp_http_referrer in it
	 	 *	@return string wp_nonce hidden field
	 	 */
	 	public function add_nonce( $action = 'save', $name = 'valz_webform_nonce', $add_referrer_field = false ){
	 		$nonce = wp_nonce_field( $action, $name, $add_referrer_field, false );
	 		$this->content .= $nonce;
	 		return $nonce;
	 	}



	 	/**
	 	 * Establishes the default field formatting templates.
	 	 * Should be overridden by child classes that require custom formatting.
	 	 * Switches templates based on $mode
	 	 */
	 	protected function define_templates(){
	 		switch ( $this->mode ){
				case 'row_per_field':
					$template = '<tr><td class="label"><!label></td><td><!field></td></tr>\n';
					$raw_text_template = '<tr><td colspan="2"><!html></td></tr>\n';
					$header_template ='<thead><tr><td colspan="2"><!label></td></tr></thead>';
					$separator_template = '<tr><td colspan="2"><hr/></td></tr>\n';
					break;

				case 'row_per_record':
					$template = '<td<!style>><!field></td>';
					$header_template = '<td colspan="<!columns>"><!label></td>';
					$separator_template = '<td  colspan="<!columns>"><hr/></td>';
					break;

				case 'multi-column':
					$template = '<td class="label"><!label></td><td <!colspan> ><!field></td>';
					$raw_text_template = '<td colspan="2"><!html></td>';
					$header_template = '<th colspan="<!columns>"><!label></th>';
					$separator_template = '<td  colspan="<!columns>"><hr/></td>';
					break;

				default: // no tables
					$template = '<span<!class>><label for="<!fieldname>"><!label></label><!field></span>'."\n";
					$raw_text_template = '<p><!html></p>'."\n";
					$header_template = '<h2><!label></h2>'."\n";
					$separator_template = '<hr/>'."\n";
					break;
			}

			return compact( 'template', 'raw_text_template', 'header_template', 'separator_template' );
	 	}

		/**
		 * Set up the form display templates, while preserving any templates that may have been defined during instantiation.
		 *
		 * The display templates are used to control the HTML structure around each form element. Defaults are defined within the subclass within the {@link define_templates()} method.
		 * Templates can also be overridden by manually defining them during instantiation of the form, simply by specifying the template name as a parameter and the template itself as the value. Overrides performed this way will supercede templates set using set_templates().
		 * __Note:__ for this reason, it is a good practice to *NOT* define the template properties within the subclass, but rather to override {@link define_templates()}, unless you wish to strictly enforce the template, and there are no multiple "modes" that would offer different templates.
		 *
		 * @used-by define_templates()
		 * @param array $templates_array Associative array of 'template_name' => 'template value'
		 * @throws BadMethodCallException if an array is not passed
		 */
		protected function set_templates( $templates_array = array() ){
			if ( is_array( $templates_array ) ){
				foreach( $templates_array as $template_name => $template ){
					if ( property_exists( $this, $template_name ) ){
						if ( ! isset( $this->{$template_name} ) )
							$this->{$template_name} = $template;
					}
				}
			} else {
				throw new BadMethodCallException( 'set_templates() expects associative array of template definitions' );
			}
		}

	 	public function printForm($echo = true){
	 		$form = '';

	 		$form = '<form';
	 			$form .= echo_if_not_empty($this->id, ' id="%s"', false);
	 			$form .= echo_if_not_empty($this->name, ' name="%s"', false);
	 			$form .= echo_if_not_empty($this->method, ' method="%s"', false);
	 			$form .= echo_if_not_empty($this->action, ' action="%s"', false);
	 			$form .= echo_if_not_empty($this->form_class, ' class="%s"', false);
	 		$form .= '>';

	 		$form .= $this->content;

	 		// The Form action buttons...
	 		$form .= '<span class="'.self::FORM_BUTTONS_SPAN_CLASS.'">';
	 			$form .= '<input type="submit" name="SUBMIT" value="'.$this->savelabel.'" />';
	 		$form .= '</span>';

	 		$form .= "</form>";

	 		if ($echo){
	 			echo $form;
	 		} else {
	 			return $form;
	 		}
	 	}

	 	public function getForm(){
	 		return $this->printForm(false);
	 	}




	 	protected function generate_class_attribute($typeClass, $itemClass){
	 		// If a class has been defined for this item, add that;
			// if not, add the default class, if it was set
			$classes[] = select_non_empty($itemClass, $this->default_class);
			$classes[] = $typeClass;
			$classTag = implode(' ', array_filter($classes, create_function('$a', 'return $a!="";')));
			if ($classTag) return ' class="'.$classTag.'"';
	 	}

		protected function sanitize(&$data, $method){
			$method = select_non_empty( $method, $this->default_text_sanitization_method );

			switch ($method){
				case self::SANITIZE_DATA:
					$data = wp_kses_data( $data );
				case self::SANITIZE_POST:
					$data = wp_kses_post( $data );
				case self::SANITIZE_ALLOW_HTML:
					$data = balanceTags( $data, true );
					break;

				case self::SANITIZE_ALL:
					$data = wp_kses( $data, array() );
				default:
					$data = wp_kses_data( $data );
			}
		}

	 	/**
	 	 * Getters for read-only properties
	 	 */

		public function __get($prop){
			if ( property_exists(__CLASS__, $prop) || property_exists($this, $prop) ){
				if (in_array($prop, $this->_read_only)){
					return $this->{$prop};
				}

				// Anything with _template is read-only as well
				if (stripos(strrev("_template"), strrev($prop)) == 0){
					return $this->{$prop};
				}
			}
		}



	 	/**
		 * Merge user defined arguments into defaults array.
		 *
		 * This function is taken verbatim from WordPress'
		 * wp_parse_args, but just included here to avoid dependencies
		 *
		 * @param string|array $args Value to merge with $defaults
		 * @param array $defaults Array that serves as the defaults.
		 * @return array Merged user defined values with defaults.
		 */
		private function set_defaults( $args, $defaults = '' ) {
			if ( is_object( $args ) ){
				$r = get_object_vars( $args );
			} elseif ( is_array( $args ) ) {
				$r =& $args;
			} else {
				parse_str( $args, $r );
				if ( get_magic_quotes_gpc() )
					$r = stripslashes_deep( $r );
			}

			if ( is_array( $defaults ) ){
				return array_merge( $defaults, $r );
			} else {
				return $r;
			}
		}

		function get_submit_button() {
			$output = '<p class="submit">';
				$output .= '<input type="submit" name="SUBMIT" class="button button-primary" value="'.$this->savelabel.'" />';
			$output .= '</p>';
			return $output;
		}
	 }
