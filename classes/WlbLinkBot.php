<?php

if (!class_exists('classLink_Bot')) {

    /**
     * Main / front controller class
     *
     */
    class classLink_Bot {

        /**
         * Constructor class this case calls the register hook function which is a collection of hooks and filters
         */
        function __construct() {
            $this->register_hook_callbacks();
        }

        /**
         * Register callbacks for actions and filters
         */
        public function register_hook_callbacks() {
            add_action('init', array($this, 'init'));
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
         * View the rewrite rules for the site
         */
        function view_rules() {
            print_r($this->generate_links());
            // print_r(get_option('permalink_structure'));
            //  print_r(get_option('page_for_posts'));
        }

        /*
         * This function generates a list of possible link scenarios for a WordPress site
         * @sanitization use esc_url in displaying these links in the view.
         */

        public function generate_links() {
            $link_array = array();
            $home_url = get_home_url();
            $blog_view = $this->get_blog_view_vars();
            $blog_page = get_option('page_for_posts');

            $link_array['home_pg']['normal_link'] = $this->link_a_rule($home_url, null);

            $link_array['blog_pg']['normal_link'] = ($blog_page) ? $this->link_a_rule(get_permalink($blog_page), 'page') : '--';
            if ($blog_view['no_of_pages'] > 1) {
                $link_array['blog_pg']['paginated_link'] = ($blog_page) ? $this->link_a_rule($this->get_paginated_link($blog_page, 2), 'page') : '--';
            }
            $link_array['blog_pg']['ex_pgl'] = ($blog_page) ? $this->link_a_rule($this->get_paginated_link($blog_page, $blog_view['no_of_pages'] + 7), 'page') : '--';

            //search page
            $link_array['search']['results'] = add_query_arg('s', 'a', $home_url);
            if ($blog_view['no_of_pages'] > 1) {
                $link_array['search']['paginated_results'] = add_query_arg('s', 'a', $this->get_paginated_link(null, 2));
            }
            $link_array['search']['not_found'] = add_query_arg('s', '$#zxy*xyz#$', $home_url);


            // special pages
            $special_pages = self::get_special_pages();
            if (!empty($special_pages)) {
                foreach ($special_pages as $type => $data) {
                    $link_array[$type]['normal_link'] = isset($data['no_pagi']) ? $this->link_a_rule(get_permalink($data['no_pagi']), $type) : '--';
                    $link_array[$type]['paginated_link'] = isset($data['pagi']) ? $this->link_a_rule(get_permalink($data['pagi']['id']), $type) : '--';
                    $link_array[$type]['ex_pgl'] = isset($data['pagi']) ? $this->link_a_rule($this->get_paginated_link($data['pagi']['id'], ((int) ($data['pagi']['pg_no'] + 7))), $type) : '--';
                }
            }

            $post_link_ids = self::get_post_link_ids();

            foreach ($post_link_ids as $type => $data) {
                $link_array[$type]['normal_link'] = isset($data['no_pagi']) ? $this->link_a_rule(get_permalink($data['no_pagi']), $type) : '--';
                $link_array[$type]['paginated_link'] = isset($data['pagi']) ? $this->link_a_rule($this->get_paginated_link($data['pagi']['id'], 2), $type) : '--';
                $link_array[$type]['pagination_exceed'] = isset($data['pagi']) ? $this->link_a_rule($this->get_paginated_link($data['pagi']['id'], ((int) ($data['pagi']['pg_no'] + 7))), $type) : '--';
                $link_array[$type]['comments_link'] = isset($data['no_pagi_com']) ? $this->link_a_rule(get_permalink($data['no_pagi_com']), $type) : '--';
                $link_array[$type]['comments_pagi_link'] = isset($data['pagi_com']) ? $this->link_a_rule(get_permalink($data['pagi_com']['id']), $type) : '--';
                $link_array[$type]['pagination_exceed'] = isset($data['pagi_com']) ? $this->link_a_rule($this->get_comment_pagenum_link($data['pagi_com']['id'], ((int) ($data['pagi_com']['pg_no'] + 7))), $type) : '--';
            }

            $taxonomy_terms = self::get_cat_terms();

            foreach ($taxonomy_terms as $term_name => $term) {
                $link_array[$term_name]['normal_link'] = $this->link_a_rule(get_term_link($term[0]), $term_name);
                $link_array[$term_name]['ex_pgl'] = '';
            }

            return $link_array;
        }

        /* Matches a link to its rule */

        public function link_a_rule($link, $type) {
            $rules = Rewrite_Rules::get_link_rules($link, $type);
            return array($link => $rules);
        }

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
                if (!isset($id)) {//if Id is not set, treat as home page
                    $url = trailingslashit(get_home_url()) . user_trailingslashit("$wp_rewrite->pagination_base/" . $i, 'single_paged');
                } elseif ('' == get_option('permalink_structure')) {
                    $url = add_query_arg('page', $i, get_permalink());
                } elseif ('page' == get_option('show_on_front') && get_option('page_on_front') == $id || get_option('page_for_posts') == $id) {
                    $url = trailingslashit(get_permalink($id)) . user_trailingslashit("$wp_rewrite->pagination_base/" . $i, 'single_paged');
                } else {
                    $url = trailingslashit(get_permalink($id)) . user_trailingslashit($i, 'single_paged');
                }
            }

            return $url;
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

            $output = 'names'; // names or objects, note names is the default
            $operator = 'and'; // 'and' or 'or'

            $post_types = get_post_types($args, $output, $operator);
            $posts_w_comments = self::get_comment_posts();
            $max_pg_comments = get_option('comments_per_page');

            foreach ($post_types as $post_type) {
                $post_ids = array();
                $args = array(
                    'post_type' => $post_type,
                    'posts_per_page' => 100, //sample space of 100posts
                    'post_status' => self::display_status($post_type),
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                );

                $posts = new WP_Query($args);
                if ($posts->have_posts()) {
                    $pagi = $no_pagi = $no_pagi_com = $pagi_com = false;
                    $use_cases = 0; // We shall require 4 use cases to exit the loop. start at 0 incrementing as we go along.
                    while ($posts->have_posts()) {
                        $post = $posts->next_post();

                        $content = $post->post_content;
                        $numpages = self::get_post_pages($content); //search if the post is paginated

                        if (!$pagi && $numpages > 1) {
                            $post_ids['pagi'] = array('id' => $post->ID, 'pg_no' => $numpages); // post is paginated pg_no is the no of pages it has
                            $pagi = true;
                            $use_cases++;
                        } elseif (!$no_pagi) {
                            $post_ids['no_pagi'] = $post->ID; // non paginated post ID is retrieved
                            $no_pagi = true;
                            $use_cases++;
                        }
                        if (isset($posts_w_comments[$post->ID])) {
                            if ($posts_w_comments[$post->ID] > $max_pg_comments && !$pagi_com) {
                                $pg_no = (int) $posts_w_comments[$post->ID] / $max_pg_comments;
                                $post_ids['pagi_com']['id'] = $post->ID; //post with paginated comments retrieved								
                                $post_ids['pagi_com']['pg_no'] = ceil($pg_no); //no of comment pages for the post					
                                $pagi_com = true;
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

        public static function get_cat_terms() {

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

        public static function get_special_pages() {
            global $wpdb;
            $special_pg_array = array();
            $sql = $wpdb->prepare("SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = %s", '_wp_page_template');
            $special_pages = $wpdb->get_results($sql);
            $theme_templates = get_page_templates();

            foreach ($special_pages as $spage) {
                if ($spage->meta_value == 'default' || !in_array($spage->meta_value, $theme_templates))
                    continue;
                if (isset($special_pg_array[$spage->meta_value]['pagi']) && isset($special_pg_array[$spage->meta_value]['no_pagi']))
                    continue;
                $page = get_post($spage->post_id);
                $post_pages = self::get_post_pages($page->post_content);
                if ($post_pages > 1 && !isset($special_pg_array[$spage->meta_value]['pagi'])) {// if we have no paginated post sample, store it
                    $special_pg_array[$spage->meta_value]['pagi'] = $spage->post_id;
                } elseif ($post_pages <= 1 && !isset($special_pg_array[$spage->meta_value]['no_pagi'])) { // if we have no non paginated sample store it.
                    $special_pg_array[$spage->meta_value]['no_pagi'] = $spage->post_id;
                }
            }
            return $special_pg_array;
        }

        public static function get_comment_posts() {
            global $wpdb;
            $comment_pg_array = array();
            $id_coment_arr = $wpdb->get_col("SELECT comment_post_ID FROM $wpdb->comments");
            return array_count_values($id_coment_arr); // return the number of comments per ID
        }

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

        public static function display_status($type) {
            switch ($type) {
                case 'attachment':
                    return 'inherit';
                default:
                    return 'publish';
            }
        }

    }

}
