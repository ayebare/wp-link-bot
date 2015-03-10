<?php

if (!class_exists('classLink_Bot')) {

    /**
     * Main / front controller class
     *
     */
    class classLink_Bot {

        protected static $cache_group;
        protected static $cache_time;

        /**
         * Constructor class this case calls the register hook function which is a collection of hooks and filters
         */
        function __construct() {
            self::$cache_group = 'wblink_query';
            self:$cache_time = 3600; //one hour cache
            $this->register_hook_callbacks();
        }

        /**
         * Register callbacks for actions and filters
         */
        public function register_hook_callbacks() {
            add_action('init', array($this, 'init'));
            add_action('save_post', array($this, 'delete_cache'));
        }

        /**
         * Initializes variables
         *
         */
        public function init() {
            if (!is_admin()) {
                return;
            }
            add_action('admin_menu', array($this, 'action_admin_menu'));
        }

        /**
         * Add our sub-menu page to the VIP dashboard navigation
         */
        public function action_admin_menu() {

            add_submenu_page('tools.php', __('WP Urls', 'wp-link-bot'), __('Front End URLs', 'wp-link-bot'), 'manage_options', 'wp-link-bot', array($this, 'view_rules'));
        }

        /**
         * View the urls plus their rewrite rules for the site
         */
        function view_rules() {
            print_r($this->generate_links());
        }

        /*
         * This function generates a list of possible link scenarios for a WordPress site
         * @sanitization use esc_url in displaying these links in the view.
         * @return array. multidimensional array of links	 
         * [month_archive] => Array
          [normal_link] => Array
          (
          [http://localhost/wp4.1.1/2015/03/] => [^/]+/([^/]+)/?$
          )

          [paginated_link] => Array
          (
          [http://localhost/wp4.1.1/2015/03/page/2/] => (.?.+?)(/[0-9]+)?/?$
          )

         */

        public function generate_links() {
            $link_array = array();
            $home_url = get_home_url();
            $blog_view = $this->get_blog_view_vars();
            $blog_page = get_option('page_for_posts');

            $link_array['home_pg']['normal_link'] = $this->link_a_rule($home_url, null);
            $link_array['home_pg']['404'] = $this->link_a_rule($home_url . '/zyxwvutsr10up', null);


            $link_array['blog_pg']['normal_link'] = ($blog_page) ? $this->link_a_rule(get_permalink($blog_page), 'page') : '--';
            if ($blog_view['no_of_pages'] > 1) {
                $link_array['blog_pg']['paginated_link'] = ($blog_page) ? $this->link_a_rule($this->get_paginated_link($blog_page, 2), 'page') : '--';
            }
            $link_array['blog_pg']['ex_pgl'] = ($blog_page) ? $this->link_a_rule($this->get_paginated_link($blog_page, $blog_view['no_of_pages'] + 7), 'page') : '--';

            //search page
            $link_array['search']['results'] = add_query_arg('s', 'a', $home_url);
            if ($blog_view['no_of_pages'] > 1) {
                $link_array['search']['paginated_results'] = add_query_arg('s', 'a', $this->search_pagination(2));
            }
            $link_array['search']['not_found'] = add_query_arg('s', 'zyxwvutsr10up', $home_url);


            // special pages
            $special_pages = self::get_template_pages();
            if (!empty($special_pages)) {
                foreach ($special_pages as $type => $data) {
                    $link_array[$type]['normal_link'] = isset($data['no_pagination']) ? $this->link_a_rule(get_permalink($data['no_pagination']), $type) : '--';
                    $link_array[$type]['paginated_link'] = isset($data['pagination']) ? $this->link_a_rule($this->get_paginated_link($data['pagination']['id'], 2), $type) : '--';
                    $link_array[$type]['pagination_exceed'] = isset($data['pagination']) ? $this->link_a_rule($this->get_paginated_link($data['pagination']['id'], ((int) ($data['pagination']['pages_no'] + 7))), $type) : '--';
                    if ($data['comments']) {
                        $link_array[$type]['comments_link'] = isset($data['no_pagi_com']) ? $this->link_a_rule(get_permalink($data['no_pagi_com']), $type) : '--';
                        $link_array[$type]['comments_pagi_link'] = isset($data['paginated_com']) ? $this->link_a_rule(get_permalink($data['paginated_com']['id']), $type) : '--';
                        $link_array[$type]['com_pagination_exceed'] = isset($data['paginated_com']) ? $this->link_a_rule($this->get_comment_pagenum_link($data['paginated_com']['id'], ((int) ($data['paginated_com']['pages_no'] + 7))), $type) : '--';
                    }
                }
            }

            // post links
            $post_link_ids = self::get_post_link_ids();

            foreach ($post_link_ids as $type => $data) {
                $link_array[$type]['normal_link'] = isset($data['no_pagination']) ? $this->link_a_rule(get_permalink($data['no_pagination']), $type) : '--';
                $link_array[$type]['paginated_link'] = isset($data['pagination']) ? $this->link_a_rule($this->get_paginated_link($data['pagination']['id'], 2), $type) : '--';
                $link_array[$type]['pagination_exceed'] = isset($data['pagination']) ? $this->link_a_rule($this->get_paginated_link($data['pagination']['id'], ((int) ($data['pagination']['pages_no'] + 7))), $type) : '--';
                if ($data['comments']) {
                    $link_array[$type]['comments_link'] = isset($data['no_pagi_com']) ? $this->link_a_rule(get_permalink($data['no_pagi_com']), $type) : '--';
                    $link_array[$type]['comments_pagi_link'] = isset($data['paginated_com']) ? $this->link_a_rule(get_permalink($data['paginated_com']['id']), $type) : '--';
                    $link_array[$type]['com_pagination_exceed'] = isset($data['paginated_com']) ? $this->link_a_rule($this->get_comment_pagenum_link($data['paginated_com']['id'], ((int) ($data['paginated_com']['pages_no'] + 7))), $type) : '--';
                }
            }

            //taxonomy term archive links
            $taxonomy_terms = self::get_tax_terms();

            foreach ($taxonomy_terms as $taxonomy => $terms) {
                $pagi_term = $no_pagi_term = false;

                foreach ($terms as $term) {
                    $no_of_pages = ceil($term->count / $blog_view['posts_per_page']);

                    if ($no_of_pages < 1 && $no_pagi_term == false) {
                        $link_array[$taxonomy]['normal_link'] = $this->link_a_rule(get_term_link($term), $taxonomy);
                        $no_pagi_term = true;
                    } elseif ($no_of_pages > 1 && $pagi_term == false) {
                        $link_array[$taxonomy]['paginated_link'] = $this->link_a_rule($this->term_pagination($term, 2), null);
                        $link_array[$taxonomy]['pagination_exceed'] = $this->link_a_rule($this->term_pagination($term, $blog_view['no_of_pages'] + 7), null);
                        $pagi_term = true;
                    }
                    if ($pagi_term && $no_pagi_term) {
                        break;
                    }
                }
            }

            //Year archive links
            $link_array['year_archive']['normal_link'] = $this->link_a_rule(get_year_link(date('Y')), null);

            $year_posts = $this->count_post_by_date(date("Y-m-d", strtotime("-1 year", time())));
            $year_pages = ceil($year_posts / $blog_view['posts_per_page']);
            if ($year_pages > 1) {
                $link_array['year_archive']['paginated_link'] = $this->link_a_rule($this->date_archive_pagination('year', 2), null);
                $link_array['year_archive']['pagination_exceed'] = $this->link_a_rule($this->date_archive_pagination('year', $year_pages + 7), null);
            }

            //Month archive links
            $link_array['month_archive']['normal_link'] = $this->link_a_rule(get_month_link(date('Y'), date('m')), null);
            $month_posts = $this->count_post_by_date(date("Y-m-d", strtotime("-1 month", time())));
            $month_pages = ceil($month_posts / $blog_view['posts_per_page']);
            if ($month_pages > 1) {
                $link_array['month_archive']['paginated_link'] = $this->link_a_rule($this->date_archive_pagination('month', 2), null);
                $link_array['month_archive']['pagination_exceed'] = $this->link_a_rule($this->date_archive_pagination('month', $month_pages + 7), null);
            }

            //Day Posts

            $link_array['day_archive']['normal_link'] = $this->link_a_rule(get_day_link(date('Y'), date('m'), date('d')), null);
            $day_posts = $this->count_post_by_date(date("Y-m-d"));
            $day_pages = ceil($day_posts / $blog_view['posts_per_page']);
            if ($day_pages > 1) {
                $link_array['day_archive']['paginated_link'] = $this->link_a_rule($this->date_archive_pagination('day', 2), null);
                $link_array['day_archive']['pagination_exceed'] = $this->link_a_rule($this->date_archive_pagination('day', $day_pages + 7), null);
            }

            return $link_array;
        }

        /*
         * Matches a link to its rule
         * $params $link, $type  the url in string format and the post type to which it belongs. 
         * @retun an array of a the link as the key and its corresponding re-write rule as the value
         */

        public function link_a_rule($link, $type=null) {
            $rules = Rewrite_Rules::get_link_rules($link, $type);
            return array($link => $rules);
        }

        /*
         * Returns an array containing the site settings for page for posts as set in the settings->reading options,
         * The number of posts per page configured in the settings->reading options
         * Number of published posts. This is helpfull in calculating how many sub pages are expected in blog-view
         * Number of pages. This is calculated by deviding the number of posts by the number of posts per page
         * @return array 
         */

        public function get_blog_view_vars() {
            $vars = array();
            $vars['page_for_posts'] = get_option('page_for_posts');
            $vars['posts_per_page'] = get_option('posts_per_page');
            $vars['no_of_posts'] = wp_count_posts('post')->publish;
            $vars['no_of_pages'] = ceil($vars['no_of_posts'] / $vars['posts_per_page']);
            return $vars;
        }

        /**
         * Retrieve the url of paginated post given the post id and  page index number 
         *
         * @param int $i Page number.
         * @param int $id Page id
         * @return string Link.
         */
        function get_paginated_link($id=null, $i) {
            global $wp_rewrite;


            if (1 == $i) {
                $url = get_permalink($id);
            } else {
                if ('' == get_option('permalink_structure')) {
                    $url = add_query_arg('page', $i, get_permalink());
                } elseif ('page' == get_option('show_on_front') && get_option('page_on_front') == $id || get_option('page_for_posts') == $id) {
                    $url = $this->add_pagination_page_2_url(get_permalink($id), $i);
                } else {
                    $url = trailingslashit(get_permalink($id)) . user_trailingslashit($i, 'single_paged');
                }
            }

            return $url;
        }

        /*
         * Returns a paginated date archive url
         * @param $date the date for the archived posts to display
         * @param $index the page index of the paginated url e.g http://site.com/2015/03/page/10/ has index 10/
         * @retun string. url string of the paginated date archive at index $index
         */

        public function date_archive_pagination($date, $index) {
            switch ($date) {
                case 'day':
                    $url = get_day_link(date('Y'), date('m'), date('d'));
                    break;
                case 'month':
                    $url = get_month_link(date('Y'), date('m'));
                    break;
                default:
                    $url = get_year_link(date('Y'));
                    break;
            }
            return $this->add_pagination_page_2_url($url, $index);
        }

        /*
         * Returns a paginated terms archive url
         * @param object $term the term object for which the url is to be generated
         * @param $index the page index of the paginated url 
         * @retun string. url string of the paginated term archive at index $index
         */

        public function term_pagination($term, $index) {
            return $this->add_pagination_page_2_url(get_term_link($term), $index);
        }

        /*
         * Returns a paginated search url
         * @param $index the page index of the paginated url 
         * @retun string. url string of the paginated search results at index $index
         */

        public function search_pagination($index) {
            return $this->add_pagination_page_2_url(get_home_url(), $index);
        }

        public function add_pagination_page_2_url($url, $index) {
            global $wp_rewrite;
            return trailingslashit($url) . user_trailingslashit("$wp_rewrite->pagination_base/" . $index, 'single_paged');
        }

        /**
         * Retrieve comments page number link.
         *
         * @param int $pg_id
         * @return string The comments page number link URL.
         */
        function get_comment_pagenum_link($pg_id, $index) {
            global $wp_rewrite;

            $pagenum = (int) $index;

            $result = get_permalink($pg_id);

            if ($wp_rewrite->using_permalinks())
                $result = user_trailingslashit(trailingslashit($result) . 'comment-page-' . $pagenum, 'commentpaged');
            else
                $result = add_query_arg('cpage', $pagenum, $result);


            $result .= '#comments';

            /**
             * Apply wordpress comment link filter
             *
             * @param string $result The comments page number link.
             */
            $result = apply_filters('get_comments_pagenum_link', $result);

            return $result;
        }

        /* Get ordinary post link, Post with paginated comment, post with pagination
         * Get all post types and do an individual WP_Query as opposed to doing a query on all to prevent heavy queries
         *
         */

        public static function get_post_link_ids() {

            // get all public post types
            $return_ids = array();


            // get all public post types
            $args = array(
                'public' => true,
            );

            $output = 'names';
            $operator = 'and'; // 'and' or 'or'

            $post_types = get_post_types($args, $output, $operator);
            $posts_w_comments = self::get_comment_posts();
            $max_pg_comments = get_option('comments_per_page');

            foreach ($post_types as $post_type) {
                $post_ids = array();
                $post_ids['comments'] = post_type_supports($post_type, 'comments');

                $args = array(
                    'post_type' => $post_type,
                    'posts_per_page' => 100, //sample space of 100posts
                    'post_status' => self::display_status($post_type),
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                );

                $cache_key = 'all_test_posts'.serialize($args);
                $posts = wp_cache_get($cache_key, self::$cache_group);
                if (!$posts) {
                    $posts = new WP_Query($args);
                    wp_cache_set($cache_key, $posts, self::$cache_group, self::$cache_time);
                }
                if ($posts->have_posts()) {
                    $pagination = $no_pagination = $no_pagi_com = $paginated_com = false;
                    $use_cases = 0; // We shall require 4 use cases to exit the loop. start at 0 incrementing as we go along.

                    while ($posts->have_posts()) {
                        $post = $posts->next_post();

                        $content = $post->post_content;
                        $numpages = self::get_post_pages($content); //search if the post is paginated

                        if (!$pagination && $numpages > 1) {
                            $post_ids['pagination'] = array('id' => $post->ID, 'pages_no' => $numpages); // post is paginated pages_no is the no of pages it has
                            $pagination = true;
                            $use_cases++;
                        } elseif (!$no_pagination) {
                            $post_ids['no_pagination'] = $post->ID; // non paginated post ID is retrieved
                            $no_pagination = true;
                            $use_cases++;
                        }
                        if (isset($posts_w_comments[$post->ID])) {
                            if ($posts_w_comments[$post->ID] > $max_pg_comments && !$paginated_com) {
                                $pages_no = (int) $posts_w_comments[$post->ID] / $max_pg_comments;
                                $post_ids['paginated_com']['id'] = $post->ID; //post with paginated comments retrieved								
                                $post_ids['paginated_com']['pages_no'] = ceil($pages_no); //no of comment pages for the post					
                                $paginated_com = true;
                                $use_cases++;
                            } elseif (!$no_pagi_com) {
                                $post_ids['no_pagi_com'] = $post->ID; // post with no paginated comments retrieved
                                $no_pagi_com = true;
                                $use_cases++;
                            }
                        }

                        if ($use_cases == 4) {
                            break; // job done, get out o here!
                        }
                    }
                }
                $return_ids[$post_type] = $post_ids;
            }
            return $return_ids;
        }

        /*
         * Used to get an array of all public taxonomy terms on the site
         * @return array of taxonomy keys as indices and their terms as values
         */

        public static function get_tax_terms() {

            $return_terms = array();
            $output = 'names'; // or objects
            $operator = 'and'; // 'and' or 'or'

            $args = array(
                'public' => true,
            );
            $taxonomies = get_taxonomies($args, $output, $operator);

            $args = array(
                'hide_empty' => true,
            );


            if ($taxonomies) {
                foreach ($taxonomies as $taxonomy) {
                    $terms = get_terms($taxonomy, $args);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $return_terms[$taxonomy] = $terms;
                    }
                }

                return $return_terms;
            }

            // endLink_Bot
        }

        /*
         * Returns an array of each instance of a page template assigned to page.  http://codex.wordpress.org/Page_Templates
         * The array comprises pages that have been assined page templates and they have pagnination, no paginaction, comments, no comments. one 
         * Instance of each is collected.
         * @retun multidimentional array 
         */

        public static function get_template_pages() {
            global $wpdb;
            $cache_key = 'special-pages';
            $template_page_array = wp_cache_get($cache_key, self::$cache_group);

            if (!$template_page_array) {
                $sql = $wpdb->prepare("SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = %s", '_wp_page_template');
                $special_pages = $wpdb->get_results($sql);
                wp_cache_set($cache_key, $special_pages, self::$cache_group, self::$cache_time);
            }

            $theme_templates = get_page_templates();
            $max_pg_comments = get_option('comments_per_page');
            foreach ($special_pages as $spage) {
                $temp_name = $spage->meta_value;

                if (!in_array($temp_name, $theme_templates))
                    continue;
                if (isset($template_page_array[$temp_name]['pagination']) && isset($template_page_array[$temp_name]['no_pagination']))
                    continue;
                $page = get_post($spage->post_id);
                if (!isset($template_page_array[$temp_name]['comments'])) {
                    $template_page_array[$temp_name]['comments'] = post_type_supports($page->post_type, 'comments');
                }

                $post_pages = self::get_post_pages($page->post_content);
                if ($post_pages > 1 && !isset($template_page_array[$temp_name]['pagination'])) {// if we have no paginated post sample, store it
                    $template_page_array[$temp_name]['pagination']['id'] = $spage->post_id;
                    $template_page_array[$temp_name]['pagination']['pages_no'] = $post_pages;
                } elseif ($post_pages <= 1 && !isset($template_page_array[$temp_name]['no_pagination'])) { // if we have no non paginated sample store it.
                    $template_page_array[$temp_name]['no_pagination'] = $spage->post_id;
                }
                if ($template_page_array[$temp_name]['comments'] && $page->comment_count) {

                    if ($page->comment_count > $max_pg_comments) {
                        $pages_no = (int) $page->comment_count / $max_pg_comments;
                        $template_page_array[$temp_name]['paginated_com']['id'] = $spage->post_id;
                        $template_page_array[$temp_name]['paginated_com']['pages_no'] = ceil($pages_no); //no of comment pages for the post					
                    } else {
                        $template_page_array[$temp_name]['no_pagi_com'] = $spage->post_id; // post with no paginated comments retrieved
                    }
                }
            }
            return $template_page_array;
        }

        /*
         * Used to fetch an array of posts that have comments
         * @return a multidimensional array of post ID's of posts that have comments as keys and number of comments the post has as values
         */

        public static function get_comment_posts() {
            global $wpdb;
            $cache_key = 'posts_wit_commets';
            $id_coment_arr = wp_cache_get($cache_key, self::$cache_group);
            if (!$id_coment_arr) {
                $id_coment_arr = $wpdb->get_col("SELECT comment_post_ID FROM $wpdb->comments");
                wp_cache_set($cache_key, $id_coment_arr, self::$cache_group, self::$cache_time);
            }
            return array_count_values($id_coment_arr); // return the number of comments per ID
        }

        /*
         * Determines if a post has pagination by seeking presence of <!--nextpage--> in its content
         * @return 1 if no pages are found. (i.e 1 for one page) and number of pages found if many subpages exist in the post
         */

        public static function get_post_pages($content) {
            if (false !== strpos($content, '<!--nextpage-->')) {

                // Ignore nextpage at the beginning of the content.
                if (0 === strpos($content, '<!--nextpage-->'))
                    $content = substr($content, 15);

                $pages = explode('<!--nextpage-->', $content);
                $numpages = count($pages);
                if ($numpages > 1) {
                    return $numpages;
                }else
                    return 1;
            }
            return 1;
        }

        /*
         * This is a helper function that is used by get_post_link_ids to determine the status of posts to search for
         * It is used in conjunction with the post type to decide what status of the post is applicable or considered viewable to the public.
         * @param $type. The post type.
         * @return string. the public accessible post type
         */

        public static function display_status($type) {
            switch ($type) {
                case 'attachment':
                    return 'inherit';
                default:
                    return 'publish';
            }
        }

        /*
         * Retuns the number of posts created before the $date object
         *
         * @param int $date date object
         * @return archive link string according to specified date
         */

        public function count_post_by_date($date) {
            global $wpdb;
            $cache_key = 'postcount' . $date;
            $count = wp_cache_get($cache_key, self::$cache_group);

            if (!$count) {
                $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(100) FROM $wpdb->posts
      		 WHERE 1=1  AND ( wp_posts.post_date > %s) 
			 AND wp_posts.post_type = 'post' 
			 AND (wp_posts.post_status = 'publish' 
			 OR wp_posts.post_status = 'private')", $date));

                wp_cache_set($cache_key, $count, self::$cache_group, self::$cache_time);
            }

            return absint($count);
        }

        /*
         * Delete cache if post is saved. This is a caller function for save_post
         * Since cache has been set to an hour, It makes sense to update it whenever a post is saved.
         * @retun void
         */

        public function delete_cache($post_id) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return;

            if (!$post = get_post($post_id))
                return;

            if ('auto-draft' == $post->post_status)
                return;

            global $wp_object_cache;

            if (isset($wp_object_cache->cache[self::$cache_group])) {
                foreach ($wp_object_cache->cache[self::$cache_group] as $k => $v) {
                    wp_cache_delete($k, $group);
                }
            }
        }

    } //End of classLink_Bot Class
     
}
