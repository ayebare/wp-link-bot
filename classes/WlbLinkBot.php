<?php

if ( !class_exists( 'classLink_Bot' ) ) {

	class classLink_Bot {

		protected static $cache_group;
		protected static $cache_time;
		protected static $return_links;
		protected static $urls_array;
		public static $transient;

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
		}

		/**
		 * initialises functions that generate the various urls available on the wordpress site
		 *
		 * @return void
		 */
		public static function generate_links() {

			self::gen_blog_view_links();
			self::gen_search_links();
			self::gen_template_page_links();
			self::gen_post_links();
			self::gen_taxterm_archive_liks();
			self::gen_year_archive_links();
			self::gen_month_archive_links();
			self::gen_day_archive_links();
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

			self::update_url_array( 'home_pg', 'normal_link', self::link_a_rule( $home_url, null ) );
			self::update_url_array( 'home_pg', '404', self::link_a_rule( $home_url . '/zyxwvutsr10up', null ) );

			$blog_normal_link = ($blog_page) ? self::link_a_rule( get_permalink( $blog_page ), 'page' ) : '--';
			self::update_url_array( 'blog_pg', 'normal_link', $blog_normal_link );

			if ( $blog_view[ 'no_of_pages' ] > 1 && $blog_page ) {
				$paginated_link = self::link_a_rule( self::get_paginated_link( $blog_page, 2 ), 'page' );
				self::update_url_array( 'blog_pg', 'paginated_link', $paginated_link );

				$pagination_exceeded = self::link_a_rule( self::get_paginated_link( $blog_page, $blog_view[ 'no_of_pages' ] + 7 ), 'page' );
				self::update_url_array( 'blog_pg', 'pagination_exceeded', $pagination_exceeded );
			}
		}

		/**
		 * Generates search links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_search_links() {
			$home_url = get_home_url();
			$blog_view = self::get_blog_view_vars();

			self::update_url_array( 'search', 'results', add_query_arg( 's', 'a', $home_url ) );

			if ( $blog_view[ 'no_of_pages' ] > 1 ) {
				self::update_url_array( 'search', 'paginated_results', add_query_arg( 's', 'a', self::search_pagination( 2 ) ) );
			}
			self::update_url_array( 'search', 'not_found', add_query_arg( 's', 'zyxwvutsr10up', $home_url ) );
		}

		/**
		 * Generates links pointing to pages with special templates and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_template_page_links() {
			$template_pages = self::get_template_pages();

			if ( !empty( $template_pages ) ) {
				foreach ( $template_pages as $type => $data ) {

					$normal_link = isset( $data[ 'no_pagination' ] ) ? self::link_a_rule( get_permalink( $data[ 'no_pagination' ] ), $type ) : '--';
					self::update_url_array( $type, 'normal_link', $normal_link );

					$paginated_link = isset( $data[ 'pagination' ] ) ? self::link_a_rule( self::get_paginated_link( $data[ 'pagination' ][ 'id' ], 2 ), $type ) : '--';
					self::update_url_array( $type, 'paginated_link', $paginated_link );

					$pagination_exceeded = isset( $data[ 'pagination' ] ) ? self::link_a_rule( self::get_paginated_link( $data[ 'pagination' ][ 'id' ], ((int) ($data[ 'pagination' ][ 'pages_no' ] + 7) ) ), $type ) : '--';
					self::update_url_array( $type, 'pagination_exceeded', $pagination_exceeded );

					if ( $data[ 'comments' ] ) {
						$comment_link = isset( $data[ 'no_pagi_com' ] ) ? self::link_a_rule( get_permalink( $data[ 'no_pagi_com' ] ), $type ) : '--';
						self::update_url_array( $type, 'comment_link', $comment_link );

						$comments_pagi_link = isset( $data[ 'paginated_com' ] ) ? self::link_a_rule( self::get_comment_pagenum_link( $data[ 'paginated_com' ][ 'id' ], 2 ), $type ) : '--';
						self::update_url_array( $type, 'comments_pagi_link', $comments_pagi_link );

						$com_pagination_exceed = isset( $data[ 'paginated_com' ] ) ? self::link_a_rule( self::get_comment_pagenum_link( $data[ 'paginated_com' ][ 'id' ], ((int) ($data[ 'paginated_com' ][ 'pages_no' ] + 7) ) ), $type ) : '--';
						self::update_url_array( $type, 'com_pagination_exceed', $com_pagination_exceed );
					}
				}
			}
		}

		/**
		 * Generates links of all post types and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_post_links() {
			// post links
			$post_link_ids = self::get_post_link_ids();

			foreach ( $post_link_ids as $type => $data ) {
				$normal_link = isset( $data[ 'no_pagination' ] ) ? self::link_a_rule( get_permalink( $data[ 'no_pagination' ] ), $type ) : '--';
				self::update_url_array( $type, 'normal_link', $normal_link );

				$paginated_link = isset( $data[ 'pagination' ] ) ? self::link_a_rule( self::get_paginated_link( $data[ 'pagination' ][ 'id' ], 2 ), $type ) : '--';
				self::update_url_array( $type, 'paginated_link', $paginated_link );

				$pagination_exceed = isset( $data[ 'pagination' ] ) ? self::link_a_rule( self::get_paginated_link( $data[ 'pagination' ][ 'id' ], ((int) ($data[ 'pagination' ][ 'pages_no' ] + 7) ) ), $type ) : '--';
				self::update_url_array( $type, 'pagination_exceed', $pagination_exceed );

				if ( $data[ 'comments' ] ) {
					$comments_link = isset( $data[ 'no_pagi_com' ] ) ? self::link_a_rule( get_permalink( $data[ 'no_pagi_com' ] ), $type ) : '--';
					self::update_url_array( $type, 'comments_link', $comments_link );

					$comments_pagi_link = isset( $data[ 'paginated_com' ] ) ? self::link_a_rule( self::get_comment_pagenum_link( $data[ 'paginated_com' ][ 'id' ] , 7), $type ) : '--';
					self::update_url_array( $type, 'comments_pagi_link', $comments_pagi_link );

					$com_pagination_exceed = isset( $data[ 'paginated_com' ] ) ? self::link_a_rule( self::get_comment_pagenum_link( $data[ 'paginated_com' ][ 'id' ], ((int) ($data[ 'paginated_com' ][ 'pages_no' ] + 7) ) ), $type ) : '--';
					self::update_url_array( $type, 'com_pagination_exceed', $com_pagination_exceed );
				}
			}
		}

		/**
		 * Generates taxonomy term archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_taxterm_archive_liks() {
			$blog_view = self::get_blog_view_vars();

			//taxonomy term archive links
			$taxonomy_terms = self::get_tax_terms();
			foreach ( $taxonomy_terms as $taxonomy => $terms ) {
				$pagi_term = $no_pagi_term = $pagi_term_p = $no_pagi_term_p = false;
				$term_usecases = 0;

				foreach ( $terms as $term ) {
					$no_of_pages = ceil( $term->count / $blog_view[ 'posts_per_page' ] );

					if ( $term->parent ) {
						if ( $no_of_pages == 1 && $no_pagi_term_p == false ) {
							$normal_link_parent = self::link_a_rule( get_term_link( $term ), $taxonomy );
							self::update_url_array( $taxonomy, 'normal_link_parent', $normal_link_parent );

							$no_pagi_term_p = true;
							$term_usecases++;
						} elseif ( $no_of_pages > 1 && $pagi_term_p == false ) {
							$paginated_link_parent = self::link_a_rule( self::term_pagination( $term, 2 ), null );
							self::update_url_array( $taxonomy, 'paginated_link_parent', $paginated_link_parent );

							$pagination_exceed_parent = self::link_a_rule( self::term_pagination( $term, $blog_view[ 'no_of_pages' ] + 7 ), null );
							self::update_url_array( $taxonomy, 'pagination_exceed_parent', $pagination_exceed_parent );
							$pagi_term_p = true;
							$term_usecases++;
						}
					} else {
						if ( $no_of_pages == 1 && $no_pagi_term == false ) {
							$normal_link = self::link_a_rule( get_term_link( $term ), $taxonomy );
							self::update_url_array( $taxonomy, 'normal_link', $normal_link );
							$no_pagi_term = true;
							$term_usecases++;
						} elseif ( $no_of_pages > 1 && $pagi_term == false ) {
							$paginated_link = self::link_a_rule( self::term_pagination( $term, 2 ), null );
							self::update_url_array( $taxonomy, 'paginated_link', $paginated_link );

							$pagination_exceed = self::link_a_rule( self::term_pagination( $term, $blog_view[ 'no_of_pages' ] + 7 ), null );
							self::update_url_array( $taxonomy, 'pagination_exceed', $pagination_exceed );
							$pagi_term = true;
							$term_usecases++;
						}
					}

					if ( $term_usecases == 4 ) {
						break;
					}
				}
			}
		}

		/**
		 * Generates year archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_year_archive_links() {
			$blog_view = self::get_blog_view_vars();

			self::update_url_array( 'year_archive', 'normal_link', self::link_a_rule( get_year_link( date( 'Y' ) ), null ) );

			$year_posts = self::count_post_by_date( date( "Y-m-d", strtotime( "-1 year", time() ) ) );
			$year_pages = ceil( $year_posts / $blog_view[ 'posts_per_page' ] );

			if ( $year_pages > 1 ) {
				$paginated_link = self::link_a_rule( self::date_archive_pagination( 'year', 2 ), null );
				self::update_url_array( 'year_archive', 'paginated_link', $paginated_link );

				$pagination_exceed = self::link_a_rule( self::date_archive_pagination( 'year', $year_pages + 7 ), null );
				self::update_url_array( 'year_archive', 'pagination_exceed', $pagination_exceed );
			}
		}

		/**
		 * Generates month archive links and assigns them to the self::$urls_array using update_url_array function 
		 *
		 * @return void
		 */
		public static function gen_month_archive_links() {
			$blog_view = self::get_blog_view_vars();

			$normal_link = self::link_a_rule( get_month_link( date( 'Y' ), date( 'm' ) ), null );
			self::update_url_array( 'month_archive', 'normal_link', $normal_link );

			$month_posts = self::count_post_by_date( date( "Y-m-d", strtotime( "-1 month", time() ) ) );
			$month_pages = ceil( $month_posts / $blog_view[ 'posts_per_page' ] );

			if ( $month_pages > 1 ) {
				$paginated_link = self::link_a_rule( self::date_archive_pagination( 'month', 2 ), null );
				self::update_url_array( 'month_archive', 'paginated_link', $paginated_link );

				$pagination_exceed = self::link_a_rule( self::date_archive_pagination( 'month', $month_pages + 7 ), null );
				self::update_url_array( 'month_archive', 'pagination_exceed', $pagination_exceed );
			}
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
			$normal_link = self::link_a_rule( get_day_link( date( 'Y' ), date( 'm' ), date( 'd' ) ), null );
			self::update_url_array( 'day_archive', 'normal_link', $normal_link );

			if ( $day_pages > 1 ) {
				$paginated_link = self::link_a_rule( self::date_archive_pagination( 'day', 2 ), null );
				self::update_url_array( 'day_archive', 'paginated_link', $normal_link );

				$pagination_exceed = self::link_a_rule( self::date_archive_pagination( 'day', $day_pages + 7 ), null );
				self::update_url_array( 'day_archive', 'pagination_exceed', $pagination_exceed );
			}
		}

		/**
		 * updates self::$urls_array property of the class with a url categorised in type and variant
		 *
		 * @return void
		 */
		public static function update_url_array( $type, $variant, $value ) {
			self::$urls_array[ $type ][ $variant ] = $value;
		}

		/**
		 * Matches a link to its rule
		 *
		 * @params $link, $type  the url in string format and the post type to which it belongs. 
		 * @return an array of a the link as the key and its corresponding re-write rule as the value
		 */
		public static function link_a_rule( $link, $type=null ) {
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
		public static function date_archive_pagination( $date, $index ) {
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
			/**
			 * Apply wordpress comment link filter
			 *
			 * @param string $result The comments page number link.
			 */
			$result = apply_filters( 'get_comments_pagenum_link', $result );
			return $result;
		}

		/**
		 * Get ordinary post link, Post with paginated comment, post with pagination
		 * Get all post types and do an individual WP_Query as opposed to doing a query on all to prevent heavy queries
		 *
		 * @retun a multidimensional array of post ids taken from a sample space of 100 posts conatining posts that have comments, pagination, no pagination
		 */
		public static function get_post_link_ids() {
			// get all public post types
			$return_ids = array( );
			// get all public post types
			$args = array(
				'public' => true,
			);
			$output = 'names';
			$operator = 'and'; // 'and' or 'or'
			$post_types = get_post_types( $args, $output, $operator );
			$posts_w_comments = self::get_comment_posts();
			$max_pg_comments = get_option( 'comments_per_page' );
			
			foreach ( $post_types as $post_type ) {
				$post_ids = array( );
				$post_ids[ 'comments' ] = post_type_supports( $post_type, 'comments' );
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
					$pagination = $no_pagination = $no_pagi_com = $paginated_com = false;
					$use_cases = 0; // We shall require 4 use cases to exit the loop. start at 0 incrementing as we go along.
					while ( $posts->have_posts() ) {
						$post = $posts->next_post();
						$content = $post->post_content;
						$numpages = self::get_post_pages( $content ); //search if the post is paginated
						if ( !$pagination && $numpages > 1 ) {
							$post_ids[ 'pagination' ] = array( 'id' => $post->ID, 'pages_no' => $numpages ); // post is paginated pages_no is the no of pages it has
							$pagination = true;
							$use_cases++;
						} elseif ( !$no_pagination ) {
							$post_ids[ 'no_pagination' ] = $post->ID; // non paginated post ID is retrieved
							$no_pagination = true;
							$use_cases++;
						}
						if ( isset( $posts_w_comments[ $post->ID ] ) ) {
							if ( $posts_w_comments[ $post->ID ] > $max_pg_comments && !$paginated_com ) {
								$pages_no = (int) $posts_w_comments[ $post->ID ] / $max_pg_comments;
								$post_ids[ 'paginated_com' ][ 'id' ] = $post->ID; //post with paginated comments retrieved								
								$post_ids[ 'paginated_com' ][ 'pages_no' ] = ceil( $pages_no ); //no of comment pages for the post					
								$paginated_com = true;
								$use_cases++;
							} elseif ( !$no_pagi_com ) {
								$post_ids[ 'no_pagi_com' ] = $post->ID; // post with no paginated comments retrieved
								$no_pagi_com = true;
								$use_cases++;
							}
						}
						if ( $use_cases == 4 ) {
							break; // job done, get out o here!
						}
					}
				}
				$return_ids[ $post_type ] = $post_ids;
			}

			return $return_ids;
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
					'number'   => 100, //sample space of 100 terms
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
		 * Returns an array of each instance of a page template assigned to page.  http://codex.wordpress.org/Page_Templates
		 * The array comprises pages that have been assigned page templates and they have pagination, no pagination, comments, no comments. one 
		 * Instance of each is collected.
		 *
		 * @return multidimensional array 
		 */
		public static function get_template_pages() {
			global $wpdb;

			$cache_key = 'special-pages';
			$template_pages = self::get_cache( $cache_key );
			$template_page_array = array( );

			if ( !$template_pages ) {
				$sql = $wpdb->prepare( "SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = %s", '_wp_page_template' );
				$template_pages = $wpdb->get_results( $sql );
				self::set_cache( $cache_key, $template_pages );
			}
			$theme_templates = get_page_templates();
			$max_pg_comments = get_option( 'comments_per_page' );

			foreach ( $template_pages as $page_data ) {

				$temp_name = $page_data->meta_value;

				if ( !in_array( $temp_name, $theme_templates ) ) {
					continue;
				}
				
				if ( isset( $template_page_array[ $temp_name ][ 'pagination' ] ) && isset( $template_page_array[ $temp_name ][ 'no_pagination' ] ) ) {
					continue;
				}

				$page = get_post( $page_data->post_id );

				if ( !isset( $template_page_array[ $temp_name ][ 'comments' ] ) ) {
					$template_page_array[ $temp_name ][ 'comments' ] = post_type_supports( $page->post_type, 'comments' );
				}
				
				$post_pages = self::get_post_pages( $page->post_content );

				if ( $post_pages > 1 && !isset( $template_page_array[ $temp_name ][ 'pagination' ] ) ) {// if we have no paginated post sample, store it
					$template_page_array[ $temp_name ][ 'pagination' ][ 'id' ] = $page_data->post_id;
					$template_page_array[ $temp_name ][ 'pagination' ][ 'pages_no' ] = $post_pages;
				} elseif ( $post_pages <= 1 && !isset( $template_page_array[ $temp_name ][ 'no_pagination' ] ) ) { // if we have no non paginated sample store it.
					$template_page_array[ $temp_name ][ 'no_pagination' ] = $page_data->post_id;
				}

				if ( $template_page_array[ $temp_name ][ 'comments' ] && $page->comment_count ) {
					if ( $page->comment_count > $max_pg_comments ) {
						$pages_no = (int) $page->comment_count / $max_pg_comments;
						$template_page_array[ $temp_name ][ 'paginated_com' ][ 'id' ] = $page_data->post_id;
						$template_page_array[ $temp_name ][ 'paginated_com' ][ 'pages_no' ] = ceil( $pages_no ); //no of comment pages for the post					
					} else {
						$template_page_array[ $temp_name ][ 'no_pagi_com' ] = $page_data->post_id; // post with no paginated comments retrieved
					}
				}
			}
			return $template_page_array;
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
				}else{
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

	}

	//End of classLink_Bot Class
}
