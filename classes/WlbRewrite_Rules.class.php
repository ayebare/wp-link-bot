<?php

class Rewrite_Rules {

    protected $parent_slug = 'tools.php';
    protected $page_slug = 'wp-link-bot';
    protected $view_cap = 'manage_options';
    protected $flushing_enabled = true;
    static $sources = array();
    static $rewrite_rules;
    protected static $readableProperties = array('flushing_enabled', 'sources');  // These should really be constants, but PHP doesn't allow class constants to be arrays

    /**
     * Construct the plugin
     */

    function __construct() {

        // This plugin only runs in the admin, but we need it initialized on init
        add_action('init', array($this, 'action_init'));
    }

    /**
     * Public getter for protected variables
     * @param string $variable
     * @return mixed
     */
    public function __get($variable) {
        if (in_array($variable, self::$readableProperties))
            return $this->$variable;
        else
            throw new Exception(__METHOD__ . " error: $" . $variable . " doesn't exist or isn't readable.");
    }

    /**
     * Initialize the plugin
     */
    function action_init() {

        if (!is_admin())
            return;

        // Whether or not users can flush the rewrite rules from this tool
        $this->flushing_enabled = apply_filters('rri_flushing_enabled', $this->flushing_enabled);
        self::$rewrite_rules = self::get_rewrite_rules();


        // User actions available for the rewrite rules page

        if (isset($_GET['page'], $_GET['action']) && $_GET['page'] == $this->page_slug && $_GET['action'] == 'flush-rules')
            add_action('admin_init', array($this, 'flush_rules'));
        elseif (isset($_GET['page'], $_GET['message']) && $_GET['page'] == $this->page_slug && $_GET['message'] == 'flush-success')
            add_action('admin_notices', array($this, 'action_admin_notices'));
    }

    /**
     * Show a message when you've successfully flushed your rewrite rules
     */
    function action_admin_notices() {
        echo '<div class="message updated"><p>' . __('Rewrite rules flushed.', 'wp-link-bot') . '</p></div>';
    }

    public static function get_rewrite_rules() {
        global $wp_rewrite;
        // Track down which rewrite rules are associated with which methods by breaking it down
        $rewrite_rules_by_source = array();
        $rewrite_rules_by_source['post'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->permalink_structure, EP_PERMALINK);
        $rewrite_rules_by_source['date'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->get_date_permastruct(), EP_DATE);
        $rewrite_rules_by_source['root'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->root . '/', EP_ROOT);
        $rewrite_rules_by_source['comments'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->root . $wp_rewrite->comments_base, EP_COMMENTS, true, true, true, false);
        $rewrite_rules_by_source['search'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->get_search_permastruct(), EP_SEARCH);
        $rewrite_rules_by_source['author'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->get_author_permastruct(), EP_AUTHORS);
        $rewrite_rules_by_source['page'] = $wp_rewrite->page_rewrite_rules();

        // Extra permastructs including tags, categories, etc.
        foreach ($wp_rewrite->extra_permastructs as $permastructname => $permastruct) {
            if (is_array($permastruct)) {
                // Pre 3.4 compat
                if (count($permastruct) == 2)
                    $rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules($permastruct[0], $permastruct[1]);
                else
                    $rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules($permastruct['struct'], $permastruct['ep_mask'], $permastruct['paged'], $permastruct['feed'], $permastruct['forcomments'], $permastruct['walk_dirs'], $permastruct['endpoints']);
            } else {
                $rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules($permastruct, EP_NONE);
            }
        }
        return $rewrite_rules_by_source;
    }

    /**
     * Get the rewrite rules for the current view
     */
    public static function get_link_rules($link, $type) {
        global $wp_rewrite;
        $rewrite_rules_by_source = self::$rewrite_rules;
        $rewrite_rules_array = array();
        $rewrite_rules = get_option('rewrite_rules');
        if (!$rewrite_rules)
            $rewrite_rules = array();

        // Apply the filters used in core just in case
        foreach ($rewrite_rules_by_source as $source => $rules) {
            $rewrite_rules_by_source[$source] = apply_filters($source . '_rewrite_rules', $rules);
            if ('post_tag' == $source)
                $rewrite_rules_by_source[$source] = apply_filters('tag_rewrite_rules', $rules);
        }

        foreach ($rewrite_rules as $rule => $rewrite) {
            $rewrite_rules_array[$rule]['rewrite'] = $rewrite;
            foreach ($rewrite_rules_by_source as $source => $rules) {
                if (array_key_exists($rule, $rules)) {
                    $rewrite_rules_array[$rule]['source'] = $source;
                }
            }
            if (!isset($rewrite_rules_array[$rule]['source']))
                $rewrite_rules_array[$rule]['source'] = apply_filters('rewrite_class_source', 'other', $rule, $rewrite);
        }

        // Find any rewrite rules that should've been generated but weren't
        $maybe_missing = $wp_rewrite->rewrite_rules();
        $missing_rules = array();
        $rewrite_rules_array = array_reverse($rewrite_rules_array, true);
        foreach ($maybe_missing as $rule => $rewrite) {
            if (!array_key_exists($rule, $rewrite_rules_array)) {
                $rewrite_rules_array[$rule] = array(
                    'rewrite' => $rewrite,
                );
            }
        }
        // Prepend rules so it's obvious
        $rewrite_rules_array = array_reverse($rewrite_rules_array, true);

        // Allow static sources of rewrite rules to override, etc.
        $rewrite_rules_array = apply_filters('rri_rewrite_rules', $rewrite_rules_array);
        // Set the sources used in our filtering
        $sources = array('all');
        foreach ($rewrite_rules_array as $rule => $data) {
            $sources[] = $data['source'];
        }
        self::$sources = array_unique($sources);

        if (!empty($link)) {
            $match_path = parse_url(esc_url($link), PHP_URL_PATH);
            $wordpress_subdir_for_site = parse_url(home_url(), PHP_URL_PATH);
            if (!empty($wordpress_subdir_for_site)) {
                $match_path = str_replace($wordpress_subdir_for_site, '', $match_path);
            }
            $match_path = ltrim($match_path, '/');
        }

        $should_filter_by_source = !empty($type) && 'all' !== $type && in_array($type, self::$sources);

        // Filter based on match or source if necessary
        foreach ($rewrite_rules_array as $rule => $data) {
            // If we're searching rules based on URL and there's no match, don't return it
            if (!empty($match_path) && !preg_match("!^$rule!", $match_path)) {
                unset($rewrite_rules_array[$rule]);
            } elseif ($should_filter_by_source && $data['source'] != $type) {
                unset($rewrite_rules_array[$rule]);
            }
        }
        $rewrite_rule ='';
        foreach ($rewrite_rules_array as $rewrite_rule => $rewrite_data) {
            $rewrite_data['rule'] = $rewrite_rule;
        }

        // Return our array of rewrite rules to be used
        return $rewrite_rule;
    }

    /**
     * Allow a user to flush rewrite rules for their site
     */
    function flush_rules() {
        global $plugin_page;

        // Check nonce and permissions
        check_admin_referer('flush-rules');
        if (!current_user_can($this->view_cap) || !$this->flushing_enabled)
            wp_die(__('You do not have permissions to perform this action.'));

        flush_rewrite_rules(false);
        do_action('rri_flush_rules');

        // Woo hoo!
        $args = array(
            'message' => 'flush-success',
        );
        wp_safe_redirect(add_query_arg($args, menu_page_url($plugin_page, false)));
        exit;
    }

}
