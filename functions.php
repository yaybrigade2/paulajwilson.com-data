<?php
/*  ******************************************** */
/*  paulawilsondata THEME
/*  ******************************************** */


/*  ******************************************** */
/*  ANCHOR: Add Custom REST endpoint for PROJECTS
/*  https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/projects/  
*/
function rest_projects( $data ) {
	
	global $post;
	
	$args = [
		'post_type' => 'project',
		'posts_per_page' => -1,
		'orderby'        => 'rand',
	];	
	$projects_query = new WP_Query($args);

	$projects = [];

	if ( $projects_query->have_posts() ) : 

		while ( $projects_query->have_posts() ) : $projects_query->the_post(); 

			$id = $post->ID;
			$project_title = $post->post_title;
			$slug = $post->post_name;
			$description = get_field('description');
			$category = get_field('category');

			$poster_image = get_field('poster_image');
			$media = get_field('media');

			// PUT IT ALL TOGETHER
			$projectArray = array(
					'id' => $id,
					'title' => $project_title,
					'projectSlug' => $slug,
					'description' => $description,
					'category' => $category,
					'posterImage' => $poster_image,
					'media' => $media,
				);
			
			array_push($projects, $projectArray);
			
		endwhile; 

	endif;

	$jsonObj = $projects;
	return $jsonObj;
}
add_action( 'rest_api_init', function () {
  register_rest_route( 'paulawilsondata/v1', '/projects', array(
	'methods' => 'GET',
	'callback' => 'rest_projects',
	'permission_callback' => '__return_true',
  ));
});


/*  ******************************************** */
/*  ANCHOR REST for sitemap URLs
/*  Example: https://paulawilsondata.yaybrigade.xyz/wp-json/paulawilsondata/v1/urls
*/
function rest_urls( $data ) {
    
    global $post;

	$url_prefix = 'https://xxx'; // TODO: replace with live url

	$results = [];

	// get all designers
	$args = array(
		'post_type' => 'designer',
		'posts_per_page' => -1,
		'post_status' => 'publish',
		'fields' => 'post_name',
		'orderby' => 'meta_value',
		'meta_key' => 'sort_name',
		'order' => 'ASC',
	);

	$designers = get_posts($args);

	foreach ($designers as $designer) {

		// ignore year_level=='PETS'
		if (get_field('year_level', $designer->ID) == 'PETS') {
			continue;
		}

		$path = 'designer/' . $designer->post_name;

		$result = $url_prefix . $path;
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
	// /wp-json/paulawilsondata/v1/projects
	if ( ! isset( $allowed_endpoints[ 'paulawilsondata/v1' ] ) || ! in_array( 'projects', $allowed_endpoints[ 'paulawilsondata/v1' ] ) ) {
		$allowed_endpoints[ 'paulawilsondata/v1' ][] = 'projects';
	}
	return $allowed_endpoints;
}
add_filter( 'wp_rest_cache/allowed_endpoints', 'wprc_add_custom_endpoints', 10, 1);

/**
 * Flush REST cache
 */
function paulawilsondata_flush_rest() {
	if( is_plugin_active( 'wp-rest-cache/wp-rest-cache.php' ) ) {
		// https://wordpress.org/support/topic/how-to-flush-cache-on-custom-endpoints/
		\WP_Rest_Cache_Plugin\Includes\Caching\Caching::get_instance()->delete_cache_by_endpoint( '/data/wp-json/paulawilsondata/v1/projects', 'strict', false );
	}
}
add_action( 'save_post',	'paulawilsondata_flush_rest' );
add_action( 'trashed_post',	'paulawilsondata_flush_rest' );
add_action( 'deleted_post',	'paulawilsondata_flush_rest' );