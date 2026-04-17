<?php
/**
 * AJAX Handler for WooPoints
 *
 * Handles all AJAX requests:
 * - Saving user points (admin)
 * - Spinning the grand raffle wheel (admin)
 * - Saving prize details (admin)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_Ajax_Handler {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin AJAX actions
        add_action( 'wp_ajax_wpr_save_user_points', array( $this, 'save_user_points' ) );
        add_action( 'wp_ajax_wpr_spin_raffle', array( $this, 'spin_raffle' ) );
        add_action( 'wp_ajax_wpr_save_prize_details', array( $this, 'save_prize_details' ) );
        add_action( 'wp_ajax_wpr_search_wp_users', array( $this, 'search_wp_users' ) );
        add_action( 'wp_ajax_wpr_manual_add_points', array( $this, 'manual_add_points' ) );
    }

    /**
     * Save user points (admin edit)
     */
    public function save_user_points() {
        check_ajax_referer( 'wpr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-points-rewards' ) ) );
        }

        $user_id = intval( $_POST['user_id'] );
        $points  = floatval( $_POST['points'] );

        if ( ! $user_id || $points < 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid data', 'woo-points-rewards' ) ) );
        }

        WPR_Points_Manager::set_points( $user_id, $points );

        wp_send_json_success( array(
            'message' => __( 'Points updated successfully!', 'woo-points-rewards' ),
            'balance' => $points,
        ));
    }

    /**
     * Spin the grand raffle wheel
     *
     * Receives the winner (determined client-side by the wheel animation)
     * and logs the win + sends an email.
     */
    public function spin_raffle() {
        check_ajax_referer( 'wpr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-points-rewards' ) ) );
        }

        // Server-side winner selection (BUG-001 fix)
        $winner = WPR_Points_Manager::pick_raffle_winner();

        if ( ! $winner ) {
            wp_send_json_error( array( 'message' => __( 'No eligible users found for the raffle.', 'woo-points-rewards' ) ) );
        }

        $winner_user_id = intval( $winner->user_id );
        $prize_name     = get_option( 'wpr_grand_prize_name', 'Grand Prize' );

        // Log the spin in history
        global $wpdb;
        $spin_table = WPR_Database::spin_table();
        $wpdb->insert(
            $spin_table,
            array(
                'user_id'     => $winner_user_id,
                'prize_label' => $prize_name,
                'prize_value' => '',
                'prize_type'  => 'grand_prize',
                'spun_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        wp_send_json_success( array(
            'message'       => sprintf(
                __( '🎉 %s has won the Grand Prize!', 'woo-points-rewards' ),
                $winner->display_name
            ),
            'winner_id'     => $winner_user_id,
            'winner_name'   => $winner->display_name,
            'winner_email'  => $winner->user_email,
            'winner_points' => floatval( $winner->points_balance ),
        ));
    }

    /**
     * Save prize details via AJAX
     */
    public function save_prize_details() {
        check_ajax_referer( 'wpr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-points-rewards' ) ) );
        }

        $prize_name = sanitize_text_field( $_POST['prize_name'] );
        $prize_desc = sanitize_textarea_field( $_POST['prize_desc'] );

        update_option( 'wpr_grand_prize_name', $prize_name );
        update_option( 'wpr_grand_prize_description', $prize_desc );

        wp_send_json_success( array(
            'message' => __( 'Prize details saved!', 'woo-points-rewards' ),
        ));
    }

    /**
     * Search WordPress users by name or email (for manual add)
     */
    public function search_wp_users() {
        check_ajax_referer( 'wpr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-points-rewards' ) ) );
        }

        $search = sanitize_text_field( $_POST['search'] );

        if ( strlen( $search ) < 2 ) {
            wp_send_json_success( array( 'users' => array() ) );
        }

        $user_query = new WP_User_Query( array(
            'search'         => '*' . $search . '*',
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
            'number'         => 10,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
        ));

        $results = array();
        foreach ( $user_query->get_results() as $user ) {
            $balance = WPR_Points_Manager::get_balance( $user->ID );
            $results[] = array(
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'balance'      => $balance,
            );
        }

        wp_send_json_success( array( 'users' => $results ) );
    }

    /**
     * Manually add points to a user
     */
    public function manual_add_points() {
        check_ajax_referer( 'wpr_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'woo-points-rewards' ) ) );
        }

        $user_id = intval( $_POST['user_id'] );
        $points  = floatval( $_POST['points'] );

        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => __( 'Please select a user.', 'woo-points-rewards' ) ) );
        }

        if ( $points <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Points must be greater than 0.', 'woo-points-rewards' ) ) );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( array( 'message' => __( 'User not found.', 'woo-points-rewards' ) ) );
        }

        $description = sprintf(
            __( 'Manual points added by admin (%s points)', 'woo-points-rewards' ),
            number_format( $points )
        );

        WPR_Points_Manager::add_points( $user_id, $points, 'admin_manual', null, $description );

        $new_balance = WPR_Points_Manager::get_balance( $user_id );

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Successfully added %s points to %s. New balance: %s', 'woo-points-rewards' ),
                number_format( $points ),
                $user->display_name,
                number_format( $new_balance )
            ),
        ));
    }
}
