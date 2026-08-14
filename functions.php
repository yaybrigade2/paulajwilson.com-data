<?php
/*  ******************************************** */
/*  paulawilsondata THEME
/*  ******************************************** */


/*  ******************************************** */
/*  ANCHOR: REST endpoints for WORKS
*/

function paulawilsondata_get_work_type_map() {
	return array(
		'exhibition' => 'exhibition',
		'painting' => 'painting',
		'sculpture' => 'sculpture',
		'film' => 'film',
		'edition' => 'edition',
	);
}

function paulawilsondata_prepare_work_data( $post_id, $include_fields_for_detail = false ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		return null;
	}

	$work = array(
		'id' => (int) $post_id,
		'type' => $post->post_type,
		'slug' => $post->post_name,
		'title' => $post->post_title,
		'displayDate' => get_field( 'display_date', $post_id ),
		'sortDate' => get_field( 'sort_date', $post_id ),
		'featuredImage' => get_field( 'featured_image', $post_id ),
		'featuredVideo' => get_field( 'featured_video', $post_id ),
		'location' => get_field( 'location', $post_id ),
	);

	if ( $include_fields_for_detail ) {
		$work['contentModules'] = get_field( 'content_modules', $post_id );
	}

	return $work;
}

// Works Endpoint 
// https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/works

function rest_works( $request ) {
	$type_map = paulawilsondata_get_work_type_map();
	$post_types = array_values( $type_map );

	$args = array(
		'post_type' => $post_types,
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'orderby' => 'meta_value',
		'meta_key' => 'sort_date',
		'order' => 'DESC',
	);

	$works_query = new WP_Query( $args );
	$works = array();

	if ( $works_query->have_posts() ) {
		while ( $works_query->have_posts() ) {
			$works_query->the_post();
			$work = paulawilsondata_prepare_work_data( get_the_ID(), false );

			if ( $work ) {
				$works[] = $work;
			}
		}
	}

	wp_reset_postdata();

	return $works;
}

// Work Detail Endpoint
// https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/works/exhibition/we-dream-of-life-iris-hu-and-paula-wilson
function rest_work( $request ) {
	$type_map = paulawilsondata_get_work_type_map();
	$type = sanitize_key( $request['type'] );
	$slug = sanitize_title( $request['slug'] );

	if ( ! isset( $type_map[ $type ] ) ) {
		return new WP_Error( 'rest_invalid_type', 'Invalid work type.', array( 'status' => 404 ) );
	}

	$args = array(
		'post_type' => $type_map[ $type ],
		'name' => $slug,
		'posts_per_page' => 1,
		'post_status' => 'publish',
	);

	$work_query = new WP_Query( $args );

	if ( ! $work_query->have_posts() ) {
		return new WP_Error( 'rest_work_not_found', 'Work not found.', array( 'status' => 404 ) );
	}

	$work_query->the_post();
	$work = paulawilsondata_prepare_work_data( get_the_ID(), true );
	wp_reset_postdata();

	return $work;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'paulawilsondata/v1', '/works', array(
		'methods' => 'GET',
		'callback' => 'rest_works',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'paulawilsondata/v1', '/works/(?P<type>[\w-]+)/(?P<slug>[\w-]+)', array(
		'methods' => 'GET',
		'callback' => 'rest_work',
		'permission_callback' => '__return_true',
		'args' => array(
			'type' => array(
				'required' => true,
			),
			'slug' => array(
				'required' => true,
			),
		),
	) );
} );


/*  ******************************************** */
/*  ANCHOR REST for PAGE DETAIL
/*  https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/page/test
*/
function rest_page( $data ) {
	
	global $post;

	$slug = $data['slug'];
	
	$args = [
		'post_type' => 'page',
		'name' => $slug,
	];	
	$page_query = new WP_Query($args);

	if ( $page_query->have_posts() ) : 

		if ( $page_query->have_posts() ) : $page_query->the_post(); 

			$id = $post->post_name; /* slug */
			$title = $post->post_title;
			$content_modules = get_field('content_modules');			

			// PUT IT ALL TOGETHER
			$page = array(
				'id' => $id,
				'title' => $title,
				'contentModules' => $content_modules,
			);
			
			$jsonObj = $page;
			return $jsonObj;
			
		endif; 

	endif;

}
add_action( 'rest_api_init', function () {
  register_rest_route( 'paulawilsondata/v1', '/page/(?P<slug>[\w-]+)', array(
	'methods' => 'GET',
	'callback' => 'rest_page',
	'permission_callback' => '__return_true',
	'args' => [
        'slug'
    ],	
  ));
});


/*  ******************************************** */
/*  ANCHOR REST for SEARCH
/*  Example: https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/search/paula
*/
function rest_search( $data ) {
    
    global $post;
    
    $search = $data['searchterm'];
    
    $custom_query = new WP_Query();

    $custom_query->query_vars['s'] = $search;
    $custom_query->query_vars['posts_per_page'] = -1; // all

    relevanssi_do_query($custom_query);

    $results = [];

    if ( $custom_query->have_posts() ) : 

       foreach ( $custom_query->posts as $post ) :
        	
			$section = ucwords($post->post_type);
			$path = $post->post_name;

			if ('page' != $post->post_type) {
				$path = $post->post_type . 's/' . $path;
			}
			// check if this is the homepage
			if ( 'paula-wilson' == $path ) {
				$section = 'Homepage';
				$path = '/';
			}

			if ( is_front_page($post->ID) ) {
				$section = 'Homepage';
			}
		
            $result = array(
                'name' => $post->post_name, /* slug */
				'title' => $post->post_title,
				'path' => $path,
				'posttype' => $post->post_type,
				'section' => $section,
				'excerpt' => $post->post_excerpt
            );
                
            array_push($results, $result);

        endforeach;

    endif;

	return $results;
}
add_action( 'rest_api_init', function () {
  register_rest_route( 'paulawilsondata/v1', '/search/(?P<searchterm>.+)', array(
    'methods' => 'GET',
    'callback' => 'rest_search',
  ) );
} );


/*  ******************************************** */
/*  ANCHOR REST for sitemap URLs
/*  Example: https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/urls
*/
function rest_urls( $data ) {
    
    global $post;

	$url_prefix = 'https://www.paulajwilson.com';

	$results = [];

	// get all designers
	$args = array(
		// all post types
		'post_type' => 'any',
		'posts_per_page' => -1,
		'post_status' => 'publish',
	);

	$pages = get_posts($args);

	foreach ($pages as $page) {

		$path = $page->post_name;

		if ('page' != $page->post_type) {
			$path = $page->post_type . 's/' . $path;
		}
		// check if this is the homepage
		if ( 'paula-wilson' == $path ) {
			$path = '';
		}

		$result = $url_prefix . '/' . $path;
		array_push($results, $result);
	}

	return $results;
}
add_action( 'rest_api_init', function () {
  register_rest_route( 'paulawilsondata/v1', '/urls', array(
    'methods' => 'GET',
    'callback' => 'rest_urls',
	'permission_callback' => '__return_true',
  ) );
} );


/**
 * REST CACHING
 * 
 * Register Custom endpoints to be cached
 */
function wprc_add_custom_endpoints( $allowed_endpoints ) {
	// /wp-json/paulawilsondata/v1/works
	if ( ! isset( $allowed_endpoints[ 'paulawilsondata/v1' ] ) || ! in_array( 'works', $allowed_endpoints[ 'paulawilsondata/v1' ] ) ) {
		$allowed_endpoints[ 'paulawilsondata/v1' ][] = 'works';
	}
	return $allowed_endpoints;
}
add_filter( 'wp_rest_cache/allowed_endpoints', 'wprc_add_custom_endpoints', 10, 1);

/**
 * Flush REST cache
 */
function paulawilsondata_flush_rest() {
	// TODO: Add other endpoints to flush as needed
	if( is_plugin_active( 'wp-rest-cache/wp-rest-cache.php' ) ) {
		// https://wordpress.org/support/topic/how-to-flush-cache-on-custom-endpoints/
		\WP_Rest_Cache_Plugin\Includes\Caching\Caching::get_instance()->delete_cache_by_endpoint( '/data/wp-json/paulawilsondata/v1/works', 'strict', false );
	}
}
add_action( 'save_post',	'paulawilsondata_flush_rest' );
add_action( 'trashed_post',	'paulawilsondata_flush_rest' );
add_action( 'deleted_post',	'paulawilsondata_flush_rest' );





/*  ******************************************** */
/*  Image Size Presets 
*/
function paulawilsondata_filter_image_sizes( $sizes) { /* Deactivate some default sizes we don't need */
    unset( $sizes['large']);

    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'paulawilsondata_filter_image_sizes');


/*  ******************************************** */
/*  Custom formatting tags for admin editor
*/
function paulawilsondata_formatTinyMCE($in) {
	$in['block_formats'] = "Paragraph=p;Header=h2;Sub Header=h3";
	return $in;
  }
add_filter('tiny_mce_before_init', 'paulawilsondata_formatTinyMCE' );
  

/*  ******************************************** */
/*  Add a 'Very Simple' WYSIWYG option to ACF
*/
function paulawilsondata_WYSIWYG_toolbars( $toolbars )
{
	// Uncomment to view format of $toolbars
	/*
	echo '< pre >';
		print_r($toolbars);
	echo '< /pre >';
	die;
	*/

	// New toolbar: "Very Simple"
	$toolbars['Very Simple' ] = array();
	$toolbars['Very Simple' ][1] = array('bold' , 'italic'); // [1]=this toolbar has only 1 row of buttons
	
	// New toolbar: "Very Simple with Link"
	$toolbars['Very Simple with Link' ] = array();
	$toolbars['Very Simple with Link' ][1] = array('bold' , 'italic', 'link', 'unlink');

	// New toolbar: "CV"
	$toolbars['CV' ] = array();
	$toolbars['CV' ][1] = array('italic', 'link', 'unlink', 'bullist');

	return $toolbars;
}
add_filter( 'acf/fields/wysiwyg/toolbars' , 'paulawilsondata_WYSIWYG_toolbars'  );


/*  ******************************************** */
/*  Remove Media Attachement fields from backend (Image title, caption, and description)
*/
function paulawilsondata_remove_media_attachement_fields() {
	echo '<style type="text/css">
			.setting[data-setting=title] {display:none !important;}
			.setting[data-setting=caption] {display:none !important;}
			.setting[data-setting=description] {display:none !important;}
		  </style>';
 }
 add_action('admin_head', 'paulawilsondata_remove_media_attachement_fields');


/*  ******************************************** */
/*  Remove certain options from admin menu bar ("new post", etc) 
 */
add_action( 'admin_bar_menu', 'remove_wp_nodes', 999 );
function remove_wp_nodes() 
{
    global $wp_admin_bar;   
    $wp_admin_bar->remove_node( 'new-post' );
    $wp_admin_bar->remove_node( 'new-media' );
    $wp_admin_bar->remove_node( 'new-user' );
}

/*  ******************************************** */
/*  Remove command palette from admin bar (ctrl+k)
 */
add_action( 'admin_bar_menu', 'remove_wp_command_palette', 999 );
function remove_wp_command_palette() {
	global $wp_admin_bar;

	if ( ! is_object( $wp_admin_bar ) || ! is_admin_bar_showing() ) {
		return;
	}

	$wp_admin_bar->remove_node( 'command-palette' );
	$wp_admin_bar->remove_node( 'wp-admin-bar-command-palette' );
}

add_action( 'admin_head', function () {
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	echo '<style>#wpadminbar #wp-admin-bar-command-palette { display:none !important; }</style>';
} );
