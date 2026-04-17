<?php
/**
 * User Dashboard for WooPoints
 *
 * Adds a points counter and transaction history
 * to the WooCommerce My Account page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_User_Dashboard {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add "My Points" tab to WooCommerce My Account
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
        add_action( 'woocommerce_account_my-points_endpoint', array( $this, 'render_my_points_page' ) );

        // Add points summary widget on the main My Account dashboard
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_dashboard_widget' ) );

        // Enqueue frontend styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the endpoint
     */
    public function add_endpoint() {
        add_rewrite_endpoint( 'my-points', EP_ROOT | EP_PAGES );
    }

    /**
     * Add query var
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'my-points';
        return $vars;
    }

    /**
     * Add menu item to My Account nav
     */
    public function add_menu_item( $items ) {
        // Insert before "Logout"
        $logout = false;
        if ( isset( $items['customer-logout'] ) ) {
            $logout = $items['customer-logout'];
            unset( $items['customer-logout'] );
        }

        $items['my-points'] = __( '⭐ My Points', 'woo-points-rewards' );

        if ( $logout ) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    /**
     * Enqueue frontend CSS
     */
    public function enqueue_assets() {
        if ( is_account_page() ) {
            wp_enqueue_style(
                'wpr-frontend-css',
                WPR_PLUGIN_URL . 'assets/css/frontend-dashboard.css',
                array(),
                WPR_VERSION
            );
        }
    }

    /**
     * Render the points summary widget on the My Account dashboard
     */
    public function render_dashboard_widget() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $balance      = WPR_Points_Manager::get_balance( $user_id );
        $total_earned = WPR_Points_Manager::get_total_earned( $user_id );

        ?>
        <div class="wpr-account-widget">
            <div class="wpr-widget-header">
                <span class="wpr-widget-icon">⭐</span>
                <h3><?php esc_html_e( 'Your Loyalty Points', 'woo-points-rewards' ); ?></h3>
            </div>
            <div class="wpr-widget-body">
                <div class="wpr-widget-stat">
                    <span class="wpr-widget-stat-number"><?php echo esc_html( number_format( $balance ) ); ?></span>
                    <span class="wpr-widget-stat-label"><?php esc_html_e( 'Current Balance', 'woo-points-rewards' ); ?></span>
                </div>
                <div class="wpr-widget-stat">
                    <span class="wpr-widget-stat-number"><?php echo esc_html( number_format( $total_earned ) ); ?></span>
                    <span class="wpr-widget-stat-label"><?php esc_html_e( 'Total Earned', 'woo-points-rewards' ); ?></span>
                </div>
            </div>
            <div class="wpr-widget-footer">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'my-points' ) ); ?>" class="wpr-widget-link">
                    <?php esc_html_e( 'View Points History →', 'woo-points-rewards' ); ?>
                </a>
            </div>
            <div class="wpr-widget-info">
                <p><?php esc_html_e( 'Earn points with every purchase! The more points you have, the bigger your chance to win the Grand Prize raffle. 🎡', 'woo-points-rewards' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the full My Points page
     */
    public function render_my_points_page() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $balance      = WPR_Points_Manager::get_balance( $user_id );
        $total_earned = WPR_Points_Manager::get_total_earned( $user_id );
        $log          = WPR_Points_Manager::get_user_log( $user_id, 30 );

        ?>
        <div class="wpr-my-points-page">
            <!-- Points Overview Cards -->
            <div class="wpr-points-overview">
                <div class="wpr-points-card wpr-points-card-balance">
                    <div class="wpr-points-card-icon">🏆</div>
                    <div class="wpr-points-card-value"><?php echo esc_html( number_format( $balance ) ); ?></div>
                    <div class="wpr-points-card-label"><?php esc_html_e( 'Current Points', 'woo-points-rewards' ); ?></div>
                    <div class="wpr-points-card-glow"></div>
                </div>
                <div class="wpr-points-card wpr-points-card-earned">
                    <div class="wpr-points-card-icon">💎</div>
                    <div class="wpr-points-card-value"><?php echo esc_html( number_format( $total_earned ) ); ?></div>
                    <div class="wpr-points-card-label"><?php esc_html_e( 'Lifetime Earned', 'woo-points-rewards' ); ?></div>
                </div>
                <div class="wpr-points-card wpr-points-card-info">
                    <div class="wpr-points-card-icon">🎡</div>
                    <div class="wpr-points-card-value-text"><?php esc_html_e( 'Grand Raffle', 'woo-points-rewards' ); ?></div>
                    <div class="wpr-points-card-label"><?php esc_html_e( 'More points = bigger slice of the raffle wheel = higher chance to win!', 'woo-points-rewards' ); ?></div>
                </div>
            </div>

            <!-- How it Works -->
            <div class="wpr-how-it-works">
                <h3><?php esc_html_e( 'How Points Work', 'woo-points-rewards' ); ?></h3>
                <div class="wpr-steps">
                    <div class="wpr-step">
                        <div class="wpr-step-number">1</div>
                        <div class="wpr-step-text">
                            <strong><?php esc_html_e( 'Shop', 'woo-points-rewards' ); ?></strong>
                            <p><?php esc_html_e( 'Make a purchase on our store', 'woo-points-rewards' ); ?></p>
                        </div>
                    </div>
                    <div class="wpr-step">
                        <div class="wpr-step-number">2</div>
                        <div class="wpr-step-text">
                            <strong><?php esc_html_e( 'Earn', 'woo-points-rewards' ); ?></strong>
                            <p><?php esc_html_e( 'Points are added automatically to your account', 'woo-points-rewards' ); ?></p>
                        </div>
                    </div>
                    <div class="wpr-step">
                        <div class="wpr-step-number">3</div>
                        <div class="wpr-step-text">
                            <strong><?php esc_html_e( 'Win', 'woo-points-rewards' ); ?></strong>
                            <p><?php esc_html_e( 'More points = bigger chance in the Grand Prize raffle!', 'woo-points-rewards' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transactions History -->
            <div class="wpr-history-section">
                <h3><?php esc_html_e( 'Points History', 'woo-points-rewards' ); ?></h3>

                <?php if ( empty( $log ) ) : ?>
                    <div class="wpr-empty-history">
                        <span class="wpr-empty-icon">📋</span>
                        <p><?php esc_html_e( 'No points transactions yet. Start shopping to earn points!', 'woo-points-rewards' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="wpr-history-list">
                        <?php foreach ( $log as $entry ) : ?>
                            <div class="wpr-history-item <?php echo floatval( $entry->points ) >= 0 ? 'wpr-earned' : 'wpr-spent'; ?>">
                                <div class="wpr-history-icon">
                                    <?php echo floatval( $entry->points ) >= 0 ? '➕' : '➖'; ?>
                                </div>
                                <div class="wpr-history-details">
                                    <div class="wpr-history-desc"><?php echo wp_kses( $entry->description, array( 'span' => array( 'class' => array() ), 'strong' => array(), 'b' => array(), 'bdi' => array() ) ); ?></div>
                                    <div class="wpr-history-meta">
                                        <span class="wpr-history-type"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry->type ) ) ); ?></span>
                                        <span class="wpr-history-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></span>
                                    </div>
                                </div>
                                <div class="wpr-history-points">
                                    <?php echo floatval( $entry->points ) >= 0 ? '+' : ''; ?><?php echo esc_html( number_format( $entry->points ) ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
