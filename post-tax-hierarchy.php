<?php

class TitanPostTaxHierarchy {

	protected static $instance = null;
	private $arr_list_of_post_taxs = array();
	private $arr_customPostTermSlug = array();
	private $current_url = '';

	private function __construct() {
		$this->current_url = str_replace(home_url(), '', $_SERVER['REQUEST_URI']);
	}

	public static function getInstance() {
		if (!isset(static::$instance)) {
			static::$instance = new static;
		}
		return static::$instance;
	}

	/**
	 * Function to give you a list of registered custom post types as well as their corresponding taxonomies
	 *
	 * @param data	$data	Array of data to set the property in this class.
	 * 
	 * @return $data
	 */
	public function listOfPostTaxs($data = array()) {
		if (!empty($data)) {
			$this->arr_list_of_post_taxs = $data;
		} else {
			return $this->arr_list_of_post_taxs;
		}
	}

	/**
	 * This bit builds up rewrite rules for taxonomies as well as custom posts
	 *
	 * @param wp_rewrite	$wp_rewrite	WP rewrite rules.
	 * 
	 * @return Nothing
	 */
	function rewriteRulesForCustomPostTypeAndTax($wp_rewrite) {
		$tax_rules = array();
		$custom_post_rules = array();

		foreach ($this->arr_list_of_post_taxs as $post_type) {

			$args = array(
					'post_type' => $post_type['name'],
					'posts_per_page' => -1
			);
			$custom_post_type_posts = new WP_Query($args);


			foreach ($custom_post_type_posts->posts as $post_key => $post_val) {
				$arr_slugs = $this->getSlugsForPostTax($post_val->ID);
				$base_taxonomy = $post_type['basepage']->post_name;

				if (!empty($arr_slugs)) { //there are more than one slug for the same post, create a rule for each
					foreach ((array) $arr_slugs as $slug_key => $slug_val) {
						$single_post_slug = explode('/', $slug_val);
						$single_post_slug[] = $post_val->post_name; //add the post name at the end of the array
						$single_post_slug = array_values(array_filter($single_post_slug)); //re-index after removing all the empty keys from array
						$single_post_slug[0] = $base_taxonomy; //replace the old base taxonomy with the new one.
						$single_post_slug = implode('/', $single_post_slug) . '-' . $post_val->ID;
						$custom_post_rules['^' . $single_post_slug . '$'] = 'index.php?' . $post_type['name'] . '=' . $post_val->post_name;
					}
				} else { //only one slug available, create the rule
					$single_post_slug = $post_type['basepage']->post_name . '/' . $post_val->post_name . '-' . $post_val->ID;
					$custom_post_rules['^' . $single_post_slug . '$'] = 'index.php?' . $post_type['name'] . '=' . $post_val->post_name;
				}
			}

			$arr_categories = get_categories(array('type' => $post_type, 'taxonomy' => $post_type['custom_taxonomy']['slug'], 'hide_empty' => 0));
			foreach ($arr_categories as $category) {
				$tax_rules['^' . $base_taxonomy . '/' . $category->slug . '/?$'] = 'index.php?' . $category->taxonomy . '=' . $category->slug;
			}
		}

		$final_rules = array_merge($custom_post_rules, $tax_rules);
		$wp_rewrite->rules = $final_rules + $wp_rewrite->rules;
	}

	function getTheTermListModified($id, $taxonomy) {
		$terms = get_the_terms($id, $taxonomy);

		if (is_wp_error($terms))
			return $terms;

		if (empty($terms))
			return false;

		$links = array();

		foreach ($terms as $term) {
			$link = get_term_link($term, $taxonomy);
			if (is_wp_error($link)) {
				return $link;
			}
			$links[] = esc_url(str_replace(home_url(), '', $link));
		}

		$current_page_in_array = array();
		foreach ($links as $key => $val) {
			if (stristr($val, $_SERVER['REQUEST_URI'])) {
				$current_page_in_array[] = $val;
			}
		}

		if (!empty($current_page_in_array)) {
			return $current_page_in_array;
		} else {
			return $links;
		}
	}

	/**
	 * Generate the rewrite rules for Woocommerce
	 * 
	 * @return Nothing
	 */
	function rewriteRulesForWoocommerce($wp_rewrite) {
		global $arr_termSlug;
		global $wp_rewrite;

		$custom_post_rules = array();
		$product_categories = get_terms('product_cat');
		$obj_shop_page = get_post(woocommerce_get_page_id('shop'));
		foreach ($product_categories as $category) {
			$arr_termSlug = array();
			$this->getTaxonomyHierarchy($category, 'product');
			$slug = implode('/', array_reverse($arr_termSlug));
			$custom_post_rules['^' . $obj_shop_page->post_name . '/' . $slug . '/?$'] = 'index.php?product_cat=' . $category->slug;
		}

		//------------This code is simply for products that do no have a category, by default wordpress assigns "uncategorized" category and puts it in the slug
		$args = array(
				'post_type' => 'product',
				'posts_per_page' => -1
		);
		$arr_woo_products = new WP_Query($args);

		foreach ($arr_woo_products->posts as $product) {
			$links = $this->getTheTermListModified($product->ID, 'product_cat');
			if (empty($links)) { //no category
				$custom_post_rules['^' . $obj_shop_page->post_name . '/' . $product->post_name . '/?$'] = 'index.php?product=' . $product->post_name;
			}
		}
		//-------------------------------------------------------------------------------------------------------------------------------------------------------

		return $wp_rewrite->rules = $custom_post_rules + $wp_rewrite->rules;
	}

	function getTaxonomyHierarchy($term, $custom_tax) {
		global $arr_termSlug;
		array_push($arr_termSlug, $term->slug);

		if ($term->parent > 0) {
			$this->getTaxonomyHierarchy(get_term($term->parent), $custom_tax);
		}
	}

//	function sortTermsHierarchicaly(Array &$cats, Array &$into, $parentId = 0) {
//		foreach ($cats as $i => $cat) {
//			if ($cat->parent == $parentId) {
//				$into[$cat->term_id] = $cat;
//				unset($cats[$i]);
//			}
//		}
//
//		foreach ($into as $topCat) {
//			$topCat->children = array();
//			sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
//		}
//	}

	/**
	 * Get the slugs for Woocommerce items
	 *
	 * @param post_link	$post_link	The current link
	 * 
	 * @param post	$post	The post object.
	 * 
	 * @return Array of possible links
	 */
	function wooCustomPostLink($post_link, $post = array()) {
		global $tax_hierarchy;
		$tax_hierarchy = array();

		if (empty($post)) {
			$post = get_post(get_the_ID());
		}

		if (get_post_type() == 'product') {
			$obj_shop_page = get_post(woocommerce_get_page_id('shop'));

			//$links = get_the_term_list_modified($post->ID, $link_setup[get_post_type($post->ID)]);
			$links = $this->getTheTermListModified($post->ID, 'product_cat');
			
			if (!empty($links)) {
				//by default wordpress adds "product-category" to it's shop base page slug, we don't want that, replace with the slug of the specified shop page
				$post_link = str_replace('product-category', $obj_shop_page->post_name, $links[0]) . $post->post_name . '/';
			} else {
				$post_link = '/' . $obj_shop_page->post_name . '/' . $post->post_name . '/';
			}
		}

		return $post_link;
	}

	/**
	 * Generate the slug for taxonomies in wp-admin
	 *
	 * @param id	$id	ID of the item you wish a slug for.
	 * 
	 * @return Array of links, if a item is in multiple taxonomies, it can have more than one link
	 */
	function getSlugsForPostTax($id) {
		$links = array();
		if (!empty($this->arr_list_of_post_taxs[get_post_type($id)])) { //get the
			$taxonomy = $this->arr_list_of_post_taxs[get_post_type($id)]['custom_taxonomy'];
			$terms = get_the_terms($id, $taxonomy['slug']);

			if (is_wp_error($terms))
				return $terms;

			if (empty($terms))
				return false;


			foreach ($terms as $term) {
				$link = get_term_link($term, $taxonomy);
				if (is_wp_error($link)) {
					return $link;
				}
				$links[] = esc_url(str_replace(home_url(), '', $link));
			}
		}
		return $links;
	}

	public function getTermFromCurrentURL($post, $custom_tax) {
		$arr_terms = wp_get_object_terms($post->ID, $custom_tax['custom_taxonomy']['slug'], array('fields' => 'all'));

		foreach ($arr_terms as $term) {

			$check = $term->slug;
			if ($term->parent > 0) {
				$parent = get_term($term->parent);
				$check = $parent->slug;
			}

			if (stristr($this->current_url, $check)) {
				return $term;
			}
		}
	}

	/**
	 * This creates the link for the custom post type post to include the hierarchy of the taxonomy terms in the slug
	 * This is used wherever WP needs to display a slug for a post type this will work in the XML sitemap as well
	 *
	 * @param Post Link   $post_link  Slug for the post
	 * @param Post $post Post object
	 * 
	 * @return Modified post link to include hierarcy
	 */
	function customPostTaxLink($post_link, $post = null) {
		$this->arr_customPostTermSlug = array(); //re-initialize the array
		if (!empty($this->arr_list_of_post_taxs[get_post_type($post->ID)])) { //this is for a custom post type
			$custom_tax = $this->arr_list_of_post_taxs[get_post_type($post->ID)];

			if (!empty($custom_tax)) { //I'm inside one of my custom taxonomies
				if (is_admin()) {
					$terms = wp_get_object_terms($post->ID, $custom_tax['custom_taxonomy']['slug'], array('fields' => 'all'));
					$terms = count($terms) > 0 ? $terms[0] : $terms;
				} else {
					$terms = $this->getTermFromCurrentURL($post, $custom_tax);
				}

				if (!empty($terms)) { //add the terms into the url structure
					$this->getTermHierarchy($terms, $custom_tax['name']);
					$slug = implode('/', array_reverse($this->arr_customPostTermSlug));

					$post_link = '/' . $custom_tax['basepage']->post_name . '/' . $slug . '/' . $post->post_name . '-' . $post->ID . '/';
				} else { //append post-id to any custom posts not inside a taxonomy
					if (is_a($post, 'WP_Term')) { //this is a term link
						$post_link = '/' . $custom_tax['basepage']->post_name . '/' . $post->slug . '/';
					} else {
						$post_link = $post->slug . '/' . $custom_tax['basepage']->post_name . '/' . $post->post_name . '-' . $post->ID . '/';
					}
				}
			}
		}

		if (is_a($post, 'WP_Term')) { //this is a term link
			$post_link_parts = array_values(array_filter(explode('/', str_replace(home_url(), '', $post_link))));

			$taxonomy = 'td_' . $post_link_parts[0];

			if (!empty($this->arr_list_of_post_taxs[$taxonomy])) {
				$updated_taxonomy = $this->arr_list_of_post_taxs[$taxonomy]['basepage']->post_name;
				$post_link = str_replace($post_link_parts[0], $updated_taxonomy, $post_link);
			}
		}

		return $post_link;
	}

	/**
	 * This is simply a recursive function to build up the custom taxonomy hierarchy
	 * WP has a function that does something similar, but not exactly what I needed here
	 *
	 * @param Term   $term  The term object
	 * @param Custom Taxonomy $custom_tax Custom taxonomy name
	 * 
	 * @return Nothing, builds up array
	 */
	function getTermHierarchy($term, $custom_tax) {

		array_push($this->arr_customPostTermSlug, $term->slug);

		if ($term->parent > 0) {
			$this->getTermHierarchy(get_term($term->parent), $custom_tax);
		}
	}

	/**
	 * Check if WooCommerce is active. Courtesy of : http://snippet.fm/snippets/check-if-woocommerce-plugin-is-installed-and-activated-on-server-multisite-and-single-installation/
	 *
	 * @return  bool
	 */
	public function isWoocommerceActive() {
		$active_plugins = ( is_multisite() ) ?
				array_keys(get_site_option('active_sitewide_plugins', array())) :
				apply_filters('active_plugins', get_option('active_plugins', array()));
		foreach ($active_plugins as $active_plugin) {
			$active_plugin = explode('/', $active_plugin);
			if (isset($active_plugin[1]) && 'woocommerce.php' === $active_plugin[1]) {
				return true;
			}
		}
		return false;
	}

	public function seoCanonical($post_link) {
		global $post;
		if (!empty($this->arr_list_of_post_taxs[get_post_type($post->ID)])) {
			$custom_tax = $this->arr_list_of_post_taxs[get_post_type($post->ID)];
			$this->arr_customPostTermSlug = array(); //re-initialize the array
			if (!empty($custom_tax)) { //I'm inside one of my custom taxonomies
				$terms = wp_get_object_terms($post->ID, $custom_tax['custom_taxonomy']['slug'], array('fields' => 'all'));
				if (count($terms) > 1) {
					$this->getTermHierarchy($terms[0], $custom_tax['name']);
					$slug = implode('/', array_reverse($this->arr_customPostTermSlug));
					$post_link = home_url() . '/' . $custom_tax['basepage']->post_name . '/' . $slug . '/' . $post->post_name . '-' . $post->ID . '/';
				}
			}
		}
		return $post_link;
	}

	/**
	 * This handles the breadcrumbs for custom post types and taxonomies
	 * Simply put, this takes the top level taxonomy and returns a breadcrumb trail for it.
	 *
	 * @param Term   $term  The term for the current post
	 * @param ID $id ID for the current post
	 * 
	 * @return Array
	 */
	public function bcnBreadcrumbs($term, $id) {
		if(empty($term)){
			return false;
		}
		
		$link_setup = array();
		//check if woocommerce installed
		if (class_exists('WooCommerce')) {
			$link_setup['product'] = 'product_cat';
		}

		$arr_list_custom_tax = get_field('td_admin_custom_taxonomies', 'options');
		foreach ((array) $arr_list_custom_tax as $key => $val) {
			if (!in_array($val['custom_taxonomy_post_name'], $link_setup)) {
				$link_setup[$val['custom_taxonomy_post_name']] = sanitize_title_with_dashes($val['taxonomy_name']);
			}
		}
		
		//Fill a temporary object with the terms
		$bcn_object = get_the_terms($id, $term->taxonomy);

		$url_parts = explode('/', $_SERVER['REQUEST_URI']);
		$url_parts = array_filter($url_parts);
		$potential_base_term = $url_parts[(count($url_parts) - 1)];

		$potential_parent = 0;
		//Make sure we have an non-empty array
		if (is_array($bcn_object)) {
			//Now try to find the deepest term of those that we know of
			$bcn_use_term = key($bcn_object);
			foreach ($bcn_object as $key => $object) {

				if (!empty($link_setup[get_post_type($id)]) && $object->slug == $potential_base_term) {
					return $bcn_object[$key];
				}

				//Can't use the next($bcn_object) trick since order is unknown
				if ($object->parent > 0 && ($potential_parent === 0 || $object->parent === $potential_parent)) {
					$bcn_use_term = $key;
					$potential_parent = $object->term_id;
				}
			}
			return $bcn_object[$bcn_use_term];
		}
		return false;
	}

}



//only do this if Woocommerce is enabled, no point doing it otherwise
if ($titan_custom_tax_hierarchy->isWoocommerceActive()) { 
	add_filter('generate_rewrite_rules', array($titan_custom_tax_hierarchy, 'rewriteRulesForWoocommerce'));
	add_filter('post_type_link', array($titan_custom_tax_hierarchy, 'wooCustomPostLink'), 1, 2);
}

//this is for custom post types and taxonomies
add_filter('generate_rewrite_rules', array($titan_custom_tax_hierarchy, 'rewriteRulesForCustomPostTypeAndTax'));
add_filter('post_type_link', array($titan_custom_tax_hierarchy, 'customPostTaxLink'), 1, 2);
add_filter('term_link', array($titan_custom_tax_hierarchy, 'customPostTaxLink'), 1, 2);

//URL canonicalisation for posts that are in two categories
add_filter( 'wpseo_canonical', array($titan_custom_tax_hierarchy, 'seoCanonical'), 10, 1 );

//breadcrumb magic goes here
add_filter( 'bcn_pick_post_term', array($titan_custom_tax_hierarchy, 'bcnBreadcrumbs'), 10, 2 );