<?php

	require_once('../core/Valz_Webform.php');

	/**
	 * Extends Valz_Webform and provides convenience methods that depend on WordPress functions and classes.
	 * Used specifically for forms within metaboxes.
	 *
	 * Version 1.1
	 * If a meta field is left blank, update_post_meta_from_input will now completely delete that field, rather than
	 * store a blank meta value.
	 */
	class WP_Meta_Webform extends Valz_Webform {

		const META_PREFIX = '_zg_';

		private $post_id;

		/**
		 * Constructor function.
		 */
		function __construct($post_id = 0, $args = array()){
			$this->post_id = $post_id;

			$post_meta = get_post_custom($post_id);

			// we're only interested in metadata that's tagged with our prefix
			$our_meta = array();
			foreach ($post_meta as $key => $value){
				if (strpos($key, self::META_PREFIX) === 0){
					$key = substr($key, strlen(self::META_PREFIX));
					$our_meta[$key] = $value[0];
				}
			}

			/** @TODO if we have multiple meta values for the same key, we're screwed right now */
			$this->user_input = array_merge($our_meta, $_REQUEST);

			parent::__construct($args);
		}

		/**
		 * Overrides parent method to set up custom templates for this type of form
		 */
		protected function define_templates(){
			$this->template = '<p><label for="<!id>" class="valz_metabox"><!label></label><!field></p>';
			$this->raw_text_template = '<p><!html></p>';
			$this->header_template = '<h2><!label></h2>';
			$this->checkbox_template = '<p><!field> <label for="<!id>"><!label></p>';
		}



		/**
		 * $autop (Boolean) - allows you to enable wpautop on the content of this webform.
		 */
		public function update_post_meta_from_input( $post_id, $autop = false ){
			foreach ( $this->fields as $field ){
				$data = $_REQUEST[$field['id']];
				if( $autop ) $data = wpautop( $data );
				$this->sanitize( $data, $field['sanitization'] );

				// Removed: wp_kses_data( $data ) - data is removing html tags - changed to post
				update_post_meta( $post_id, self::META_PREFIX.$field['id'], wp_kses_post( $data ) );
			}
		}
	}
