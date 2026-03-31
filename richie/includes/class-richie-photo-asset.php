<?php
/**
 * Photo asset class for Richie article feeds.
 *
 * Extends Richie_Asset with photo-specific properties: caption,
 * scale_to_device_dimensions, and required_by_html.
 *
 * @package Richie
 */

/**
 * Class Richie_Photo_Asset
 */
class Richie_Photo_Asset extends Richie_Asset {
    /**
     * Optional caption / photo credit.
     *
     * @var string|null
     */
    public $caption = null;

    /**
     * Whether Richie backend should scale the image to fit device dimensions.
     *
     * @var bool
     */
    public $scale_to_device_dimensions = false;

    /**
     * Whether the article HTML requires this asset to render correctly.
     * Defaults to true per the Richie feed spec.
     *
     * @var bool
     */
    public $required_by_html = true;

    /**
     * Constructor.
     *
     * @param string   $url            Asset URL.
     * @param bool|int $use_attachment False to skip attachment lookup; true to look up by URL;
     *                                 integer to use directly as attachment post ID.
     * @param bool     $scale_to_device Whether to scale image to device dimensions.
     */
    public function __construct( $url, $use_attachment = false, $scale_to_device = false ) {
        parent::__construct( $url );

        if ( false !== $use_attachment ) {
            // Remove size suffix (e.g. -1000x230) before attachment lookup.
            $base_url = preg_replace( '/(.+)(-\d+x\d+)(.+)/', '$1$3', $url );

            if ( true === $use_attachment ) {
                $attachment_id = richie_attachment_url_to_postid( $base_url );
            } else {
                $attachment_id = (int) $use_attachment;
            }

            if ( $attachment_id ) {
                $attachment    = get_post( $attachment_id );
                $this->caption = $attachment->post_excerpt;
            }
        }

        if ( true === $scale_to_device ) {
            $this->scale_to_device_dimensions = true;
        }
    }

    /**
     * Serialize to JSON.
     *
     * Only includes optional properties when they carry non-default values.
     *
     * @return array
     */
    public function jsonSerialize(): mixed {
        $data = array(
            'local_name' => $this->local_name,
            'remote_url' => $this->remote_url,
        );

        if ( $this->caption ) {
            $data['caption'] = $this->caption;
        }

        if ( $this->scale_to_device_dimensions ) {
            $data['scale_to_device_dimensions'] = true;
        }

        // Only include required_by_html when it deviates from the default (true).
        if ( false === $this->required_by_html ) {
            $data['required_by_html'] = false;
        }

        return $data;
    }
}
