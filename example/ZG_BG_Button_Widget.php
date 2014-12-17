<?php

/**
 * Class: ZG_BGButtonWidget
 * 
 * Creates a background button
 * styled using CSS
 *
  * @version 1.3
 * 
 * @changelog
 * 1.3
 * - Added include title in Link checkbox
 * 
 * 1.2
 * - Updated to wrap button in a link tag.
 * 
 * 1.1
 * - Updated button text link to accept HTML
 * 
 */
class ZG_BG_Button_Widget extends ZG_Widget {
    const TD = 'zg-bg-button-widget-textdomain';

    function __construct() {
	   parent::__construct('ZG Background and Button Widget', 'This widget displays a button with an optional background image and a text area');
   }

   public function widget($args, $instance) {
		extract($args, EXTR_SKIP);
		//print_r( $args );
		// Display the custom stylsheet for this widget
		echo '<style type="text/css" name="' . $widget_id . '">';
		echo "#$widget_id {";
			echo "background:url(".  get_stylesheet_directory_uri() . '/' . $instance['bg_url'] . ') no-repeat top left;';
			if ( $instance['bg_width'] ) echo 'width:' . $instance['bg_width'].'px;';
			if ( $instance['bg_height'] ) echo 'height:'.$instance['bg_height'].'px;';
		echo '}';

		echo '</style>';

		// display the widget
		echo $this->add_classname( $instance['title'], $before_widget );

		
		// default the url
		$url = esc_url( $instance['link_url'] );
		// make it an absolute link if it is relative
		if ( $instance['local_link'] ) $url = home_url( $url );
		
		$target = '';
		if( isset( $instance['new_target'] ) ) $target = ' target="_new"';
		
		if ( ! empty($instance['title_in_link'] ) ) {
		    echo '<a href="'.$url.'"' . $target . '>';
		}
		
		if (! $instance['hide_title']){
			echo $before_title . $instance['title'] . $after_title;
		}

		// display the text
		echo '<div class="background-text">', $instance['textarea_text'], '</div>';

		// default the url
		$url = $instance['link_url'];
		// make it an absolute link if it is relative
		if ($instance['local_link']) $url = home_url($url);
		if(isset($instance['new_target'])) $target = ' target="_new" ';

		echo '<a href="'.$url.'"'.$target.' class="button-link">';
			// Display the button text inside the anchor, or hide it and put it in the image's ALT tag instead
			if ( empty( $instance['btn_src'] ) ) echo $instance['button_text'];
			else echo '<img src="', get_stylesheet_directory_uri(), '/', $instance['btn_src'], '" alt="', $instance['btn_text'], '" />';
		echo '</a>';

		
		if ( ! empty($instance['title_in_link'] ) ) {
		    echo '</a>';
		}
		
		echo $after_widget;
	}

   protected function display_widget_content($options){
	 // Do nothing - and not used.
   }

   protected function update_admin_options($new_instance, $old_instance){
	   $instance = $old_instance;

	   $instance['local_link'] = $new_instance['local_link'];
	   if ($instance['local_link']){
		   $url = wp_kses($new_instance['link_url']);
	   } else {
		   // add http:// to url if needed
		   $url = esc_url($new_instance['link_url']);
	   }
	   $instance['link_url'] = $url;
	   
	   $instance['title_in_link'] = isset( $new_instance['title_in_link'] ) ? true : false;
	   $instance['new_target']  = $new_instance['new_target'];
	   $instance['textarea_text'] = wp_kses_post( $new_instance['textarea_text'] );
	   $instance['btn_text'] = wp_kses_data($new_instance['btn_text']);
	   $instance['btn_src'] = wp_kses($new_instance['btn_src'], array() );
	   $instance['bg_url'] = wp_kses( $new_instance['bg_url'], array() );
	   $instance['bg_width'] = $new_instance['bg_width'] ? intval( $new_instance['bg_width'] ) : '';
	   $instance['bg_height'] = $new_instance['bg_height'] ? intval( $new_instance['bg_height'] ) : '';

	   return $instance;
   }

   protected function add_admin_form_content( $form, $options = array() ){
	   $form->addField(array('id'=>'link_url', 'label'=>__('Link (URL)', ZG_BG_Button_Widget::TD ), 'class'=>'widefat'));
	   
	   $form->addField( array( 'id' => 'title_in_link', 'label' => __( 'Include Widget Title in Link', ZG_BG_Button_Widget::TD ), 'type' => 'checkbox' ) );
	   
	   $form->addField(array('id'=>'local_link', 'type'=>'checkbox', 'label'=>__('Local Link?', ZG_BG_Button_Widget::TD ), 'checkbox_value'=>'true'));
	   $form->addField(array('id'=>'new_target', 'label'=>__('Open link in a new window/tab', ZG_BG_Button_Widget::TD ), 'type'=>'checkbox'));

	   $form->addField(array('id'=>'textarea_text', 'type'=>'textarea', 'label'=>__('Background Text (kses-data tags allowed)', ZG_BG_Button_Widget::TD ), 'class'=>'widefat'));

	   $form->addField(array('id'=>'btn_text', 'label'=>__('Button Image Text', ZG_BG_Button_Widget::TD ), 'class'=>'widefat'));
	   $form->addField(array('id'=>'btn_src', 'label'=>__('(optional) Button Image URL', ZG_BG_Button_Widget::TD ), 'class'=>'widefat'));

	   $form->addField(array('id'=>'bg_url', 'label'=>__('(optional) Background Image URL', ZG_TEXTDOMAIN), 'class'=>'widefat'));
	   $form->addField(array('id'=>'bg_width', 'label'=>__('Widget Width', ZG_BG_Button_Widget::TD )));
	   $form->addField(array('id'=>'bg_height', 'label'=>__('Widget Height', ZG_BG_Button_Widget::TD )));
   }
}

// Register the widget (using an anonymous function closure)
add_action('widgets_init', create_function('', 'return register_widget("ZG_BG_Button_Widget");'));
