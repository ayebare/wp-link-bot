<?php

if ( !class_exists( 'classLink_Bot' ) ) {

	class classLink_Bot {

		protected static $cache_group;
		protected static $cache_time;
		protected static $return_links;
		protected static $urls_array;
		public static $transient;
		public static $un_matched_rules;

		/**
		 * Constructor class this case calls the register hook function which is a collection of hooks and filters
		 *
		 * @return void
		 */
		function __construct() {
			self::$cache_group = 'wblink_query';
			self::$cache_time = 3600; //one hour cache
			self::$return_links = 20; // maximum number of links to return per use-case.
			self::$urls_array = array( );
			self::register_hook_callbacks();
		}

		/**
		 * Register callbacks for actions and filters
		 *
		 * @retun void
		 */
		public static function register_hook_callbacks() {
			add_action( 'init', __CLASS__ . '::init' );
			add_action( 'save_post', __CLASS__ . '::delete_cache' );
		}

		/**
		 * Initializes variables
		 *
		 * @return void
		 */
		public static function init() {
			if ( !is_admin() ) {
				return;
			}
			self::$transient = get_transient( self::$cache_group );
			add_action( 'admin_menu', __CLASS__ . '::action_admin_menu' );
		}

		/**
		 * Add our sub-menu page to the VIP dashboard navigation
		 *
		 * @return void
		 */
		public static function action_admin_menu() {
			add_submenu_page( 'tools.php', __( 'WP Urls', 'wp-link-bot' ), __( 'Front End URLs', 'wp-link-bot' ), 'manage_options', 'wp-link-bot', __CLASS__ . '::view_rules' );
		}

		/**
		 * View the urls plus their rewrite rules for the site
		 * 
		 */
		public static function view_rules() {
			self::generate_links();
			print_r( self::$urls_array );
			print_r(self::$un_matched_rules);
		}

		/**
		 * initialises functions that generate the various urls available on the wordpress site
		 *
		 * @return void
		 */
		public static function generate_links() {

			self::gen_blog_view_links();
			self::gen_search_links();
			self::gen_post_links();
			self::gen_comment_post_links();
			self::gen_templates_links();
			self::gen_template_comment_links();
			self::gen_taxterm_archive_links();
			self::gen_year_archive_links();
			self::gen_month_archive_links();
			self::gen_day_archive_links();
			self::$un_matched_rules = self::get_unmatched_rules();
		}

		/**
		 * Generates blog-view links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_blog_view_links() {
			$blog_view = self::get_blog_view_vars();
			$home_url = get_home_url();
			$blog_page = get_option( 'page_for_posts' );
			$blog_url_array = $home_url_array = array();

			$home_url_array[ 'normal_link' ][ ]= self::get_link_rule_arr( $home_url, null );
			$home_url_array[ '404' ][ ]= self::get_link_rule_arr( $home_url . '/zyxwvutsr10up', null );
			self::update_url_array( 'home_pg',  $home_url_array );

			$blog_normal_link = ($blog_page) ? self::get_link_rule_arr( get_permalink( $blog_page ), 'page' ) : '--';
			$blog_url_array[ 'normal_link' ][ ]= $blog_normal_link;
			

			if ( $blog_view[ 'no_of_pages' ] > 1 && $blog_page ) {
				$paginated_link = self::get_link_rule_arr( self::get_paginated_link( $blog_page, 2 ), 'page' );
				$blog_url_array[ 'paginated_link' ][ ]= $paginated_link;

				$pagination_exceeded = self::get_link_rule_arr( self::get_paginated_link( $blog_page, $blog_view[ 'no_of_pages' ] + 7 ), 'page' );
				$blog_url_array[ 'pagination_exceeded' ][ ]= $pagination_exceeded;
			}
			
			self::update_url_array( 'blog_pg', $blog_url_array );
		}

		/**
		 * Generates search links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_search_links() {
			$home_url = get_home_url();
			$blog_view = self::get_blog_view_vars();
			$url_array = array( );

			$url_array[ 'results' ][ ] = self::get_link_rule_arr(add_query_arg( 's', 'a', $home_url ), null);

			if ( $blog_view[ 'no_of_pages' ] > 1 ) {
				$url_array[ 'paginated_results' ][ ] = self::get_link_rule_arr(add_query_arg( 's', 'a', self::search_pagination( 2 ), null)) ;
			}
			$url_array[ 'not_found' ][ ] = self::get_link_rule_arr(add_query_arg( 's', 'zyxwvutsr10up', $home_url ), null);
			self::update_url_array( 'search', $url_array );
		}

		/**
		 * Generates links of posts from a sample space of all post types and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_post_links() {
			$site_posts = self::get_post_link_ids();

			foreach ( $site_posts as $post_type => $posts ) {
				$i=$j=0;
                $url_array = array();
				
				while ( $posts->have_posts() ) {
					$post = $posts->next_post();
					$content = $post->post_content;
					$numpages = self::get_post_pages( $content ); //search if the post is paginated

					if ( $numpages > 1 && $i < self::$return_links ) {
						$paginated_link = self::get_link_rule_arr( self::get_paginated_link( $post->ID, 2 ), $post_type );
						$url_array[ 'paginated_link' ][ ] = $paginated_link;
						$pagination_exceed = self::get_link_rule_arr( self::get_paginated_link( $post->ID, ((int) $numpages + 7 ) ), $post_type );
						$url_array[ 'pagination_exceed' ][ ] = $pagination_exceed;
						$i++;
					} elseif ( $j < self::$return_links ) {
						$normal_link = self::get_link_rule_arr( get_permalink( $post->ID ), $post_type );
						$url_array[ 'normal_link' ][ ] = $normal_link;
						$j++;
					}

					if ( $i > self::$return_links && $i == $j ) {
						break; // job done, get out o here!
					}
				}
				self::update_url_array( $post_type, $url_array );
			}
		}

		/**
		 * Generates links of posts comment links from a sample space of all post types and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_comment_post_links() {
			$site_posts = self::get_post_link_ids();
			$post_comments_no = self::get_comment_posts();
			$max_pg_comments = get_option( 'comments_per_page' );

			foreach ( $site_posts as $post_type => $posts ) {
				$i = $j = 0;
				$url_array = array( );

				if ( post_type_supports( $post_type, 'comments' ) ) {

					while ( $posts->have_posts() ) {
						$post = $posts->next_post();

						if ( isset( $post_comments_no[ $post->ID ] ) ) {

							if ( $post_comments_no[ $post->ID ] > $max_pg_comments && $i < self::$return_links ) {
								$pages_no = (int) $post_comments_no[ $post->ID ] / $max_pg_comments;

								$comments_paginated_link = self::get_link_rule_arr( self::get_comment_pagenum_link( $post->ID, 7 ), $post_type );
								$url_array[ 'comments_pagi_link' ][ ] = $comments_paginated_link;

								$com_pagination_exceed = self::get_link_rule_arr( self::get_comment_pagenum_link( $post->ID, ((int) $pages_no + 7 ) ), $post_type );
								$url_array[ 'com_pagination_exceed' ][ ] = $com_pagination_exceed;
							} elseif ( $j < self::$return_links ) {
								$comments_link = self::get_link_rule_arr( get_permalink( $post->ID ), $post_type );
								$url_array[ 'comments_link' ][ ] = $comments_link;
							}
						}

						if ( $i > self::$return_links && $i == $j ) {
							break; // job done, get out o here!
						}
					}

					self::update_url_array( $post_type, $url_array );
				}
			}
		}

		/**
		 * Generates links pointing to pages with special templates and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_templates_links() {
			$i = $j = 0;
			$template_page_array = self::get_template_pages();

			foreach ( $template_page_array as $temp_type => $posts ) {

				$url_array = array( );

				foreach ( $posts as $post ) {

					$content = $post->post_content;
					$numpages = self::get_post_pages( $content ); //search if the post is paginated

					if ( $numpages > 1 && $i < self::$return_links ) {
						$template_paginated_link = self::get_link_rule_arr( self::get_paginated_link( $post->ID, 2 ), $post->post_type );
						$url_array[ 'template_paginated_link' ][ ] = $template_paginated_link;
						$template_pagination_exceed = self::get_link_rule_arr( self::get_paginated_link( $post->ID, ((int) $numpages + 7 ) ), $post->post_type );
						$url_array[ 'template_pagination_exceed' ][ ] = $template_pagination_exceed;
						$i++;
					} elseif ( $j < self::$return_links ) {
						$template_normal_link = self::get_link_rule_arr( get_permalink( $post->ID ), $post->post_type );
						$url_array[ 'template_normal_link' ][ ] = $template_normal_link;
						$j++;
					}

					if ( $i > self::$return_links && $i == $j ) {
						break; // job done, get out o here!
					}
				}
				self::update_url_array( $temp_type, $url_array );
			}
		}

		/**
		 * Generates links of posts comment links from a sample space of posts with spacial pages
		 * and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_template_comment_links() {
			$template_page_array = self::get_template_pages();
			$post_comments_no = self::get_comment_posts();
			$max_pg_comments = get_option( 'comments_per_page' );

			foreach ( $template_page_array as $temp_type => $posts ) {
				$url_array = array( );

				foreach ( $posts as $post ) {
					$i = $j = 0;

					if ( post_type_supports( $post->post_type, 'comments' ) ) {

						if ( isset( $post_comments_no[ $post->ID ] ) ) {

							if ( $post_comments_no[ $post->ID ] > $max_pg_comments && $i < self::$return_links ) {
								$pages_no = (int) $post_comments_no[ $post->ID ] / $max_pg_comments;

								$comments_pagi_link = self::get_link_rule_arr( self::get_comment_pagenum_link( $post->ID, 7 ), $post->post_type );
								$url_array[ 'comments_pagi_link' ][ ] = $comments_pagi_link;

								$com_pagination_exceed = self::get_link_rule_arr( self::get_comment_pagenum_link( $post->ID, ((int) $pages_no + 7 ) ), $post->post_type );
								$url_array[ 'com_pagination_exceed' ][ ] = $com_pagination_exceed;
							} elseif ( $j < self::$return_links ) {
								$comments_link = self::get_link_rule_arr( get_permalink( $post->ID ), $post->post_type );
								$url_array[ 'comments_link' ][ ] = $comments_link;
							}
						}

						if ( $i > self::$return_links && $i == $j ) {
							break; // job done, get out o here!
						}
					}
				}
				self::update_url_array( $temp_type, $url_array );
			}
		}

		public static function get_term_link( $term, $variation ) {

			switch ( $variation ) {
				case 'paginated_link':
					return self::get_link_rule_arr( self::term_pagination( $term, 2 ), $term->taxonomy );
				case 'pagination_exceed':
					return self::get_link_rule_arr( self::term_pagination( $term, $blog_view[ 'no_of_pages' ] + 7 ), $term->taxonomy );
				case 'normal_link':
					return self::get_link_rule_arr( get_term_link( $term ), $term->taxonomy );
			}
		}

		/**
		 * Generates taxonomy term archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_taxterm_archive_links() {

			$taxonomy_terms = self::get_tax_terms();

			foreach ( $taxonomy_terms as $taxonomy => $terms ) {
				$i = $j = $k = $m = 0;
				$url_array = array();
				$blog_view = self::get_blog_view_vars();

				foreach ( $terms as $term ) {

					$no_of_pages = ceil( $term->count / $blog_view[ 'posts_per_page' ] );

					if ( $term->parent && $no_of_pages > 1 && $i < self::$return_links ) {
						$url_array[ 'paginated_link_parent' ][ ] = self::get_term_link( $term, 'paginated_link' );
						$i++;
					} elseif ( $term->parent && $no_of_pages = 1 && $j < self::$return_links ) {
						$url_array[ 'normal_link_parent' ][ ] = self::get_term_link( $term, 'normal_link' );
						$j++;
					} elseif ( $no_of_pages > 1 && $k < self::$return_links ) {
						$url_array[ 'paginated_link' ][ ] = self::get_term_link( $term, 'paginated_link' );
						$k++;
					} elseif ( $m < self::$return_links ) {
						$url_array[ 'normal_link' ][ ] = self::get_term_link( $term, 'normal_link' );
						$m++;
					}

					if ( $i > self::$return_links && $j>self::$return_links && $m>self::$return_links && $k>self::$return_links) {
						break;
					}
				}
				self::update_url_array( $taxonomy, $url_array );
			}
		}

		/**
		 * Generates year archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_year_archive_links() {
			$blog_view = self::get_blog_view_vars();
			$url_array = array( );

			$url_array[ 'normal_link' ][ ] = self::get_link_rule_arr( get_year_link( date( 'Y' ) ), null );

			$year_posts = self::count_post_by_date( date( "Y-m-d", strtotime( "-1 year", time() ) ) );
			$year_pages = ceil( $year_posts / $blog_view[ 'posts_per_page' ] );

			if ( $year_pages > 1 ) {
				$paginated_link = self::get_link_rule_arr( self::get_date_archive_pagination( 'year', 2 ), null );
				$url_array[ 'paginated_link' ][ ] = $paginated_link;

				$pagination_exceed = self::get_link_rule_arr( self::get_date_archive_pagination( 'year', $year_pages + 7 ), null );
				$url_array[ 'pagination_exceed' ][ ] = $pagination_exceed;
			}

			self::update_url_array( 'year_archive', $url_array );
		}

		/**
		 * Generates month archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_month_archive_links() {
			$blog_view = self::get_blog_view_vars();
			$url_array = array( );

			$normal_link = self::get_link_rule_arr( get_month_link( date( 'Y' ), date( 'm' ) ), null );
			$url_array[ 'normal_link' ][ ] = $normal_link;

			$month_posts = self::count_post_by_date( date( "Y-m-d", strtotime( "-1 month", time() ) ) );
			$month_pages = ceil( $month_posts / $blog_view[ 'posts_per_page' ] );

			if ( $month_pages > 1 ) {
				$paginated_link = self::get_link_rule_arr( self::get_date_archive_pagination( 'month', 2 ), null );
				$url_array[ 'paginated_link' ][ ] = $paginated_link;

				$pagination_exceed = self::get_link_rule_arr( self::get_date_archive_pagination( 'month', $month_pages + 7 ), null );
				$url_array[ 'pagination_exceed' ][ ] = $paginated_link;
			}
			self::update_url_array( 'month_archive', $url_array );
		}

		/**
		 * Generates day archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_day_archive_links() {
			$blog_view = self::get_blog_view_vars();
			$day_posts = self::count_post_by_date( date( "Y-m-d" ) );
			$day_pages = ceil( $day_posts / $blog_view[ 'posts_per_page' ] );

			//Day Posts
			$normal_link = self::get_link_rule_arr( get_day_link( date( 'Y' ), date( 'm' ), date( 'd' ) ), null );
			$url_array[ 'normal_link' ][ ] = $normal_link;

			if ( $day_pages > 1 ) {
				$paginated_link = self::get_link_rule_arr( self::get_date_archive_pagination( 'day', 2 ), null );
				$url_array[ 'paginated_link' ][ ] = $paginated_link;

				$pagination_exceed = self::get_link_rule_arr( self::get_date_archive_pagination( 'day', $day_pages + 7 ), null );
				$url_array[ 'pagination_exceed' ][ ] = $pagination_exceed;
			}
			self::update_url_array( 'day_archive', $url_array );
		}

		/**
		 * Returns an array of each page assigned to a template  http://codex.wordpress.org/Page_Templates
		 *
		 * @return multidimensional array of template name key page object value
		 */
		public static function get_template_pages() {
			global $wpdb;

			$cache_key = 'special-pages';
			$template_pages = self::get_cache( $cache_key );
			$posts_array = array( );

			if ( !$template_pages ) {
				$sql = $wpdb->prepare( "SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = %s", '_wp_page_template' );
				$template_pages = $wpdb->get_results( $sql );
				self::set_cache( $cache_key, $template_pages );
			}
			$theme_templates = get_page_templates();

			foreach ( $template_pages as $page_data ) {

				$temp_name = $page_data->meta_value;

				if ( !in_array( $temp_name, $theme_templates ) ) {
					continue;
				}

				$page = get_post( $page_data->post_id );
				$posts_array[ $temp_name ][ ] = $page;
			}
			return $posts_array;
		}

		/**
		 * updates self::$urls_array property of the class with a url categorised in type and variant
		 *
		 * @return void
		 */
		public static function update_url_array( $type, $value ) {
			    self::$urls_array[ $type ] = (isset(self::$urls_array[ $type ]))? array_merge(self::$urls_array[ $type ], $value): $value;
		}

		/**
		 * Matches a link to its rule
		 *
		 * @params $link, $type  the url in string format and the post type to which it belongs. 
		 * @return an array of a the link as the key and its corresponding re-write rule as the value
		 */
		public static function get_link_rule_arr( $link, $type=null ) {
			$rules = Rewrite_Rules::get_link_rules( $link, $type );
			return array( $link => $rules );
		}

		/**
		 * Returns an array containing the site settings for page for posts as set in the settings->reading options,
		 * The number of posts per page configured in the settings->reading options
		 * Number of published posts. This is helpfull in calculating how many sub pages are expected in blog-view
		 * Number of pages. This is calculated by deviding the number of posts by the number of posts per page
		 *
		 * @return array 
		 */
		public static function get_blog_view_vars() {
			$vars = array( );
			$vars[ 'page_for_posts' ] = get_option( 'page_for_posts' );
			$vars[ 'posts_per_page' ] = get_option( 'posts_per_page' );
			$vars[ 'no_of_posts' ] = wp_count_posts( 'post' )->publish;
			$vars[ 'no_of_pages' ] = ceil( $vars[ 'no_of_posts' ] / $vars[ 'posts_per_page' ] );
			return $vars;
		}

		/**
		 * Retrieve the url of paginated post given the post id and  page index number 
		 *
		 * @param int $i Page number.
		 * @param int $id Page id
		 * @return string Link.
		 */
		public static function get_paginated_link( $id=null, $i ) {
			global $wp_rewrite;

			if ( 1 == $i ) {
				$url = get_permalink( $id );
			} else {
				if ( '' == get_option( 'permalink_structure' ) ) {
					$url = add_query_arg( 'page', $i, get_permalink() );
				} elseif ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) == $id || get_option( 'page_for_posts' ) == $id ) {
					$url = self::add_pagination_page_2_url( get_permalink( $id ), $i );
				} else {
					$url = trailingslashit( get_permalink( $id ) ) . user_trailingslashit( $i, 'single_paged' );
				}
			}
			return $url;
		}

		/**
		 * Returns a paginated date archive url
		 *
		 * @param $date the date for the archived posts to display
		 * @param $index the page index of the paginated url e.g http://site.com/2015/03/page/10/ has index 10/
		 * @retun string. url string of the paginated date archive at index $index
		 */
		public static function get_date_archive_pagination( $date, $index ) {
			switch ( $date ) {
				case 'day':
					$url = get_day_link( date( 'Y' ), date( 'm' ), date( 'd' ) );
					break;
				case 'month':
					$url = get_month_link( date( 'Y' ), date( 'm' ) );
					break;
				default:
					$url = get_year_link( date( 'Y' ) );
					break;
			}
			return self::add_pagination_page_2_url( $url, $index );
		}

		/**
		 * Returns a paginated terms archive url
		 * @param object $term the term object for which the url is to be generated
		 * @param $index the page index of the paginated url 
		 * @retun string. url string of the paginated term archive at index $index
		 */
		public static function term_pagination( $term, $index ) {
			return self::add_pagination_page_2_url( get_term_link( $term ), $index );
		}

		/**
		 * Returns a paginated search url
		 *
		 * @param $index the page index of the paginated url 
		 * @return string. url string of the paginated search results at index $index
		 */
		public static function search_pagination( $index ) {
			return self::add_pagination_page_2_url( get_home_url(), $index );
		}

		public static function add_pagination_page_2_url( $url, $index ) {
			global $wp_rewrite;

			return trailingslashit( $url ) . user_trailingslashit( "$wp_rewrite->pagination_base/" . $index, 'single_paged' );
		}

		/**
		 * Retrieve comments page number link.
		 *
		 * @param int $pg_id
		 * @return string The comments page number link URL.
		 */
		public static function get_comment_pagenum_link( $pg_id, $index ) {
			global $wp_rewrite;

			$pagenum = (int) $index;
			$result = get_permalink( $pg_id );
			if ( $wp_rewrite->using_permalinks() )
				$result = user_trailingslashit( trailingslashit( $result ) . 'comment-page-' . $pagenum, 'commentpaged' );
			else
				$result = add_query_arg( 'cpage', $pagenum, $result );
			$result .= '#comments';
			$result = apply_filters( 'get_comments_pagenum_link', $result );
			return $result;
		}

		/**
		 * Query posts of all post types. This should run once
		 *
		 * @return a multidimensional array of post ids taken from a sample space of 100 posts 
		 */
		public static function get_post_link_ids() {

			$posts_data = array( );
			// get all public post types
			$args = array(
				'public' => true,
			);
			$output = 'names';
			$operator = 'and'; // 'and' or 'or'
			$post_types = get_post_types( $args, $output, $operator );

			foreach ( $post_types as $post_type ) {
				$post_ids = array( );
				$args = array(
					'post_type' => $post_type,
					'posts_per_page' => 100, //sample space of 100posts
					'post_status' => self::display_status( $post_type ),
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				);
				$cache_key = 'all_test_posts_' . serialize( $args );
				$posts = self::get_cache( $cache_key );

				if ( !$posts ) {
					$posts = new WP_Query( $args );
					self::set_cache( $cache_key, $posts );
				}

				if ( $posts->have_posts() ) {
					$posts_data[ $post_type ] = $posts;
				}
			}
			return $posts_data;
		}

		/**
		 * Used to get an array of all public taxonomy terms on the site
		 *
		 * @return array of taxonomy keys as indices and their terms as values
		 */
		public static function get_tax_terms() {
			$cache_key = 'all_tax_terms';
			$return_terms = self::get_cache( $cache_key );

			if ( !$return_terms ) {
				$output = 'names'; // or objects
				$operator = 'and'; // 'and' or 'or'
				$args = array(
					'public' => true,
				);
				$taxonomies = get_taxonomies( $args, $output, $operator );
				$args = array(
					'hide_empty' => true,
					'number' => 100, //sample space of 100 terms
				);

				if ( $taxonomies ) {
					foreach ( $taxonomies as $taxonomy ) {
						$terms = get_terms( $taxonomy, $args );
						if ( !empty( $terms ) && !is_wp_error( $terms ) ) {
							$return_terms[ $taxonomy ] = $terms;
						}
					}
				}
				$terms = self::set_cache( $cache_key, $return_terms );
			}
			return $return_terms;
		}

		/**
		 * Used to fetch an array of posts that have comments
		 *
		 * @return a multidimensional array of post ID's of posts that have comments as keys and number of comments the post has as values
		 */
		public static function get_comment_posts() {
			global $wpdb;

			$cache_key = 'posts_wit_commets';
			$id_coment_arr = self::get_cache( $cache_key );

			if ( !$id_coment_arr ) {
				$id_coment_arr = $wpdb->get_col( "SELECT comment_post_ID FROM $wpdb->comments" );
				self::set_cache( $cache_key, $id_coment_arr );
			}
			return array_count_values( $id_coment_arr ); // return the number of comments per ID
		}

		/**
		 * Determines if a post has pagination by seeking presence of <!--nextpage--> in its content
		 *
		 * @return 1 if no pages are found. (i.e 1 for one page) and number of pages found if many subpages exist in the post
		 */
		public static function get_post_pages( $content ) {
			if ( false !== strpos( $content, '<!--nextpage-->' ) ) {
				// Ignore nextpage at the beginning of the content.
				if ( 0 === strpos( $content, '<!--nextpage-->' ) )
					$content = substr( $content, 15 );
				$pages = explode( '<!--nextpage-->', $content );
				$numpages = count( $pages );

				if ( $numpages > 1 ) {
					return $numpages;
				} else {
					return 1;
				}
			}
			return 1;
		}

		/**
		 * This is a helper function that is used by get_post_link_ids to determine the status of posts to search for
		 * It is used in conjunction with the post type to decide what status of the post is applicable or considered viewable to the public.
		 *
		 * @param $type. The post type.
		 * @return string. the public accessible post type
		 */
		public static function display_status( $type ) {
			switch ( $type ) {
				case 'attachment':
					return 'inherit';
				default:
					return 'publish';
			}
		}

		/**
		 * Returns the number of posts created before the $date object
		 *
		 * @param int $date date object
		 * @return archive link string according to specified date
		 */
		public static function count_post_by_date( $date ) {
			global $wpdb;

			$cache_key = 'postcount' . $date;
			$count = self::get_cache( $cache_key );
			if ( !$count ) {
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(100) FROM $wpdb->posts
      		 WHERE 1=1  AND ( $wpdb->posts.post_date > %s) 
			 AND wp_posts.post_type = 'post' 
			 AND ($wpdb->posts.post_status = 'publish' 
			 OR $wpdb->posts.post_status = 'private')", $date ) );
				self::set_cache( $cache_key, $count );
			}
			return absint( $count );
		}

		public static function get_cache( $key ) {

			$results = wp_cache_get( $key, self::$cache_group );

			if ( !$results ) {
				$results = isset( self::$transient[ $key ] ) ? self::$transient[ $key ] : false;
				if ( $results ) {
					wp_cache_set( $key, $results, self::$cache_group, self::$cache_time );
				}
			}
			return $results;
		}

		public static function set_cache( $cache_key, $value ) {
			self::$transient[ $cache_key ] = $value;
			set_transient( self::$cache_group, self::$transient, self::$cache_time );
		}

		/**
		 * Delete cache if post is saved. This is a caller function for save_post
		 * Since cache has been set to an hour, It makes sense to update it whenever a post is saved.
		 *
		 * @return void
		 */
		public static function delete_cache( $post_id ) {
			global $wp_object_cache;

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return;

			if ( !$post = get_post( $post_id ) )
				return;

			if ( 'auto-draft' == $post->post_status )
				return;

			if ( isset( $wp_object_cache->cache[ self::$cache_group ] ) ) {
				foreach ( $wp_object_cache->cache[ self::$cache_group ] as $k => $v ) {
					wp_cache_delete( $k, $group );
				}
			}
			delete_transient( self::$cache_group );
		}
		
		public static function get_matched_rules(){
			$matched_rules = array();
			foreach(self::$urls_array as $group=>$links_array){
				foreach($links_array as $variation){
				     $matched_rules[] = array_shift($variation[0]);
				}
			}
			return $matched_rules;
		}
		
		public static function get_unmatched_rules(){
			$rewrite_rules= Rewrite_Rules::get_rewrite_rules();
			$matched_rules = self::get_matched_rules();
			$un_matched_rules = array();
			
			foreach ($rewrite_rules as $rule => $data){
			    if(!in_array($rule, $matched_rules)){
					$un_matched_rules[] = $rule;
				}
			}
			
			return $un_matched_rules;
		}

	}

	//End of classLink_Bot Class
}
