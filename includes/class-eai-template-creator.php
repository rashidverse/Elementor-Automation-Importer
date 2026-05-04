<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EAI_Template_Creator {
    public function create_template( $template ) {
        if ( ! post_type_exists( 'elementor_library' ) ) {
            return new WP_Error( 'eai_no_elementor_library', __( 'Elementor Library post type is not available. Please make sure Elementor is installed and active.', 'elementor-automation-importer' ) );
        }

        $title = isset( $template['title'] ) ? sanitize_text_field( $template['title'] ) : __( 'Automation Template', 'elementor-automation-importer' );
        $type = isset( $template['type'] ) ? sanitize_key( $template['type'] ) : 'section';
        $content = isset( $template['content'] ) && is_array( $template['content'] ) ? $template['content'] : array();
        $page_settings = isset( $template['page_settings'] ) ? $template['page_settings'] : array();

        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'elementor_library',
            'post_content' => '',
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : EAI_VERSION;
        $content_json = wp_json_encode( $content );
        if ( false === $content_json ) {
            return new WP_Error( 'eai_json_encode_failed', __( 'Could not encode Elementor content JSON.', 'elementor-automation-importer' ) );
        }

        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_template_type', $type );
        update_post_meta( $post_id, '_elementor_version', $elementor_version );
        update_post_meta( $post_id, '_elementor_data', wp_slash( $content_json ) );
        update_post_meta( $post_id, '_elementor_page_settings', $page_settings );

        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            try {
                if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                }
            } catch ( Exception $e ) {
                // Cache clearing is helpful but not required for a successful import.
            }
        }

        return array(
            'template_id' => absint( $post_id ),
            'edit_url'    => admin_url( 'post.php?post=' . absint( $post_id ) . '&action=elementor' ),
        );
    }
}
