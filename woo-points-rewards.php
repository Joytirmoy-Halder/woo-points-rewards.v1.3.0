<?php
/**
 * Plugin Name: WooPoints - Rewards & Grand Raffle
 * Plugin URI: https://example.com/woo-points-rewards
 * Description: A WooCommerce loyalty points system. Customers earn points equal to the amount spent. Admin dashboard shows all user points and features a grand prize raffle wheel where user slice sizes are proportional to their points.
 * Version: 1.3.0
 * Author: Joytirmoy Halder Joyti
 * Author URI: https://example.com
 * Text Domain: woo-points-rewards
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WPR_VERSION', '1.3.0');
define('WPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
final class WooPointsRewards
{

    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', array($this, 'check_woocommerce'));
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('WooPoints requires WooCommerce to be installed and activated.', 'woo-points-rewards');
                echo '</p></div>';
            });
            deactivate_plugins(WPR_PLUGIN_BASENAME);
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->includes();

        // HPOS compatibility
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }

    /**
     * Include required files
     */
    private function includes()
    {
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-database.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-points-manager.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-order-handler.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-admin-dashboard.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-user-dashboard.php';
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-ajax-handler.php';

        WPR_Order_Handler::instance();
        WPR_Admin_Dashboard::instance();
        WPR_User_Dashboard::instance();
        WPR_Ajax_Handler::instance();
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        require_once WPR_PLUGIN_DIR . 'includes/class-wpr-database.php';
        WPR_Database::create_tables();

        // Default options
        if (!get_option('wpr_points_rate')) {
            update_option('wpr_points_rate', 1); // 1 currency unit = 1 point
        }
        if (!get_option('wpr_grand_prize_name')) {
            update_option('wpr_grand_prize_name', 'Grand Prize');
        }
        if (!get_option('wpr_grand_prize_description')) {
            update_option('wpr_grand_prize_description', 'Congratulations! You have won the Grand Prize raffle!');
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }
}

WooPointsRewards::instance();
