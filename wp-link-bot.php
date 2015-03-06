<?php

if (!class_exists('Email_Manager')) {

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
         * @mvc Controller
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
            //print_r(self::get_special_pages());
        }

        public function generate_links() {
            $link_array = array();
            $link_array['home_pg']['normal_link'] = $this->link_a_rule(get_home_url(), null);

            $blog_page = get_option('page_for_posts');
            $blog_page_no = get_option('posts_per_page');

            $link_array['blog_pg']['normal_link'] = ($blog_page) ? $this->link_a_rule(get_permalink($blog_page), 'page') : '--';
            $link_array['blog_pg']['ex_pgl'] = ($blog_page) ? $this->link_a_rule($this->get_paginated_link($blog_page, $blog_page_no + 7), 'page') : '--';

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
                $link_array[$type]['paginated_link'] = isset($data['pagi']) ? $this->link_a_rule(get_permalink($data['pagi']['id']), $type) : '--';
                $link_array[$type]['ex_pgl'] = isset($data['pagi']) ? $this->link_a_rule($this->get_paginated_link($data['pagi']['id'], ((int) ($data['pagi']['pg_no'] + 7))), $type) : '--';
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

        /**
         * Helper function for wp_link_pages().
         *
         * @since 3.1.0
         * @access private
         *
         * @param int $i Page number.
         * @return string Link.
         */
        function get_paginated_link($id, $i) {
            global $wp_rewrite;

            if (1 == $i) {
                $url = get_permalink($id);
            } else {
                if ('' == get_option('permalink_structure'))
                    $url = add_query_arg('page', $i, get_permalink());
                elseif ('page' == get_option('show_on_front') && get_option('page_on_front') == $id)
                    $url = trailingslashit(get_permalink($id)) . user_trailingslashit("$wp_rewrite->pagination_base/" . $i, 'single_paged');
                else
                    $url = trailingslashit(get_permalink($id)) . user_trailingslashit($i, 'single_paged');
            }

            return $url;
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

            foreach ($post_types as $post_type) {
                $post_ids = array();
                $args = array(
                    'post_type' => $post_type,
                    'numberposts' => 999,
                    'post_status' => 'publish',
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                );

                $posts = new WP_Query($args);
                if ($posts->have_posts()) {
                    $pagi = $no_pagi = false;
                    while ($posts->have_posts()) {
                        $post = $posts->next_post();
                        //search if the post is paginated
                        $content = $post->post_content;
                        $numpages = self::get_post_pages($content);

                        if (!$pagi && $numpages > 1) {
                            $post_ids['pagi'] = array('id' => $post->ID, 'pg_no' => $numpages);
                            $pagi = true; // post is paginated
                            continue;
                        } elseif (!$no_pagi) { //if we don't have a non paginated ID in this type
                            $post_ids['no_pagi'] = $post->ID;
                            $no_pagi = true;
                        }

                        if ($pagi && $no_pagi) {
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

    }

}
