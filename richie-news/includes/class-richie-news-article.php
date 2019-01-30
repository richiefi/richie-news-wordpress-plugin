<?php

class Richie_News_Article {

    // values for metered_paywall
    const METERED_PAYWALL_NO_ACCESS_VALUE = 'no_access';
    const METERED_PAYWALL_METERED_VALUE = 'metered';
    const METERED_PAYWALL_FREE_VALUE = 'free';

    public $title;
    public $content_html_document;
    public $date;
    public $metered_paywall;
    public $updated_date;

    function __construct($post, $richie_news_options) {

        global $wpdb;

        $metered_id = $richie_news_options['metered_pmpro_level'];
        $member_only_id = $richie_news_options['member_only_pmpro_level'];

        $content = $post->post_content;
        $content = apply_filters('the_content', $content);
        $content = str_replace(']]>', ']]&gt;', $content);
        $sqlQuery = "SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $post->ID . "'";
        $post_membership_levels = $wpdb->get_results($sqlQuery);
        $levels = array_column($post_membership_levels, 'id');

        $is_premium = in_array($member_only_id, $levels);
        $is_metered = in_array($metered_id, $levels);

        if ( $is_premium ) {
            $this->metered_paywall = self::METERED_PAYWALL_NO_ACCESS_VALUE;
        } else if ( $is_metered  ) {
            $this->metered_paywall = self::METERED_PAYWALL_METERED_VALUE;
        } else {
            $this->metered_paywall = self::METERED_PAYWALL_FREE_VALUE;
        }

        $this->title = $post->post_title;
        $this->content_html_document = $content;
        $this->date = (new DateTime($post->post_date_gmt))->format('c');
        $this->updated_date = (new DateTime($post->post_modified_gmt))->format('c');

    }
}