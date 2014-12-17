webformz
========

WordPress back-end forms made easy.

## Valz::Webformz

At the core is Valz::Webformz, ironically at `/core/Valz_Webform.php`. This is a platform-agnostic PHP form building library (initially created in Perl, but ported a decade later to PHP - which explains some of the strange coding conventions used therein), that manages the creation of form elements, validation of form input, and faciliates form output and saving.

Form saving isn't handled directly by Valz::Webformz, but is expected to be handled on a platform-specific implementation in a subclass. This is where the WordPress-specific subclasses come in.

### Creating a Form

A `form` object both defines the form elements and their layout, as well as provides methods to output the form and handle user input for the form.

Create a new Valz::Webform object to instantiate a form:

```php
$args = array();
$form = new Valz_Webform( $args );
```

`$args` is optional. There are a vast number of configuration parameters that can be initialized into the object, or can be individually set on the form after instantiation. Review the list of `protected` instance variables on the `Valz_Webform` object to get a sense of what these options are.

### Adding a field to a Form

The next thing you'll do is add fields to a form. There are a wide array of field types available. The field type constants are defined at the head of the class, and include:

*	 	TEXT_TYPE
*	 	TEXTAREA_TYPE
*	 	CHECKBOX_TYPE
*	 	RADIO_BUTTON_TYPE
*	 	EMAIL_TYPE
*	 	URL_TYPE
*	 	DATE_TYPE
*	 	TIME_TYPE
*	 	FIELD_TYPE
*	 	CURRENCY_TYPE
*	 	HIDDEN_FIELD_TYPE
*	 	SELECT_TYPE
	 	
Fields are added in sequence to the form using `add_field( $args )`. `$args` is an associative array of field creation options. Valid options vary depending on the field type (for example a `SELECT_TYPE` can include an `items` parameter to initialize the list of <OPTIONS>). Refer to the documentation for a particular field type for further information. (ed: this documentation doesn't exist yet, so don't waste your time looking for it. But please consider creating it and submitting a pull request!)

``` php
// Create a new Valz::Webform object
$form = new Valz_Webform();
// Add a standard text input field
$form->add_field( "id" => "first_name", "label" => "First Name" );
// Add a field with a default value
$form->add_field( "id" => "role", "label" => "Your Role", "type" => TEXT_TYPE, "default_value" => "Developer" );
// Add a date field
$form->add_field( "id" => "birth_date", "label" => "Your Birthday", "type" => DATE_TYPE, "datepicker_type" => JQUERYUI_INLINE_DATEPICKER );
// Add a select pull-down field
$form->add_field( "id" => "favourite_colour", "label" => "Favourite Colour", "type" => SELECT_TYPE, "items" => array( "red", "green", "blue" ), "display" => TITLE_CASE, "select_text" => "Choose a favourite colour" );
```

### Outputting a form

To generate the entire form, including the `<form>` tag complete with `action` attribute, use `printForm()`:

``` php
// assumes $form has been created and all the fields have been defined:
$form->printForm();
```

If you want to manually handle the `<form>` tag and just want the HTML field list, then use `$form->content`

``` php
// assumes $form already defined.
echo $form->content;
```

### Multi-record Forms vs single-record Forms

Valz::Webform was originally created to handle tabular (multiple record) forms, as well as detail forms (single field per row). This is controlled via the form's `$mode` parameter. The default is `web_form`, which creates a standard, TABLE-less HTML5 web form, one input per line.

To get multi-record, set `$mode` to `row_per_record`:

``` php
// Create a multi-record form
$multiform = new Valz_Webform( array( "mode" => 'row_per_record' ) );
```

### Handling user input

User input usually comes in from `$_POST` or `$_GET`. You pass this to a form's `$user_input` parameter either at instantiation, or field-by-field using `$form->set_user_input()`. Generally, the former is the way to go.

``` php
// instantiate a form, and give it access to the user input from $_POST
$form = new Valz_Webform( array( "user_input" => $_POST ) );
```

Doing this allows the form to display the user input in the appropriate fields when you redisplay the form.
