<?php
/**
 * Admin Dashboard for WooPoints
 *
 * Renders the admin menu page with:
 * - Overview stats cards
 * - Users points table with search
 * - Grand Prize raffle spinning wheel
 * - Settings panel
 * - Spin history log
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_Admin_Dashboard {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Reward Point System', 'woo-points-rewards' ),
            __( 'Reward Point System', 'woo-points-rewards' ),
            'manage_woocommerce',
            'woo-points-rewards',
            array( $this, 'render_dashboard' ),
            'dashicons-awards',
            56
        );

        add_submenu_page(
            'woo-points-rewards',
            __( 'Dashboard', 'woo-points-rewards' ),
            __( 'Dashboard', 'woo-points-rewards' ),
            'manage_woocommerce',
            'woo-points-rewards',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'woo-points-rewards',
            __( 'Grand Raffle', 'woo-points-rewards' ),
            __( 'Grand Raffle', 'woo-points-rewards' ),
            'manage_woocommerce',
            'woo-points-raffle',
            array( $this, 'render_raffle_page' )
        );

        add_submenu_page(
            'woo-points-rewards',
            __( 'Settings', 'woo-points-rewards' ),
            __( 'Settings', 'woo-points-rewards' ),
            'manage_woocommerce',
            'woo-points-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin CSS and JS
     */
    public function enqueue_assets( $hook ) {
        $plugin_pages = array(
            'toplevel_page_woo-points-rewards',
            'reward-point-system_page_woo-points-raffle',
            'reward-point-system_page_woo-points-settings',
        );

        if ( ! in_array( $hook, $plugin_pages ) ) {
            return;
        }

        wp_enqueue_style(
            'wpr-admin-css',
            WPR_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            WPR_VERSION
        );

        wp_enqueue_script(
            'wpr-admin-js',
            WPR_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array( 'jquery' ),
            WPR_VERSION,
            true
        );

        // Get users for the raffle wheel
        $users_with_points = WPR_Points_Manager::get_all_users_points( 100, 0, 'points_balance', 'DESC' );
        $wheel_data = array();

        // Color palette for wheel slices
        $colors = array(
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#FF8C69', '#87CEEB', '#98D8C8', '#F7DC6F',
            '#BB8FCE', '#85C1E9', '#82E0AA', '#F8C471', '#F1948A',
            '#AED6F1', '#A3E4D7', '#FAD7A0', '#D2B4DE', '#A9DFBF',
            '#F9E79F', '#FADBD8', '#D5F5E3', '#D6EAF8', '#FCF3CF',
        );

        foreach ( $users_with_points as $index => $user ) {
            if ( floatval( $user->points_balance ) <= 0 ) {
                continue;
            }
            $wheel_data[] = array(
                'user_id'      => intval( $user->user_id ),
                'display_name' => esc_html( $user->display_name ),
                'email'        => esc_html( $user->user_email ),
                'points'       => floatval( $user->points_balance ),
                'color'        => $colors[ $index % count( $colors ) ],
            );
        }

        wp_localize_script( 'wpr-admin-js', 'wprAdmin', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'wpr_admin_nonce' ),
            'wheel_users' => $wheel_data,
            'prize_name'  => get_option( 'wpr_grand_prize_name', 'Grand Prize' ),
            'prize_desc'  => get_option( 'wpr_grand_prize_description', '' ),
            'currency'    => get_woocommerce_currency_symbol(),
            'i18n'        => array(
                'spinning'     => __( 'Spinning...', 'woo-points-rewards' ),
                'winner'       => __( 'Winner!', 'woo-points-rewards' ),
                'no_users'     => __( 'No users with points to spin!', 'woo-points-rewards' ),
                'confirm_spin' => __( 'Are you sure you want to spin the Grand Raffle wheel?', 'woo-points-rewards' ),
                'spin_success' => __( 'Winner recorded successfully!', 'woo-points-rewards' ),
                'spin_error'   => __( 'Something went wrong. Please try again.', 'woo-points-rewards' ),
            ),
        ));
    }

    /**
     * Render the main dashboard page
     */
    public function render_dashboard() {
        // Handle manual points form submission
        if ( isset( $_POST['wpr_manual_add_points'] ) && check_admin_referer( 'wpr_manual_add_nonce' ) ) {
            $manual_user_id = intval( $_POST['wpr_manual_user_id'] );
            $manual_points  = floatval( $_POST['wpr_manual_points_amount'] );

            if ( $manual_user_id && $manual_points > 0 ) {
                $target_user = get_userdata( $manual_user_id );
                if ( $target_user ) {
                    $description = sprintf(
                        __( 'Manual points added by admin (%s points)', 'woo-points-rewards' ),
                        number_format( $manual_points )
                    );
                    WPR_Points_Manager::add_points( $manual_user_id, $manual_points, 'admin_manual', null, $description );
                    $new_balance = WPR_Points_Manager::get_balance( $manual_user_id );
                    echo '<div class="notice notice-success is-dismissible"><p>' .
                        sprintf(
                            esc_html__( '✅ Successfully added %s points to %s. New balance: %s', 'woo-points-rewards' ),
                            '<strong>' . esc_html( number_format( $manual_points ) ) . '</strong>',
                            '<strong>' . esc_html( $target_user->display_name ) . '</strong>',
                            '<strong>' . esc_html( number_format( $new_balance ) ) . '</strong>'
                        ) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                    esc_html__( 'Please select a user and enter a valid points amount.', 'woo-points-rewards' ) .
                    '</p></div>';
            }
        }

        // Handle delete all points
        if ( isset( $_POST['wpr_delete_all_points'] ) && check_admin_referer( 'wpr_delete_all_nonce' ) ) {
            WPR_Points_Manager::delete_all_points();
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__( '🗑️ All customer points have been deleted.', 'woo-points-rewards' ) .
                '</p></div>';
        }

        // Handle delete single user points
        if ( isset( $_POST['wpr_delete_user_points'] ) && check_admin_referer( 'wpr_delete_user_nonce' ) ) {
            $del_user_id = intval( $_POST['wpr_delete_user_id'] );
            $del_user = get_userdata( $del_user_id );
            if ( $del_user_id && WPR_Points_Manager::delete_user_points( $del_user_id ) ) {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    sprintf(
                        esc_html__( '🗑️ Points for %s have been deleted.', 'woo-points-rewards' ),
                        '<strong>' . esc_html( $del_user ? $del_user->display_name : '#' . $del_user_id ) . '</strong>'
                    ) . '</p></div>';
            }
        }


        $total_users   = WPR_Points_Manager::get_total_users_count();
        $total_points  = WPR_Points_Manager::get_total_points_in_system();

        // Pagination
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        // Search
        $search = isset( $_GET['wpr_search'] ) ? sanitize_text_field( $_GET['wpr_search'] ) : '';

        if ( $search ) {
            $search_total = WPR_Points_Manager::get_search_users_count( $search );
            $total_pages  = ceil( $search_total / $per_page );
            $users = WPR_Points_Manager::search_users( $search, $per_page, $offset );
        } else {
            $total_pages = ceil( $total_users / $per_page );
            $users = WPR_Points_Manager::get_all_users_points( $per_page, $offset );
        }

        ?>
        <div class="wpr-admin-wrap">
            <div class="wpr-admin-header">
                <div class="wpr-header-content">
                    <h1>
                        <span class="wpr-logo">⭐</span>
                        <?php esc_html_e( 'Reward Point System', 'woo-points-rewards' ); ?>
                    </h1>
                    <p class="wpr-subtitle"><?php esc_html_e( 'Manage customer loyalty points and rewards', 'woo-points-rewards' ); ?></p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="wpr-stats-grid">
                <div class="wpr-stat-card wpr-stat-users">
                    <div class="wpr-stat-icon">👥</div>
                    <div class="wpr-stat-info">
                        <span class="wpr-stat-number"><?php echo esc_html( number_format( $total_users ) ); ?></span>
                        <span class="wpr-stat-label"><?php esc_html_e( 'Total Members', 'woo-points-rewards' ); ?></span>
                    </div>
                </div>
                <div class="wpr-stat-card wpr-stat-points">
                    <div class="wpr-stat-icon">🏆</div>
                    <div class="wpr-stat-info">
                        <span class="wpr-stat-number"><?php echo esc_html( number_format( $total_points ) ); ?></span>
                        <span class="wpr-stat-label"><?php esc_html_e( 'Points in Circulation', 'woo-points-rewards' ); ?></span>
                    </div>
                </div>
                <div class="wpr-stat-card wpr-stat-rate">
                    <div class="wpr-stat-icon">💰</div>
                    <div class="wpr-stat-info">
                        <span class="wpr-stat-number"><?php echo esc_html( get_option( 'wpr_points_rate', 1 ) ); ?>x</span>
                        <span class="wpr-stat-label"><?php esc_html_e( 'Points Rate', 'woo-points-rewards' ); ?></span>
                    </div>
                </div>
                <div class="wpr-stat-card wpr-stat-raffle">
                    <div class="wpr-stat-icon">🎡</div>
                    <div class="wpr-stat-info">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-points-raffle' ) ); ?>" class="wpr-raffle-link">
                            <?php esc_html_e( 'Open Grand Raffle', 'woo-points-rewards' ); ?> →
                        </a>
                        <span class="wpr-stat-label"><?php esc_html_e( 'Spin the Wheel', 'woo-points-rewards' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Add Points Manually -->
            <?php
            // Get all WordPress users for the dropdown
            $all_wp_users = get_users( array(
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'number'  => 200,
            ));
            ?>
            <div class="wpr-card" style="border-left: 4px solid #4ECDC4;">
                <div class="wpr-card-header">
                    <h2>➕ <?php esc_html_e( 'Add Points Manually', 'woo-points-rewards' ); ?></h2>
                </div>
                <form method="post" style="padding: 24px;">
                    <?php wp_nonce_field( 'wpr_manual_add_nonce' ); ?>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                        <div style="flex: 2; min-width: 220px;">
                            <label for="wpr_manual_user_id" style="display: block; font-size: 13px; color: #888; margin-bottom: 6px;">
                                <?php esc_html_e( 'Select User', 'woo-points-rewards' ); ?>
                            </label>
                            <select name="wpr_manual_user_id" id="wpr_manual_user_id" class="wpr-input" style="height: 42px;" required>
                                <option value=""><?php esc_html_e( '— Choose a user —', 'woo-points-rewards' ); ?></option>
                                <?php foreach ( $all_wp_users as $wp_user ) :
                                    $user_balance = WPR_Points_Manager::get_balance( $wp_user->ID );
                                ?>
                                    <option value="<?php echo esc_attr( $wp_user->ID ); ?>">
                                        <?php echo esc_html( $wp_user->display_name ); ?> (<?php echo esc_html( $wp_user->user_email ); ?>) — <?php echo esc_html( number_format( $user_balance ) ); ?> pts
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 140px;">
                            <label for="wpr_manual_points_amount" style="display: block; font-size: 13px; color: #888; margin-bottom: 6px;">
                                <?php esc_html_e( 'Points to Add', 'woo-points-rewards' ); ?>
                            </label>
                            <input type="number" name="wpr_manual_points_amount" id="wpr_manual_points_amount" class="wpr-input" min="1" step="1" value="100" placeholder="100" style="height: 42px;" required />
                        </div>
                        <div>
                            <button type="submit" name="wpr_manual_add_points" value="1" class="wpr-btn wpr-btn-primary" style="height: 42px; padding: 0 24px;">
                                <?php esc_html_e( 'Add Points', 'woo-points-rewards' ); ?>
                            </button>
                        </div>
                    </div>
                    <p style="font-size: 12px; color: #888; margin: 12px 0 0;">
                        <?php esc_html_e( 'Select any registered user and add points to their account. Useful for testing or manual rewards.', 'woo-points-rewards' ); ?>
                    </p>
                </form>
            </div>

            <!-- Users Table -->
            <div class="wpr-card">
                <div class="wpr-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <h2>📋 <?php esc_html_e( 'Customer Points', 'woo-points-rewards' ); ?></h2>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <form method="post" style="margin: 0; display: inline;" onsubmit="return confirm('⚠️ This will permanently delete ALL customer points. Are you sure?');">
                            <?php wp_nonce_field( 'wpr_delete_all_nonce' ); ?>
                            <button type="submit" name="wpr_delete_all_points" value="1" class="wpr-btn wpr-btn-sm" style="background: rgba(224, 96, 96, 0.15); color: #e06060; border: 1px solid rgba(224, 96, 96, 0.3);">
                                🗑️ <?php esc_html_e( 'Delete All Points', 'woo-points-rewards' ); ?>
                            </button>
                        </form>
                        <form method="get" style="display: flex; gap: 8px; margin: 0;">
                            <input type="hidden" name="page" value="woo-points-rewards" />
                            <input type="text" name="wpr_search" placeholder="<?php esc_attr_e( 'Search by name or email...', 'woo-points-rewards' ); ?>" value="<?php echo esc_attr( $search ); ?>" class="wpr-search-input" />
                            <button type="submit" class="wpr-btn wpr-btn-primary"><?php esc_html_e( 'Search', 'woo-points-rewards' ); ?></button>
                            <?php if ( $search ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=woo-points-rewards' ) ); ?>" class="wpr-btn wpr-btn-secondary"><?php esc_html_e( 'Clear', 'woo-points-rewards' ); ?></a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <?php if ( empty( $users ) ) : ?>
                    <div class="wpr-empty-state">
                        <span class="wpr-empty-icon">📊</span>
                        <p><?php esc_html_e( 'No customers have earned points yet.', 'woo-points-rewards' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wpr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Customer', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Current Points', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Total Earned', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Total Spent', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Last Updated', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'woo-points-rewards' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $users as $user ) : ?>
                                <tr>
                                    <td>
                                        <div class="wpr-user-cell">
                                            <?php echo get_avatar( $user->user_id, 36 ); ?>
                                            <strong><?php echo esc_html( $user->display_name ); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( $user->user_email ); ?></td>
                                    <td><span class="wpr-points-badge"><?php echo esc_html( number_format( $user->points_balance ) ); ?></span></td>
                                    <td><?php echo esc_html( number_format( $user->total_earned ) ); ?></td>
                                    <td><?php echo esc_html( number_format( $user->total_spent ) ); ?></td>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user->last_updated ) ) ); ?></td>
                                    <td style="white-space: nowrap;">
                                        <button class="wpr-btn wpr-btn-sm wpr-btn-edit" data-user-id="<?php echo esc_attr( $user->user_id ); ?>" data-points="<?php echo esc_attr( $user->points_balance ); ?>" data-name="<?php echo esc_attr( $user->display_name ); ?>">
                                            <?php esc_html_e( 'Edit', 'woo-points-rewards' ); ?>
                                        </button>
                                        <form method="post" style="display: inline; margin: 0;" onsubmit="return confirm('Delete all points for <?php echo esc_js( $user->display_name ); ?>?');">
                                            <?php wp_nonce_field( 'wpr_delete_user_nonce' ); ?>
                                            <input type="hidden" name="wpr_delete_user_id" value="<?php echo esc_attr( $user->user_id ); ?>" />
                                            <button type="submit" name="wpr_delete_user_points" value="1" class="wpr-btn wpr-btn-sm" style="background: rgba(224, 96, 96, 0.15); color: #e06060; border: 1px solid rgba(224, 96, 96, 0.3);">
                                                <?php esc_html_e( 'Delete', 'woo-points-rewards' ); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="wpr-pagination">
                            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="wpr-page-link <?php echo $i === $current_page ? 'active' : ''; ?>">
                                    <?php echo esc_html( $i ); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Edit Points Modal -->
            <div id="wpr-edit-modal" class="wpr-modal" style="display:none;">
                <div class="wpr-modal-backdrop"></div>
                <div class="wpr-modal-content">
                    <div class="wpr-modal-header">
                        <h3><?php esc_html_e( 'Edit Points', 'woo-points-rewards' ); ?></h3>
                        <button class="wpr-modal-close">&times;</button>
                    </div>
                    <div class="wpr-modal-body">
                        <p class="wpr-modal-user-name"></p>
                        <label for="wpr-edit-points"><?php esc_html_e( 'Points Balance:', 'woo-points-rewards' ); ?></label>
                        <input type="number" id="wpr-edit-points" min="0" step="1" class="wpr-input" />
                        <input type="hidden" id="wpr-edit-user-id" />
                    </div>
                    <div class="wpr-modal-footer">
                        <button class="wpr-btn wpr-btn-secondary wpr-modal-close-btn"><?php esc_html_e( 'Cancel', 'woo-points-rewards' ); ?></button>
                        <button class="wpr-btn wpr-btn-primary" id="wpr-save-points"><?php esc_html_e( 'Save Changes', 'woo-points-rewards' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Grand Raffle page
     */
    public function render_raffle_page() {
        // Handle clear raffle history
        if ( isset( $_POST['wpr_clear_raffle_history'] ) && check_admin_referer( 'wpr_clear_history_nonce' ) ) {
            global $wpdb;
            $spin_table = WPR_Database::spin_table();
            $wpdb->query( "TRUNCATE TABLE $spin_table" );
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__( '🗑️ Raffle history has been cleared.', 'woo-points-rewards' ) .
                '</p></div>';
        }

        // Get recent spin history
        global $wpdb;
        $spin_table = WPR_Database::spin_table();
        $history = $wpdb->get_results(
            "SELECT s.*, u.display_name, u.user_email
             FROM $spin_table s
             JOIN {$wpdb->users} u ON s.user_id = u.ID
             ORDER BY s.spun_at DESC
             LIMIT 10"
        );

        ?>
        <div class="wpr-admin-wrap">
            <div class="wpr-admin-header wpr-header-raffle">
                <div class="wpr-header-content">
                    <h1>
                        <span class="wpr-logo">🎡</span>
                        <?php esc_html_e( 'Grand Prize Raffle', 'woo-points-rewards' ); ?>
                    </h1>
                    <p class="wpr-subtitle"><?php esc_html_e( 'Each customer\'s slice is proportional to their points — more points = more chance to win!', 'woo-points-rewards' ); ?></p>
                </div>
            </div>

            <!-- Prize Configuration -->
            <div class="wpr-card wpr-prize-config">
                <div class="wpr-card-header">
                    <h2>🏆 <?php esc_html_e( 'Grand Prize Details', 'woo-points-rewards' ); ?></h2>
                </div>
                <div class="wpr-prize-form">
                    <div class="wpr-form-row">
                        <label for="wpr-prize-name"><?php esc_html_e( 'Prize Name', 'woo-points-rewards' ); ?></label>
                        <input type="text" id="wpr-prize-name" class="wpr-input" value="<?php echo esc_attr( get_option( 'wpr_grand_prize_name', 'Grand Prize' ) ); ?>" placeholder="e.g. iPhone 16 Pro" />
                    </div>
                    <button id="wpr-save-prize" class="wpr-btn wpr-btn-primary"><?php esc_html_e( 'Save Prize Details', 'woo-points-rewards' ); ?></button>
                </div>
            </div>

            <!-- Spinning Wheel -->
            <div class="wpr-card wpr-wheel-card">
                <div class="wpr-wheel-container">
                    <div class="wpr-wheel-wrapper">
                        <div class="wpr-wheel-pointer">▼</div>
                        <canvas id="wpr-raffle-wheel" width="500" height="500"></canvas>
                    </div>
                    <div class="wpr-wheel-controls">
                        <button id="wpr-spin-btn" class="wpr-spin-button">
                            <span class="wpr-spin-icon">🎯</span>
                            <span class="wpr-spin-text"><?php esc_html_e( 'SPIN THE WHEEL', 'woo-points-rewards' ); ?></span>
                        </button>
                        <div id="wpr-winner-display" class="wpr-winner-display" style="display:none;">
                            <div class="wpr-winner-badge">🎉</div>
                            <h3 class="wpr-winner-title"><?php esc_html_e( 'Winner!', 'woo-points-rewards' ); ?></h3>
                            <p class="wpr-winner-name"></p>
                            <p class="wpr-winner-email"></p>
                            <p class="wpr-winner-points"></p>

                        </div>
                        <div class="wpr-wheel-legend" id="wpr-wheel-legend"></div>
                    </div>
                </div>
            </div>

            <!-- Spin History -->
            <div class="wpr-card">
                <div class="wpr-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>📜 <?php esc_html_e( 'Raffle History', 'woo-points-rewards' ); ?></h2>
                    <form method="post" style="margin: 0;" onsubmit="return confirm('⚠️ This will permanently clear all raffle history. Are you sure?');">
                        <?php wp_nonce_field( 'wpr_clear_history_nonce' ); ?>
                        <button type="submit" name="wpr_clear_raffle_history" value="1" class="wpr-btn wpr-btn-sm" style="background: rgba(224, 96, 96, 0.15); color: #e06060; border: 1px solid rgba(224, 96, 96, 0.3);">
                            🗑️ <?php esc_html_e( 'Clear All History', 'woo-points-rewards' ); ?>
                        </button>
                    </form>
                </div>
                <?php if ( empty( $history ) ) : ?>
                    <div class="wpr-empty-state">
                        <span class="wpr-empty-icon">🎰</span>
                        <p><?php esc_html_e( 'No raffle spins yet. Spin the wheel to pick a winner!', 'woo-points-rewards' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wpr-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Winner', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'woo-points-rewards' ); ?></th>
                                <th><?php esc_html_e( 'Prize', 'woo-points-rewards' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $history as $spin ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $spin->spun_at ) ) ); ?></td>
                                    <td>
                                        <div class="wpr-user-cell">
                                            <?php echo get_avatar( $spin->user_id, 36 ); ?>
                                            <strong><?php echo esc_html( $spin->display_name ); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html( $spin->user_email ); ?></td>
                                    <td><span class="wpr-points-badge wpr-prize-badge"><?php echo esc_html( $spin->prize_label ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Settings page
     */
    public function render_settings_page() {
        // Handle form save
        if ( isset( $_POST['wpr_save_settings'] ) && check_admin_referer( 'wpr_settings_nonce' ) ) {
            update_option( 'wpr_points_rate', floatval( $_POST['wpr_points_rate'] ) );
            update_option( 'wpr_include_tax', isset( $_POST['wpr_include_tax'] ) ? 'yes' : 'no' );
            update_option( 'wpr_include_shipping', isset( $_POST['wpr_include_shipping'] ) ? 'yes' : 'no' );
            update_option( 'wpr_award_on_processing', isset( $_POST['wpr_award_on_processing'] ) ? 'yes' : 'no' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'woo-points-rewards' ) . '</p></div>';
        }

        $rate              = get_option( 'wpr_points_rate', 1 );
        $include_tax       = get_option( 'wpr_include_tax', 'no' );
        $include_shipping  = get_option( 'wpr_include_shipping', 'no' );
        $award_processing  = get_option( 'wpr_award_on_processing', 'no' );

        ?>
        <div class="wpr-admin-wrap">
            <div class="wpr-admin-header">
                <div class="wpr-header-content">
                    <h1>
                        <span class="wpr-logo">⚙️</span>
                        <?php esc_html_e( 'Reward Point System — Settings', 'woo-points-rewards' ); ?>
                    </h1>
                    <p class="wpr-subtitle"><?php esc_html_e( 'Configure how points are earned and managed', 'woo-points-rewards' ); ?></p>
                </div>
            </div>

            <div class="wpr-card">
                <form method="post">
                    <?php wp_nonce_field( 'wpr_settings_nonce' ); ?>

                    <div class="wpr-settings-grid">
                        <div class="wpr-form-group">
                            <label for="wpr_points_rate"><?php esc_html_e( 'Points Rate', 'woo-points-rewards' ); ?></label>
                            <input type="number" id="wpr_points_rate" name="wpr_points_rate" value="<?php echo esc_attr( $rate ); ?>" min="0.1" step="0.1" class="wpr-input" />
                            <p class="wpr-help-text"><?php esc_html_e( 'Points earned per 1 unit of currency spent. (1 = 1 point per $1)', 'woo-points-rewards' ); ?></p>
                        </div>

                        <div class="wpr-form-group">
                            <label class="wpr-checkbox-label">
                                <input type="checkbox" name="wpr_include_tax" value="yes" <?php checked( $include_tax, 'yes' ); ?> />
                                <?php esc_html_e( 'Include tax in points calculation', 'woo-points-rewards' ); ?>
                            </label>
                        </div>

                        <div class="wpr-form-group">
                            <label class="wpr-checkbox-label">
                                <input type="checkbox" name="wpr_include_shipping" value="yes" <?php checked( $include_shipping, 'yes' ); ?> />
                                <?php esc_html_e( 'Include shipping costs in points calculation', 'woo-points-rewards' ); ?>
                            </label>
                        </div>

                        <div class="wpr-form-group">
                            <label class="wpr-checkbox-label">
                                <input type="checkbox" name="wpr_award_on_processing" value="yes" <?php checked( $award_processing, 'yes' ); ?> />
                                <?php esc_html_e( 'Award points on "Processing" status (useful for digital goods)', 'woo-points-rewards' ); ?>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="wpr_save_settings" class="wpr-btn wpr-btn-primary" style="margin-top: 20px;">
                        <?php esc_html_e( 'Save Settings', 'woo-points-rewards' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}
