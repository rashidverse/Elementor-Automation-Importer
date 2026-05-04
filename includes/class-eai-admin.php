<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EAI_Admin {
    private $last_result = null;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Elementor Automation Importer', 'elementor-automation-importer' ),
            __( 'Elementor Automation', 'elementor-automation-importer' ),
            'manage_options',
            'eai-importer',
            array( $this, 'render_page' ),
            'dashicons-upload',
            58
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_eai-importer' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'eai-admin', EAI_PLUGIN_URL . 'assets/admin.css', array(), EAI_VERSION );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-automation-importer' ) );
        }

        $result = null;
        if ( isset( $_POST['eai_import_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eai_import_nonce'] ) ), 'eai_import_template' ) ) {
            $result = $this->handle_import_request();
        }

        ?>
        <div class="wrap eai-wrap">
            <h1><?php esc_html_e( 'Elementor Automation Importer', 'elementor-automation-importer' ); ?></h1>
            <p class="eai-lead">
                <?php esc_html_e( 'Upload a custom automation JSON file. The plugin will upload embedded image assets to the Media Library, replace placeholders with image IDs/URLs, and create an Elementor Library template.', 'elementor-automation-importer' ); ?>
            </p>

            <?php $this->render_environment_notice(); ?>
            <?php $this->render_result( $result ); ?>

            <div class="eai-card">
                <h2><?php esc_html_e( 'Import Template', 'elementor-automation-importer' ); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'eai_import_template', 'eai_import_nonce' ); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                        <tr>
                            <th scope="row"><label for="eai_json_file"><?php esc_html_e( 'Automation JSON', 'elementor-automation-importer' ); ?></label></th>
                            <td>
                                <input type="file" id="eai_json_file" name="eai_json_file" accept="application/json,.json" required />
                                <p class="description"><?php esc_html_e( 'Upload JSON created for Elementor Automation Importer.', 'elementor-automation-importer' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="eai_title_override"><?php esc_html_e( 'Template title override', 'elementor-automation-importer' ); ?></label></th>
                            <td>
                                <input type="text" id="eai_title_override" name="eai_title_override" class="regular-text" placeholder="Optional" />
                                <p class="description"><?php esc_html_e( 'Leave blank to use the title inside the JSON file.', 'elementor-automation-importer' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Actions', 'elementor-automation-importer' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="eai_create_template" value="1" checked />
                                    <?php esc_html_e( 'Create Elementor Library template', 'elementor-automation-importer' ); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="checkbox" name="eai_allow_svg" value="1" />
                                    <?php esc_html_e( 'Allow SVG assets for this import', 'elementor-automation-importer' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'SVG is disabled by default because SVG uploads can be risky unless the file is trusted.', 'elementor-automation-importer' ); ?></p>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php submit_button( __( 'Process & Import', 'elementor-automation-importer' ) ); ?>
                </form>
            </div>

            <div class="eai-card eai-muted-card">
                <h2><?php esc_html_e( 'Expected JSON Format', 'elementor-automation-importer' ); ?></h2>
                <pre>{
  "title": "Template Title",
  "type": "section",
  "version": "0.4",
  "page_settings": [],
  "assets": [
    {
      "key": "main_image",
      "filename": "main-image.webp",
      "mime": "image/webp",
      "data": "BASE64_IMAGE_DATA"
    }
  ],
  "content": [
    {
      "elType": "container",
      "settings": {},
      "elements": [
        {
          "elType": "widget",
          "widgetType": "image",
          "settings": {
            "image": {
              "id": "{{asset:main_image:id}}",
              "url": "{{asset:main_image:url}}"
            }
          }
        }
      ]
    }
  ]
}</pre>
            </div>
        </div>
        <?php
    }

    private function handle_import_request() {
        if ( empty( $_FILES['eai_json_file'] ) || empty( $_FILES['eai_json_file']['tmp_name'] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Please upload a JSON file.', 'elementor-automation-importer' ),
            );
        }

        $file = $_FILES['eai_json_file'];

        if ( ! empty( $file['error'] ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Upload failed with error code: %s', 'elementor-automation-importer' ), esc_html( $file['error'] ) ),
            );
        }

        $filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        if ( 'json' !== $ext ) {
            return array(
                'success' => false,
                'message' => __( 'Only .json files are allowed.', 'elementor-automation-importer' ),
            );
        }

        $raw_json = file_get_contents( $file['tmp_name'] );
        if ( false === $raw_json || '' === trim( $raw_json ) ) {
            return array(
                'success' => false,
                'message' => __( 'The uploaded JSON file is empty or unreadable.', 'elementor-automation-importer' ),
            );
        }

        $title_override = isset( $_POST['eai_title_override'] ) ? sanitize_text_field( wp_unslash( $_POST['eai_title_override'] ) ) : '';
        $create_template = ! empty( $_POST['eai_create_template'] );
        $allow_svg = ! empty( $_POST['eai_allow_svg'] );

        $importer = new EAI_Importer();
        return $importer->process( $raw_json, array(
            'title_override'  => $title_override,
            'create_template' => $create_template,
            'allow_svg'       => $allow_svg,
        ) );
    }

    private function render_environment_notice() {
        if ( ! class_exists( '\\Elementor\\Plugin' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Elementor does not appear to be active. You can still process/download JSON, but template creation needs Elementor active.', 'elementor-automation-importer' ) . '</p></div>';
        }
    }

    private function render_result( $result ) {
        if ( empty( $result ) ) {
            return;
        }

        if ( empty( $result['success'] ) ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( $result['message'] ?? __( 'Import failed.', 'elementor-automation-importer' ) ) . '</p></div>';
            if ( ! empty( $result['errors'] ) && is_array( $result['errors'] ) ) {
                echo '<div class="eai-card eai-error-list"><ul>';
                foreach ( $result['errors'] as $error ) {
                    echo '<li>' . esc_html( $error ) . '</li>';
                }
                echo '</ul></div>';
            }
            return;
        }

        echo '<div class="notice notice-success inline"><p>' . esc_html( $result['message'] ?? __( 'Import completed.', 'elementor-automation-importer' ) ) . '</p></div>';
        echo '<div class="eai-card eai-result-card">';
        echo '<h2>' . esc_html__( 'Import Result', 'elementor-automation-importer' ) . '</h2>';
        echo '<ul>';
        if ( ! empty( $result['template_title'] ) ) {
            echo '<li><strong>' . esc_html__( 'Template:', 'elementor-automation-importer' ) . '</strong> ' . esc_html( $result['template_title'] ) . '</li>';
        }
        if ( ! empty( $result['template_id'] ) ) {
            echo '<li><strong>' . esc_html__( 'Elementor Template ID:', 'elementor-automation-importer' ) . '</strong> ' . absint( $result['template_id'] ) . '</li>';
        }
        if ( isset( $result['uploaded_assets'] ) ) {
            echo '<li><strong>' . esc_html__( 'Uploaded assets:', 'elementor-automation-importer' ) . '</strong> ' . absint( $result['uploaded_assets'] ) . '</li>';
        }
        echo '</ul>';

        echo '<p class="eai-actions">';
        if ( ! empty( $result['edit_url'] ) ) {
            echo '<a class="button button-primary" href="' . esc_url( $result['edit_url'] ) . '">' . esc_html__( 'Edit with Elementor', 'elementor-automation-importer' ) . '</a> ';
        }
        if ( ! empty( $result['processed_json_url'] ) ) {
            echo '<a class="button" href="' . esc_url( $result['processed_json_url'] ) . '" download>' . esc_html__( 'Download Processed JSON', 'elementor-automation-importer' ) . '</a>';
        }
        echo '</p>';
        echo '</div>';
    }
}
