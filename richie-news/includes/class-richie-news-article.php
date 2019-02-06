<?php

class Richie_News_Article {

    // values for metered_paywall
    const METERED_PAYWALL_NO_ACCESS_VALUE = 'no_access';
    const METERED_PAYWALL_METERED_VALUE = 'metered';
    const METERED_PAYWALL_FREE_VALUE = 'free';

    private $news_options;

    function __construct($richie_news_options) {
        $this->news_options = $richie_news_options;

        function start_wp_head_buffer() {
            ob_start();
        }
        add_action('wp_head','start_wp_head_buffer',0);

        function end_wp_head_buffer() {
            global $richie_cached_head;
            if (empty($richie_cached_head)) {
                $richie_cached_head = ob_get_clean();
            } else {
                ob_end_clean();
            }
            remove_action('wp_head', 'start_wp_head_buffer');
            remove_action('wp_head', 'end_wp_head_buffer');
        }
        add_action('wp_head','end_wp_head_buffer', PHP_INT_MAX); //PHP_INT_MAX will ensure this action is called after all other actions that can modify head
        add_action('wp_head', array($this, 'cached_head'), PHP_INT_MAX);


        function start_wp_footer_buffer() {
            ob_start();
        }
        add_action('wp_footer','start_wp_footer_buffer', 0);

        function end_wp_footer_buffer() {
            global $richie_cached_footer;
            if (empty($richie_cached_footer)) {
                $richie_cached_footer = ob_get_clean();
            } else {
                ob_end_clean();
            }
            remove_action('wp_footer', 'start_wp_footer_buffer');
            remove_action('wp_footer', 'end_wp_footer_buffer');
        }
        add_action('wp_footer','end_wp_footer_buffer', PHP_INT_MAX); //PHP_INT_MAX will ensure this action is called after all other actions that can modify head
        add_action('wp_footer', array($this, 'cached_footer'), PHP_INT_MAX);

    }

    public function cached_head() {
        global $richie_cached_head;
        echo $richie_cached_head;
    }

    public function cached_footer() {
        global $richie_cached_footer;
        echo $richie_cached_footer;
    }


    public function richie_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
        return true;
    }

    private function render_template($name, $post_id) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID, $wp_styles, $wp_filter;
        require_once plugin_dir_path( __FILE__ ) . 'class-richie-news-template-loader.php';
        $richie_news_template_loader = new Richie_News_Template_Loader;
        $wp_query = new WP_Query(array(
            'p' => $post_id
        ));

        // add pmpro filter which overrides access and always returns true
        // this way it won't filter the content and always returns full content
        add_filter( 'pmpro_has_membership_access_filter', array($this, 'richie_pmpro_has_membership_access_filter'), 20, 4 );

        ob_start();
        $richie_news_template_loader
            ->get_template_part($name);

        $rendered_content = ob_get_clean();
        wp_reset_query();
        wp_reset_postdata();

        return $rendered_content;
    }


    public function generate_article($my_post) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;

        $post = $my_post;

        $article = new stdClass();

        // get metadata
        $post_id = $post->ID;
        $user_data = get_userdata($post->post_author);
        $category = get_the_category($post_id);

        $thumbnail_id = get_post_thumbnail_id($post_id);

        if ( $thumbnail_id ) {
            $article->image_url = wp_get_attachment_url($thumbnail_id);
        }

        $article->id = $post->guid;
        $article->title = $post->post_title;
        $article->summary = $post->post_excerpt;
        if ($category) {
            $article->kicker = $category[0]->name;
        }
        $article->date = (new DateTime($post->post_date_gmt))->format('c');
        $article->updated_date = (new DateTime($post->post_modified_gmt))->format('c');
        $article->share_link_url = get_permalink($post_id);


        $metered_id = $this->news_options['metered_pmpro_level'];
        $member_only_id = $this->news_options['member_only_pmpro_level'];

        // get paywall type
        $sqlQuery = "SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $post->ID . "'";
        $post_membership_levels = $wpdb->get_results($sqlQuery);
        $levels = array_column($post_membership_levels, 'id');

        $is_premium = in_array($member_only_id, $levels);
        $is_metered = in_array($metered_id, $levels);

        if ( $is_premium ) {
            $article->metered_paywall = self::METERED_PAYWALL_NO_ACCESS_VALUE;
        } else if ( $is_metered  ) {
            $article->metered_paywall = self::METERED_PAYWALL_METERED_VALUE;
        } else {
            $article->metered_paywall = self::METERED_PAYWALL_FREE_VALUE;
        }


        $article->content_html_document = $this->render_template('richie-news-article', $post_id);
        return $article;
    }
}