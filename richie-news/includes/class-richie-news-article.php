<?php

class Richie_News_Article {

    // values for metered_paywall
    const METERED_PAYWALL_NO_ACCESS_VALUE = 'no_access';
    const METERED_PAYWALL_METERED_VALUE = 'metered';
    const METERED_PAYWALL_FREE_VALUE = 'free';

    private $news_options;

    function __construct($richie_news_options) {
        $this->news_options = $richie_news_options;
    }
    private function render_template($name, $post_id) {
        global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID, $wp_styles;
        require_once plugin_dir_path( __FILE__ ) . 'class-richie-news-template-loader.php';
        $richie_news_template_loader = new Richie_News_Template_Loader;
        $wp_query = new WP_Query(array(
            'p' => $post_id
        ));

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
        $this->article = $article;

        // get metadata
        $post_id = $post->ID;
        $user_data = get_userdata($post->post_author);
        $category = get_the_category($post_id);

        $article->id = wp_generate_uuid4();
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

        $content = $post->post_content;
        $content = apply_filters('the_content', $content);
        $content = str_replace(']]>', ']]&gt;', $content);



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