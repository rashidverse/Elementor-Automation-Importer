<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EAI_Asset_Processor {
    private $allowed_mimes = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    );

    public function process_assets( $assets, $options = array() ) {
        $allow_svg = ! empty( $options['allow_svg'] );
        if ( $allow_svg ) {
            $this->allowed_mimes['image/svg+xml'] = 'svg';
        }

        if ( empty( $assets ) ) {
            return array( 'map' => array() );
        }

        if ( ! is_array( $assets ) ) {
            return new WP_Error( 'eai_bad_assets', __( 'The assets value must be an array.', 'elementor-automation-importer' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $map = array();
        $errors = array();

        foreach ( $assets as $index => $asset ) {
            $processed = $this->process_single_asset( $asset, $index );
            if ( is_wp_error( $processed ) ) {
                $errors[] = $processed->get_error_message();
                continue;
            }
            $map[ $processed['key'] ] = $processed;
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'eai_asset_errors', __( 'One or more assets could not be processed.', 'elementor-automation-importer' ), array( 'errors' => $errors ) );
        }

        return array( 'map' => $map );
    }

    private function process_single_asset( $asset, $index ) {
        if ( ! is_array( $asset ) ) {
            return new WP_Error( 'eai_asset_not_object', sprintf( __( 'Asset %d is not a valid object.', 'elementor-automation-importer' ), $index + 1 ) );
        }

        $key = isset( $asset['key'] ) ? sanitize_key( $asset['key'] ) : '';
        if ( '' === $key ) {
            return new WP_Error( 'eai_asset_no_key', sprintf( __( 'Asset %d is missing a key.', 'elementor-automation-importer' ), $index + 1 ) );
        }

        $filename = isset( $asset['filename'] ) ? sanitize_file_name( $asset['filename'] ) : $key . '.png';
        $mime = isset( $asset['mime'] ) ? sanitize_mime_type( $asset['mime'] ) : '';
        $data = isset( $asset['data'] ) ? $asset['data'] : '';

        if ( '' === $data || ! is_string( $data ) ) {
            return new WP_Error( 'eai_asset_no_data', sprintf( __( 'Asset "%s" is missing base64 data.', 'elementor-automation-importer' ), $key ) );
        }

        $base64 = $this->strip_data_uri_prefix( $data, $mime );
        $binary = base64_decode( $base64, true );
        if ( false === $binary ) {
            return new WP_Error( 'eai_asset_decode_failed', sprintf( __( 'Asset "%s" has invalid base64 data.', 'elementor-automation-importer' ), $key ) );
        }

        if ( '' === $mime ) {
            $mime = $this->detect_mime_from_binary( $binary );
        }

        if ( ! isset( $this->allowed_mimes[ $mime ] ) ) {
            return new WP_Error( 'eai_asset_mime_blocked', sprintf( __( 'Asset "%1$s" has unsupported or blocked mime type: %2$s', 'elementor-automation-importer' ), $key, $mime ) );
        }

        $expected_ext = $this->allowed_mimes[ $mime ];
        if ( '' === pathinfo( $filename, PATHINFO_EXTENSION ) ) {
            $filename .= '.' . $expected_ext;
        }

        if ( 'svg' === $expected_ext && ! $this->is_svg_reasonably_safe( $binary ) ) {
            return new WP_Error( 'eai_svg_unsafe', sprintf( __( 'Asset "%s" SVG was blocked because it contains potentially unsafe content.', 'elementor-automation-importer' ), $key ) );
        }

        $upload = wp_upload_bits( $filename, null, $binary );
        if ( ! empty( $upload['error'] ) ) {
            return new WP_Error( 'eai_upload_failed', sprintf( __( 'Asset "%1$s" upload failed: %2$s', 'elementor-automation-importer' ), $key, $upload['error'] ) );
        }

        $attachment = array(
            'post_mime_type' => $mime,
            'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
            return new WP_Error( 'eai_attachment_failed', sprintf( __( 'Asset "%s" could not be added to the Media Library.', 'elementor-automation-importer' ), $key ) );
        }

        if ( 'image/svg+xml' !== $mime ) {
            $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
            if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
                wp_update_attachment_metadata( $attachment_id, $metadata );
            }
        }

        return array(
            'key'      => $key,
            'id'       => absint( $attachment_id ),
            'url'      => wp_get_attachment_url( $attachment_id ),
            'filename' => $filename,
            'mime'     => $mime,
        );
    }

    private function strip_data_uri_prefix( $data, &$mime ) {
        $data = trim( $data );
        if ( preg_match( '/^data:([^;]+);base64,(.*)$/s', $data, $matches ) ) {
            if ( empty( $mime ) ) {
                $mime = sanitize_mime_type( $matches[1] );
            }
            return preg_replace( '/\s+/', '', $matches[2] );
        }
        return preg_replace( '/\s+/', '', $data );
    }

    private function detect_mime_from_binary( $binary ) {
        if ( class_exists( 'finfo' ) ) {
            $finfo = new finfo( FILEINFO_MIME_TYPE );
            $mime = $finfo->buffer( $binary );
            if ( is_string( $mime ) && '' !== $mime ) {
                return $mime;
            }
        }
        if ( 0 === strpos( ltrim( $binary ), '<svg' ) || false !== strpos( substr( $binary, 0, 300 ), '<svg' ) ) {
            return 'image/svg+xml';
        }
        return '';
    }

    private function is_svg_reasonably_safe( $binary ) {
        $svg = strtolower( $binary );
        $blocked = array( '<script', 'javascript:', 'onload=', 'onerror=', 'onclick=', '<iframe', '<foreignobject', '<object', '<embed' );
        foreach ( $blocked as $needle ) {
            if ( false !== strpos( $svg, $needle ) ) {
                return false;
            }
        }
        return true;
    }

    public function replace_placeholders_recursive( $value, $asset_map ) {
        if ( is_array( $value ) ) {
            $new = array();
            foreach ( $value as $k => $v ) {
                $new[ $k ] = $this->replace_placeholders_recursive( $v, $asset_map );
            }
            return $new;
        }

        if ( ! is_string( $value ) || empty( $asset_map ) ) {
            return $value;
        }

        if ( preg_match( '/^\{\{asset:([a-zA-Z0-9_-]+):(id|url|filename|mime)\}\}$/', $value, $matches ) ) {
            $key = sanitize_key( $matches[1] );
            $field = $matches[2];
            if ( isset( $asset_map[ $key ][ $field ] ) ) {
                return ( 'id' === $field ) ? absint( $asset_map[ $key ][ $field ] ) : $asset_map[ $key ][ $field ];
            }
        }

        foreach ( $asset_map as $key => $asset ) {
            $value = str_replace( '{{asset:' . $key . ':id}}', (string) absint( $asset['id'] ), $value );
            $value = str_replace( '{{asset:' . $key . ':url}}', (string) $asset['url'], $value );
            $value = str_replace( '{{asset:' . $key . ':filename}}', (string) $asset['filename'], $value );
            $value = str_replace( '{{asset:' . $key . ':mime}}', (string) $asset['mime'], $value );
        }

        return $value;
    }
}
