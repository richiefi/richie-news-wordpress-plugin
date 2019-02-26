<?php

class Richie_Maggio_Issue {
    public $product;
    public $title;
    public $date;
    public $is_free;
    public $uuid;
    public $master_cover;

    function __construct($product, $issue_data)  {
        $this->product = $product;
        $this->title = $issue_data->name;
        $this->date = strtotime($issue_data->publishedAt);
        $this->is_free = $issue_data->isFree;
        $this->uuid = $issue_data->uuid;
        $this->master_cover = $issue_data->covers->master;
    }

    public function get_cover($width, $height) {
        $url = $this->master_cover->url . '?width=' . $width . '&height=' . $height;
        return $url;
    }

    public function get_redirect_url() {
        return '/maggio-redirect/' . $this->uuid;
    }

}