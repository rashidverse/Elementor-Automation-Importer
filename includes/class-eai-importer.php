<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EAI_Importer {
    public function process( $raw_json, $options = array() ) {
        $decoded = json_decode( $raw_json, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid JSON file.', 'elementor-automation-importer' ),
                'errors'  => array( json_last_error_msg() ),
            );
        }

        $normalized = $this->normalize_json( $decoded );
        if ( is_wp_error( $normalized ) ) {
            return array(
                'success' => false,
                'message' => $normalized->get_error_message(),
            );
        }

        if ( ! empty( $options['title_override'] ) ) {
            $normalized['title'] = sanitize_text_field( $options['title_override'] );
        }

        $asset_processor = new EAI_Asset_Processor();
        $asset_result = $asset_processor->process_assets( $normalized['assets'], array(
            'allow_svg' => ! empty( $options['allow_svg'] ),
        ) );

        if ( is_wp_error( $asset_result ) ) {
            return array(
                'success' => false,
                'message' => $asset_result->get_error_message(),
                'errors'  => $asset_result->get_error_data( 'errors' ),
            );
        }

        $content = $asset_processor->replace_placeholders_recursive( $normalized['content'], $asset_result['map'] );
        $page_settings = $asset_processor->replace_placeholders_recursive( $normalized['page_settings'], $asset_result['map'] );

        $clean_template = array(
            'title'         => $normalized['title'],
            'type'          => $normalized['type'],
            'version'       => $normalized['version'],
            'page_settings' => empty( $page_settings ) ? array() : $page_settings,
            'content'       => empty( $content ) ? array() : $content,
        );

        $processed_json_url = $this->save_processed_json( $clean_template, $normalized['title'] );

        $template_id = 0;
        $edit_url = '';
        if ( ! empty( $options['create_template'] ) ) {
            $creator = new EAI_Template_Creator();
            $created = $creator->create_template( $clean_template );
            if ( is_wp_error( $created ) ) {
                return array(
                    'success' => false,
                    'message' => $created->get_error_message(),
                    'errors'  => array( __( 'Processed JSON was created, but Elementor template creation failed.', 'elementor-automation-importer' ) ),
                    'processed_json_url' => $processed_json_url,
                );
            }
            $template_id = $created['template_id'];
            $edit_url = $created['edit_url'];
        }

        return array(
            'success'            => true,
            'message'            => __( 'Automation JSON processed successfully.', 'elementor-automation-importer' ),
            'template_title'     => $clean_template['title'],
            'template_id'        => $template_id,
            'edit_url'           => $edit_url,
            'uploaded_assets'    => count( $asset_result['map'] ),
            'processed_json_url' => $processed_json_url,
        );
    }

    private function normalize_json( $decoded ) {
        // Official Elementor export-like object.
        $title = isset( $decoded['title'] ) ? sanitize_text_field( $decoded['title'] ) : '';
        $type = isset( $decoded['type'] ) ? sanitize_key( $decoded['type'] ) : '';
        $version = isset( $decoded['version'] ) ? sanitize_text_field( $decoded['version'] ) : '0.4';
        $page_settings = isset( $decoded['page_settings'] ) ? $decoded['page_settings'] : array();
        $assets = isset( $decoded['assets'] ) && is_array( $decoded['assets'] ) ? $decoded['assets'] : array();

        // Accept both "content" and older automation key "elementor_data".
        if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
            $content = $decoded['content'];
        } elseif ( isset( $decoded['elementor_data'] ) && is_array( $decoded['elementor_data'] ) ) {
            $content = $decoded['elementor_data'];
        } elseif ( $this->looks_like_raw_elementor_content( $decoded ) ) {
            $content = $decoded;
            $title = $title ?: __( 'Automation Template', 'elementor-automation-importer' );
            $type = $type ?: 'section';
        } else {
            return new WP_Error( 'eai_missing_content', __( 'JSON must contain a content array or elementor_data array.', 'elementor-automation-importer' ) );
        }

        if ( '' === $title ) {
            $title = __( 'Automation Template', 'elementor-automation-importer' );
        }

        $allowed_types = array( 'section', 'container', 'page', 'post', 'header', 'footer', 'single', 'archive', 'popup', 'error-404', 'wp-page' );
        if ( '' === $type ) {
            $type = 'section';
        }
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'section';
        }

        if ( empty( $version ) ) {
            $version = '0.4';
        }

        return array(
            'title'         => $title,
            'type'          => $type,
            'version'       => $version,
            'page_settings' => $page_settings,
            'assets'        => $assets,
            'content'       => $content,
        );
    }

    private function looks_like_raw_elementor_content( $decoded ) {
        if ( ! is_array( $decoded ) ) {
            return false;
        }
        if ( isset( $decoded[0] ) && is_array( $decoded[0] ) && isset( $decoded[0]['elType'] ) ) {
            return true;
        }
        return false;
    }

    private function save_processed_json( $template, $title ) {
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return '';
        }

        $dir = trailingslashit( $upload_dir['basedir'] ) . 'elementor-automation-importer';
        $url = trailingslashit( $upload_dir['baseurl'] ) . 'elementor-automation-importer';

        if ( ! wp_mkdir_p( $dir ) ) {
            return '';
        }

        $filename = sanitize_title( $title );
        if ( '' === $filename ) {
            $filename = 'automation-template';
        }
        $filename .= '-' . gmdate( 'Ymd-His' ) . '.json';

        $path = trailingslashit( $dir ) . $filename;
        $json = wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        if ( false === $json ) {
            return '';
        }

        file_put_contents( $path, $json );
        return trailingslashit( $url ) . $filename;
    }
}
