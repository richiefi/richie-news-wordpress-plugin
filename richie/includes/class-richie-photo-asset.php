<?php

class Richie_Photo_Asset implements JsonSerializable {
    public $local_name;
    public $remote_url;
    public $caption = null;
    public $scale_to_device_dimensions = false;

    function __construct( $url, $use_attachment = false, $scale_to_device = false ) {

        $local_name = richie_make_local_name( $url );

        $remote_url = richie_make_link_absolute( $url );

        // Fetching asset data by url is very slow. So this is disabled as default.
        // There is an open ticket in wp core, database indexes could resolve this.

        if ( false !== $use_attachment ) {
            // Remove size from the url, expects '-1000x230' format.
            $base_url = preg_replace( '/(.+)(-\d+x\d+)(.+)/', '$1$3', $url );

            if ( true === $use_attachment ) {
                $attachment_id = richie_attachment_url_to_postid($base_url);
            } else {
                $attachment_id = (int) $use_attachment;
            }

            if ( $attachment_id ) {
                // Attachment found, use it.
                $attachment       = get_post( $attachment_id );
                $this->caption    = $attachment->post_excerpt;
            }

        }

        if ( true === $scale_to_device ) {
            $this->scale_to_device_dimensions = true;
        }

        $this->local_name = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $local_name );
        $this->remote_url = $this->append_wpp_shadow( $remote_url );

    }

    private function append_wpp_shadow( $url ) {
        if ( isset( $_GET['wpp_shadow'] ) ) {
            return add_query_arg( 'wpp_shadow', sanitize_text_field( wp_unslash( $_GET['wpp_shadow'] ) ), $url );
        } else {
            return $url;
        }
    }

    private function get_data() {
        $arr = array(
            'local_name' => $this->local_name,
            'remote_url' => $this->remote_url
        );

        if ( $this->caption ) {
            $arr['caption'] = $this->caption;
        }

        if ( $this->scale_to_device_dimensions ) {
            $arr['scale_to_device_dimensions'] = true;
        }

        return $arr;
    }

    public function __toString() {
        return json_encode( $this->get_data() );
    }

    public function jsonSerialize() {
        return $this->get_data();
    }
}