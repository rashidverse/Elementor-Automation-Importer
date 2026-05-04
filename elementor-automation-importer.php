<?php
/**
 * Plugin Name: Elementor Automation Importer
 * Description: Imports custom Elementor automation JSON files with embedded/base64 assets by uploading images to the Media Library and creating Elementor Library templates.
 * Version: 1.0.0
 * Author: RashidVerse
 * Author URI: https://rashidverse.github.io/portfolio-websites/
 * License: GPL-2.0+
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: elementor-automation-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EAI_VERSION', '1.0.0' );
define( 'EAI_PLUGIN_FILE', __FILE__ );
define( 'EAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once EAI_PLUGIN_DIR . 'includes/class-eai-asset-processor.php';
require_once EAI_PLUGIN_DIR . 'includes/class-eai-template-creator.php';
require_once EAI_PLUGIN_DIR . 'includes/class-eai-importer.php';
require_once EAI_PLUGIN_DIR . 'includes/class-eai-admin.php';

final class Elementor_Automation_Importer {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    public function init() {
        if ( is_admin() ) {
            new EAI_Admin();
        }
    }
}

Elementor_Automation_Importer::instance();
