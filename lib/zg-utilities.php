<?php

	/*	Zeitguys utility functions
		V1.2 - GSN Profs
	*/

	define('MAX_RECURSION_DEPTH', 3);

	/* WordPress utility functions */

	/**
	 * Returns true if the supplied id is a child of the given post.
	 * @param int $child_id the ID of the child we're querying
	 * @param int $parent_id the ID of the parent we're testing against
	 * @param bool $depth the amount of recursion we're going to do (0 = infinite, up to MAX_RECURSION_DEPTH)
	 * @returns bool true if the supplied id is a child of the given post.
	 */
	function is_child_of_post($child_id, $parent_id, $depth = 0){
		if (in_array($child_id, get_child_ids($parent_id, $depth))) return true;
		return false;
	}

	function get_child_ids($parent_id, $max_depth, $complete_child_list = array(), $current_depth = 1){
		// We need the post type for our query
		$parent_post = get_post($parent_id);


		// get a list of children for this parent
		global $wpdb;
		$child_ids = $wpdb->get_col(
			$wpdb->prepare("
				select id
				from wp_posts
				where
				    post_parent = %d
				    and post_status = 'publish'
				    and post_type = %s
				order by id;
			", $parent_id, $parent_post->post_type)
		);

		foreach ($child_ids as $child_id){
			if (! in_array($child_id, $complete_child_list)) $complete_child_list[] = $child_id;

			// Now recurse if we have headroom
 			if ($current_depth < MAX_RECURSION_DEPTH && ($max_depth == 0 || $current_depth < $max_depth)){
 				$complete_child_list += get_child_ids($child_id, $max_depth, $complete_child_list, $current_depth + 1);
 			}
		}

		return $complete_child_list;
	}


	/**
	 * Adds one or more capabilities to one or more roles.
	 * @param mixed $capability a string or array of the capability name(s) we're adding
	 * @param mixed $roles a string or array of roles to be added
	 */
	function add_cap_to_roles($capabilities, $roles){
		$caps_array = array();
		if (is_array($capabilities)) $caps_array = $capabilities;
		else $caps_array[] = $capabilities;

		$roles_array = array();
		if (is_array($roles)) $roles_array = $roles;
		else $roles_array[] = $roles;

		foreach ($caps_array as $capability){
			foreach ($roles_array as $role_name){
				$role = get_role($role_name);
				if ($role){
					$role->add_cap($capability);
				}
			}
		}
	}


	/* Generic PHP functions, cause I miss Perl!!! */
	function set_if_defined(&$var, $test){
		if (isset($test)){
			$var = $test;
			return true;
		} else {
			return false;
		}
	}


	function select_defined(){
		$l = func_num_args();
		$a = func_get_args();
		for ($i=0; $i<$l; $i++){
			// Safe form (array of hash/key)
			if ( is_array( $a[$i] ) ){
				list( $ary, $key ) = array_slice( $a[$i], 0, 2 );
				if ( is_array( $ary ) ){
					if ( isset( $ary[$key] ) )
						return $ary[$key];
				} else {
					throw new BadMethodCallException( "Calling select_defined() with array form expects first element to be an array." );
				}
			} else {
				// Not safe form (may throw Notice)
				if ( isset($a[$i]) ) return $a[$i];
			}
		}
	}


	function select_non_empty(){
		$l = func_num_args();
		$a = func_get_args();
		for ($i=0; $i<$l; $i++){
			// Safe form (array of hash/key)
			if ( is_array( $a[$i] ) ){
				list( $ary, $key ) = array_slice( $a[$i], 0, 2 );
				if ( is_array( $ary ) ){
					if ( isset( $ary[$key] ) )
						if ( ! empty( $ary[$key] ) ) return $ary[$key];
				} else {
					throw new BadMethodCallException( "Calling select_non_empty() with array form expects first element to be an array." );
				}
			} else {
				if (! empty($a[$i])) return $a[$i];
			}
		}
	}

	// only works for public or read-only properties
	function select_if_declared( $object, $property ){
		if ( property_exists( $object, $property ) ){
			return $object->{$property};
		}
	}

	/**
	 * Pass a $test_variable to this function to have it echo or return the value
	 * only if it is not empty. Extremely useful for outputting content (and labels
	 * for that content) only if the content is there.
	 *
	 * @param string $test_variable The scalar variable to test
	 * @param string $sprintf_string (optional) a sprintf-style formatting string that can be applied to wrap HTML and text around the variable
	 * @param boolean $echo (optional) default = true. Whether to actually echo and return the resulting output, or simply return it without echoing
	 * @return string
	 */
	function echo_if_not_empty($test_variable, $sprintf_string = null, $echo = true){
		if (! empty($test_variable)){
			if ($sprintf_string){
				if ($echo) printf($sprintf_string, $test_variable);
				else return sprintf($sprintf_string, $test_variable);
			} else {
				if ($echo) echo $test_variable;
				return $test_variable;
			}
		}
	}

	/**
	 * Use this to add default initialization values to any associative array of arguments.
	 * @param array $args the argument list that will be filtered
	 * @param array $defaults list of key-value pairs identifying default arguments and their values
	 * @param bool $exclusive if this is set, it will ONLY allow arguments for which a default value has been specified, all others are stripped out.
	 * @return array
	 */
	function set_defaults( $args, $defaults = array(), $exclusive = false ) {
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
			if ($exclusive) return array_intersect($defaults, $r);
			return array_merge( $defaults, $r );
		} else {
			return $r;
		}
	}

	/**
	 * Adds classes to an item based on the $index value provided. Useful for creating dynamic columns.
	 * If the element for which this function is called happens to fall at the END of a column,
	 * then the appropriate class will be called. You would use this class in your CSS in order
	 * to be able to do something like <code>clear: right;</code>. These classes also make it easy to re-arrange
	 * the number of columns using JavaScript without having to iterate through the elements and perform any calculations.
	 *
	 * @param int $index The number (offset 1) of the item. Eg: in a list of 10 items, the 10th item has index 10.
	 * @param boolean $generate_class_attribute default: true. Whether to wrap the output inside <code>class=""</code>
	 * @param boolean $echo default: true. Whether to echo the result or merely return it
	 * @return string List of classes, optionally wrapped in <code>class=""</code> attribute.
	 */
	function generate_column_classes( $index, $generate_class_attribute = true, $echo = true ){
		$content = 'item-number-' .$index;

		for ( $i=2; $i<10; ++$i ){
			if ( $index % $i == 0 ) $content .= ' column-member-' . $i;
			if ( $index > 1 && ($index - 1) % $i == 0 ) $content .= ' follows-column-' . $i;
		}

		if ( $generate_class_attribute ) $content = 'class="' . $content . '"';

		if ( $echo ) echo $content;
		return $content;
	}


	function trace($alert, $level = 'zg_trace', $tag = 'h1'){
		$alert = attribute_escape($alert);
		echo '<', $tag, ' class="', $level, '">', $alert, '</', $tag, '>';
	}

	function croak($error, $tag = 'h1'){
		echo '<', $tag, ' class="zg_error">', $error, '</', $tag, '>';
		apd_croak($error);
	}



	function get_input_field_IDs_by_prefix($prefix){
		return array_map(
			//create_function('$a', 'return substr($a, strlen('.$prefix.'));'),
			array(new GenericCallbackWrapper($prefix), 'strip_prefix'),
			preg_grep('/^'.$prefix.'/', array_keys($_REQUEST))
		);
	}

	class GenericCallbackWrapper {
		function __construct (){
			$argIndex = 1;
			foreach(func_get_args() as $value){
				$argName = 'user' . $argIndex;
				$this->$argName = $value;
			}
		}

		public function strip_prefix($a){
			return substr($a, strlen($this->user1));
		}
	}

	//function get_the_terms_by_field($post_id, $taxonomy_name, $field
	//$term_ids = array_map(function($a){return $a->term_id;}, get_the_terms($post->ID, TAXONOMY_NAME));


	/**
	 * Inserts any number of scalars or arrays at the point
	 * in the haystack immediately after the search key ($needle) was found,
	 * or at the end if the needle is not found or not supplied.
	 * Modifies $haystack in place.
	 * @param array &$haystack the associative array to search. This will be modified by the function
	 * @param string $needle the key to search for
	 * @param mixed $stuff one or more arrays or scalars to be inserted into $haystack
	 * @return int the index at which $needle was found
	 */
	function array_insert_after(&$haystack, $needle = '', $stuff){
		if (! is_array($haystack) ) return $haystack;

		$new_array = array();
		for ($i = 2; $i < func_num_args(); ++$i){
			$arg = func_get_arg($i);
			if (is_array($arg)) $new_array = array_merge($new_array, $arg);
			else $new_array[] = $arg;
		}

		$i = 0;
		foreach($haystack as $key => $value){
			++$i;
			if ($key == $needle) break;
		}

		$haystack = array_merge(array_slice($haystack, 0, $i, true), $new_array, array_slice($haystack, $i, null, true));

		return $i;
	}





	/**
	 * Filters content based on specific parameters, and appends a "read more" link if needed.
	 * Based on the "Advanced Excerpt" plugin by Bas van Doren - http://sparepencil.com/code/advanced-excerpt/
	 *
	 * @since 1.0
	 *
	 * @param string $content What to filter, defaults to get_the_content(); should be left empty if we're filtering post content
	 * @param array $args Optional arguments (limit, allowed tags, enable/disable shortcodes, read more link)
	 * @return string Filtered content
	 */
	function atom_filter_content($content = NULL, $args = array()){

	  $args = wp_parse_args($args, array(
	      'limit' => 55,
	      'allowed_tags' => array('a', 'abbr', 'acronym', 'address', 'b', 'big', 'blockquote', 'cite', 'code', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'ins', 'li', 'ol', 'p', 'pre', 'q', 'small', 'span', 'strong', 'sub', 'sup', 'tt', 'ul'),
	      'shortcodes' => false,
	      'more' => '<a href="'.get_permalink().'" class="more-link">'.__('More &gt;').'</a>',
	    ));

	  extract(apply_filters('atom_content_filter_args', $args, $content), EXTR_SKIP);

	  if(!isset($content)) $text = get_the_content(); else $text = $content;
	  if(!$shortcodes) $text = strip_shortcodes($text);

	  if(!isset($content)) $text = apply_filters('the_content', $text);

	  // From the default wp_trim_excerpt():
	  // Some kind of precaution against malformed CDATA in RSS feeds I suppose
	  $text = str_replace(']]>', ']]&gt;', $text);

	  // Strip HTML if allow-all is not set
	  if(!in_array('ALL', $allowed_tags)):
	    if(count($allowed_tags) > 0) $tag_string = '<'.implode('><', $allowed_tags).'>'; else $tag_string = '';
	    $text = strip_tags($text, $tag_string); // @todo: find a way to use the function above (strip certain tags with the content between them)
	  endif;

	  // Skip if text is already within limit
	  if($limit >= count(preg_split('/[\s]+/', strip_tags($text)))) return $text;

	  // Split on whitespace and start counting (for real)
	  $text_bits = preg_split('/([\s]+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	  $in_tag = false;
	  $n_words = 0;
	  $text = '';
	  foreach($text_bits as $chunk):
	    if(!$in_tag || strpos($chunk, '>') !== false) $in_tag = (strrpos($chunk, '>') < strrpos($chunk, '<'));

	    // Whitespace outside tags is word separator
	    if(!$in_tag && '' == trim($chunk)) $n_words++;

	    if($n_words >= $limit && !$in_tag) break;
	    $text .= $chunk;
	  endforeach;

	  $text = trim(force_balance_tags($text));

	  if($more):
	    $more = " {$more}";
	    if(($pos = strpos($text, '</p>', strlen($text) - 7)) !== false):
	      // Stay inside the last paragraph (if it's in the last 6 characters)
	      $text = substr_replace($text, $more, $pos, 0);
	    else:

	     // If <p> is an allowed tag, wrap read more link for consistency with excerpt markup
	     if(in_array('ALL', $allowed_tags) || in_array('p', $allowed_tags))
	       $more = "<p>{$more}</p>";
	       $text = $text.$more;
	     endif;
	  endif;
	  return $text;
	}






	/**
	 * Generates an excerpt from the content, if needed.
	 *
	 * The excerpt word amount will be 55 words and if the amount is greater than
	 * that, then the string ' [...]' will be appended to the excerpt. If the string
	 * is less than 55 words, then the content will be returned as is.
	 *
	 * The 55 word limit can be modified by plugins/themes using the excerpt_length filter
	 * The ' [...]' string can be modified by plugins/themes using the excerpt_more filter
	 *
	 * Use the $excerpt_more argument to determine what text will be appended to excerpts longer than excerpt_length.
	 * You could, for example, set this value to apply_filters('excerpt_more', ' [...]') if you want to leverage
	 * your theme's built-in "read more" link (provided the theme sets this using add_filter('excerpt_more', ...) ).
	 *
	 * @since 1.5.0
	 *
	 * @param string $text The excerpt. If set to empty an excerpt is generated.
	 * @return string The excerpt.
	 */
	function zg_trim_excerpt($postObj, $excerpt_more = ' [...]') {
		$raw_excerpt = $text = $postObj->post_excerpt;
		if ( '' == $text ) {
			$text = $postObj->post_content;

			$text = strip_shortcodes( $text );

			$text = apply_filters('the_content', $text);
			$text = str_replace(']]>', ']]&gt;', $text);
			$text = strip_tags($text);
			$excerpt_length = apply_filters('excerpt_length', 55);

			// don't automatically assume we will be using the global "read more" link provided by the theme
			// $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
// Replaced by wp_trim_words()
//			$words = preg_split("/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY);
//			if ( count($words) > $excerpt_length ) {
//				array_pop($words);
//				$text = implode(' ', $words);
//				$text = $text . $excerpt_more;
//			} else {
//				$text = implode(' ', $words);
//			}

			$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );
		}
		return apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
	}



	/** Recursive in_array search
	 *
	 *
	 * @param mixed $needle
	 * @param array $haystack
	 * @param boolean $strict
	 * @return boolean
	 */
	function in_array_recursive( $needle, $haystack, $strict = false ) {
		foreach ( $haystack as $hay ) {
			if ( $strict ) {
				if ( $needle === $hay )
					return true;
			} else {
				if ( $needle == $hay )
					return true;
			}
			if ( is_array ( $hay ) )
				if( in_array_recursive( $needle, $hay, $strict ) )
					return true;
		}

		return false;
	}


	/* Flattens multi-dimensional array
	 * http://stackoverflow.com/a/1320156/1738589
	 */
	function flatten_array( array $array ) {
		$return = array();
		array_walk_recursive( $array, function($a) use ( &$return ) { $return[] = $a; } );
		return $return;
	}


	/**
	 * Gets all items in wp_options containing a wildcard.
	 *
	 *
	 * @param string $wildcard - the wildcard (common substring)
	 * @param string $position (|prefix|suffix) - default is 'anywhere'
	 */
	function get_options_wildcard( $wildcard, $position = '' ) {
		global $wpdb;
		switch( $position ) {
			case 'prefix':
				$wildcard_string = "$wildcard%";
				break;
			case 'suffix':
				$wildcard_string = "%$wildcard";
				break;
			default:
				$wildcard_string = "%$wildcard%";
		}

		$query = $wpdb->prepare(
			"SELECT option_name, option_value
			FROM $wpdb->options
			WHERE option_name LIKE %s",
			$wildcard_string
		);
		$options = $wpdb->get_results( $query );

		$data = array();
		foreach( $options as $option ) {
			$data[$option->option_name] = $option->option_value;
		}
		return $data;
	}



	/**
	 * This function is passed an object (post), and adds on any meta information as properties to the object.
	 *
	 * @param type $object
	 * @param type $common_exclude
	 * @return type
	 */
	function attach_meta_to_object( $object, $common_exclude = '', $single = true ) {
		$post_meta = get_post_meta( $object->ID );
		// we're only interested in metadata that's tagged with our prefix
		foreach ($post_meta as $key => $value){
			if (strpos($key, WP_Meta_Webform::META_PREFIX) === 0){
				$key = substr($key, strlen(WP_Meta_Webform::META_PREFIX));

				// Strip out event_ in front of the key, just to be more concise
				if( $common_exclude )
					$key = preg_replace("/^$common_exclude/", '', $key);
				if( $single )
					$object->$key = $value[0];
				else
					$object->$key = $value;
			}
		}
		return $object;
	}


/* This function handles setting up Date archive rewrite rules for
 * AND custom post type - You pass the CPT, and it will use the
 * re-written slug if applicable.
 */
function zg_generate_date_archives( $cpt, $wp_rewrite ) {
	$rules = array();
	$post_type = get_post_type_object( $cpt );
	$slug_archive = $post_type->has_archive;
	if ( $slug_archive === false ) return $rules;
	if ( $slug_archive === true ) {
		$slug_archive = $post_type->rewrite['slug'] ? $post_type->rewrite['slug'] : $post_type->name;
	}
	$dates = array(
		array(
			'rule' => "([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})",
			'vars' => array( 'year', 'monthnum', 'day' )
		),
		array(
			'rule' => "([0-9]{4})/([0-9]{1,2})",
			'vars' => array( 'year', 'monthnum' )
		),
		array(
			'rule' => "([0-9]{4})",
			'vars' => array( 'year' )
		)
	  );
	foreach ($dates as $data) {
		$query = 'index.php?post_type='.$cpt;
		$rule = $slug_archive.'/'.$data['rule'];

		$i = 1;
		foreach ( $data['vars'] as $var ) {
			$query.= '&'.$var.'='.$wp_rewrite->preg_index($i);
			$i++;
		}
		$rules[$rule."/?$"] = $query;
		$rules[$rule."/feed/(feed|rdf|rss|rss2|atom)/?$"] = $query."&feed=".$wp_rewrite->preg_index($i);
		$rules[$rule."/(feed|rdf|rss|rss2|atom)/?$"] = $query."&feed=".$wp_rewrite->preg_index($i);
		$rules[$rule."/page/([0-9]{1,})/?$"] = $query."&paged=".$wp_rewrite->preg_index($i);
	}
	return $rules;
}

/**
 * This allows us to generate any archive link - plain, yearly, monthly, daily
 *
 * @param string $post_type
 * @param int $year
 * @param int $month (optional)
 * @param int $day (optional)
 * @return string
 */
function zg_get_post_type_date_link( $post_type, $year, $month = 0, $day = 0 ) {
	global $wp_rewrite;
	$post_type_obj = get_post_type_object( $post_type );
	$post_type_slug = $post_type_obj->rewrite['slug'] ? $post_type_obj->rewrite['slug'] : $post_type_obj->name;
	if( $day ) { // day archive link
		// set to today's values if not provided
		if ( !$year )
			$year = gmdate('Y', current_time('timestamp'));
		if ( !$month )
			$month = gmdate('m', current_time('timestamp'));
		$link = $wp_rewrite->get_day_permastruct();
	} else if ( $month ) { // month archive link
		if ( !$year )
			$year = gmdate('Y', current_time('timestamp'));
		$link = $wp_rewrite->get_month_permastruct();
	} else { // year archive link
		$link = $wp_rewrite->get_year_permastruct();
	}
	if ( !empty($link) ) {
		$link = str_replace('%year%', $year, $link);
		$link = str_replace('%monthnum%', zeroise(intval($month), 2), $link );
		$link = str_replace('%day%', zeroise(intval($day), 2), $link );
		return home_url( "$post_type_slug$link" );
	}
	return home_url( "$post_type_slug" );
}



/**
 * This function adds existing query vars onto a link passed to it.
 * essentially add_query_arg() but using current values in the URL.
 * @param string $link
 * @param array $query_vars - array of query_var keys
 * @return string
 */
function add_link_query_args( $link, $query_vars = array() ) {
	$query_args = array();
	foreach( $query_vars as $var ) {
		$var_value = get_query_var( $var );
		if( is_array( $var_value ) ) {
			foreach( $var_value as $value) {
				$query_args[] = "$var%5B%5D=$value";
			}
		} else if( $var_value ) {
			$query_args[] = "$var=$var_value";
		}
	}
	if( ! strpos( $link, '?' ) && ! empty( $query_args ) )
		  $link .= '?';
	return $link . implode( "&amp;", $query_args );
}


/**
 * Get the id of the topmost parent of this post, or this post.
 * Very useful for things like wp_list_pages() etc.
 *
 * @uses get_post() To "sanitize" the input argument, or get the current post.
 * @param mixed $post_or_id (optional) Pass a $post object or just a $post_id. If left out, will attempt to use the current post
 * @return int Topmost post ID, or null.
 */
function zg_get_top_parent_id( $post_or_id = null ) {
	// Will get the current post if no post or id is provided. Will use cache if possible.
	$post = get_post( $post_or_id );

	if ( ! is_null( $post ) ){
		$parents = get_post_ancestors( $post->ID );

		/* get the topmost parent, which could be the current post */
		$id = $parents ? $parents[count($parents)-1] : $post->ID;

		return $id;
	}
}



/**
 * This function allows us to create additional menu separators.
 *
 * From: http://wordpress.stackexchange.com/questions/2666/add-a-separator-to-the-admin-menu
 *
 * @global array $menu
 * @param int $position - used as the menu position (default is ~26, below comments (25)
 */
function add_admin_menu_separator( $position ) {
	global $menu;
	if( empty($menu) || !is_array($menu))
		return;
	$index = 0;
	foreach($menu as $offset => $section) {
		if (substr($section[2],0,9)=='separator')
			$index++;
		if ($offset>=$position) {
			$menu[$position] = array('','read',"separator{$index}",'','wp-menu-separator');
			break;
		}
	}
	ksort( $menu );
}



/* Multisite
 ---------------------------------------------------------------------------- */

/**
 * This function will do what switch_to_blog should - check first for the
 * possibility of already being on the blog that we are trying to swtich to, and
 * preventing double switch, which will break restore_current_blog();
 * @global type $blog_id
 * @param type $new_blog_id
 */
function maybe_switch_to_blog( $new_blog_id ) {
	global $blog_id;
	if( $blog_id !== $new_blog_id )
		switch_to_blog( $new_blog_id );
}