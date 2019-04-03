<?php
class Richie_Custom_Taxonomies {

    public function register_taxonomies() {
        $this->register_taxonomy_article_set();
    }

    private function register_taxonomy_article_set() {
        $labels = [
            'name'              => _x( 'Article sets', 'taxonomy general name', 'richie' ),
            'singular_name'     => _x( 'Article set', 'taxonomy singular name', 'richie' ),
            'search_items'      => __( 'Search Article sets', 'richie' ),
            'all_items'         => __( 'All Article sets', 'richie' ),
            'edit_item'         => __( 'Edit Article set', 'richie' ),
            'update_item'       => __( 'Update Article set', 'richie' ),
            'add_new_item'      => __( 'Add New Article set', 'richie' ),
            'new_item_name'     => __( 'New Article set Name', 'richie' ),
            'menu_name'         => __( 'Article set', 'richie' ),
            'parent_item'       => null,
            'parent_item_colon' => null,
        ];
        $args   = [
            'hierarchical' => false,
            'labels'       => $labels,
            'public'       => false,
            'rewrite'      => false,
            'show_ui'      => true,
        ];
        register_taxonomy( 'richie_article_set', null, $args );
    }

}
