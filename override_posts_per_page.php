<?php
add_action( 'pre_get_posts',  'set_posts_per_page'  );
function set_posts_per_page( $query ) {
	
	$page_for_posts = get_option( 'page_for_posts' );
	
  if ( get_option( 'page_for_posts' ) == $query->queried_object_id) {
    $query->set( 'posts_per_page', 3 );
  }
  

  return $query;
}