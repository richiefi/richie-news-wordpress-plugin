<?php

class Richie_News_Article {
  public $title;
  public $content_html_document;
  public $date;
  public $metered_paywall;
  public $updated_date;

  function __construct($post) {

    global $wpdb;

    $content = $post->post_content;
    $content = apply_filters('the_content', $content);
    $content = str_replace(']]>', ']]&gt;', $content);
    $sqlQuery = "SELECT m.id, m.name FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->pmpro_membership_levels m ON mp.membership_id = m.id WHERE mp.page_id = '" . $post->ID . "'";
    $post_membership_levels = $wpdb->get_results($sqlQuery);
    $level_names= array_column($post_membership_levels, 'name');
    $is_premium = in_array('Premium', $level_names);
    $is_metered = in_array('Metered', $level_names);
    if ( $is_premium ) {
      $this->metered_paywall = 'no_access';
    } else if ( $is_metered  ) {
      $this->metered_paywall = 'metered';
    } else {
      $this->metered_paywall = 'free';
    }

    $this->title = $post->post_title;
    $this->content_html_document = $content;
    $this->date = (new DateTime($post->post_date_gmt))->format('c');
    $this->updated_date = (new DateTime($post->post_modified_gmt))->format('c');

  }
}