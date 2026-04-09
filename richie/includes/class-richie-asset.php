<?php
/**
 * Base asset class for Richie feed items.
 *
 * Represents any downloadable asset (font, CSS, JS, image) with a remote URL
 * and a local filesystem-safe name for offline storage.
 *
 * @package Richie
 */

/**
 * Class Richie_Asset
 */
class Richie_Asset implements JsonSerializable {
    /**
     * Local relative path for the app to store the file.
     *
     * @var string
     */
    public $local_name;

    /**
     * Remote URL to fetch the asset from.
     *
     * @var string
     */
    public $remote_url;

    /**
     * Constructor.
     *
     * @param string $url Asset URL (absolute, root-relative, or protocol-relative).
     */
    public function __construct( $url ) {
        $local_name = richie_make_local_name( $url );

        // Transliterate non-ASCII characters so the local_name is filesystem-safe
        // on all devices. The app saves assets using local_name as the filename.
        $this->local_name = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $local_name );
        $this->remote_url = richie_make_link_absolute( $url );
    }

    /**
     * Serialize to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): mixed {
        return array(
            'local_name' => $this->local_name,
            'remote_url' => $this->remote_url,
        );
    }

    /**
     * String representation.
     *
     * @return string JSON.
     */
    public function __toString() {
        return wp_json_encode( $this->jsonSerialize() );
    }
}
