<?php
/**
 * Points Manager for WooPoints
 *
 * Core logic for adding, deducting, and querying points.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_Points_Manager {

    /**
     * Add points to a user
     *
     * @param int    $user_id
     * @param float  $points
     * @param string $type        e.g. 'purchase', 'bonus', 'spin_reward', 'admin_adjust'
     * @param int    $reference_id  Optional order ID or other reference
     * @param string $description
     * @return bool
     */
    public static function add_points( $user_id, $points, $type = 'purchase', $reference_id = null, $description = '' ) {
        global $wpdb;

        $points = abs( floatval( $points ) );

        if ( $points <= 0 || ! $user_id ) {
            return false;
        }

        $table_points = WPR_Database::points_table();
        $table_log    = WPR_Database::log_table();

        // Check if user has a record
        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_points WHERE user_id = %d", $user_id )
        );

        if ( $existing ) {
            $wpdb->update(
                $table_points,
                array(
                    'points_balance' => $existing->points_balance + $points,
                    'total_earned'   => $existing->total_earned + $points,
                    'last_updated'   => current_time( 'mysql' ),
                ),
                array( 'user_id' => $user_id ),
                array( '%f', '%f', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $table_points,
                array(
                    'user_id'        => $user_id,
                    'points_balance' => $points,
                    'total_earned'   => $points,
                    'total_spent'    => 0,
                    'last_updated'   => current_time( 'mysql' ),
                ),
                array( '%d', '%f', '%f', '%f', '%s' )
            );
        }

        // Log the transaction
        $wpdb->insert(
            $table_log,
            array(
                'user_id'      => $user_id,
                'points'       => $points,
                'type'         => $type,
                'reference_id' => $reference_id,
                'description'  => $description,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%d', '%s', '%s' )
        );

        do_action( 'wpr_points_added', $user_id, $points, $type, $reference_id );

        return true;
    }

    /**
     * Deduct points from a user
     *
     * @param int    $user_id
     * @param float  $points
     * @param string $type
     * @param int    $reference_id
     * @param string $description
     * @return bool
     */
    public static function deduct_points( $user_id, $points, $type = 'redemption', $reference_id = null, $description = '' ) {
        global $wpdb;

        $points = abs( floatval( $points ) );
        $table_points = WPR_Database::points_table();
        $table_log    = WPR_Database::log_table();

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_points WHERE user_id = %d", $user_id )
        );

        if ( ! $existing || $existing->points_balance < $points ) {
            return false;
        }

        $wpdb->update(
            $table_points,
            array(
                'points_balance' => $existing->points_balance - $points,
                'total_spent'    => $existing->total_spent + $points,
                'last_updated'   => current_time( 'mysql' ),
            ),
            array( 'user_id' => $user_id ),
            array( '%f', '%f', '%s' ),
            array( '%d' )
        );

        $wpdb->insert(
            $table_log,
            array(
                'user_id'      => $user_id,
                'points'       => -$points,
                'type'         => $type,
                'reference_id' => $reference_id,
                'description'  => $description,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%f', '%s', '%d', '%s', '%s' )
        );

        do_action( 'wpr_points_deducted', $user_id, $points, $type, $reference_id );

        return true;
    }

    /**
     * Get user's current points balance
     *
     * @param int $user_id
     * @return float
     */
    public static function get_balance( $user_id ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $balance = $wpdb->get_var(
            $wpdb->prepare( "SELECT points_balance FROM $table WHERE user_id = %d", $user_id )
        );

        return $balance ? floatval( $balance ) : 0;
    }

    /**
     * Get user's total earned points
     *
     * @param int $user_id
     * @return float
     */
    public static function get_total_earned( $user_id ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $total = $wpdb->get_var(
            $wpdb->prepare( "SELECT total_earned FROM $table WHERE user_id = %d", $user_id )
        );

        return $total ? floatval( $total ) : 0;
    }

    /**
     * Get all users with points (for admin dashboard)
     *
     * @param int    $limit
     * @param int    $offset
     * @param string $orderby
     * @param string $order
     * @return array
     */
    public static function get_all_users_points( $limit = 20, $offset = 0, $orderby = 'points_balance', $order = 'DESC' ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $allowed_orderby = array( 'points_balance', 'total_earned', 'total_spent', 'last_updated', 'user_id' );
        $orderby = in_array( $orderby, $allowed_orderby ) ? $orderby : 'points_balance';
        $order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, u.display_name, u.user_email
                 FROM $table p
                 JOIN {$wpdb->users} u ON p.user_id = u.ID
                 ORDER BY $orderby $order
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        return $results;
    }

    /**
     * Get total number of users with points
     *
     * @return int
     */
    public static function get_total_users_count() {
        global $wpdb;
        $table = WPR_Database::points_table();

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }

    /**
     * Get total points in the system
     *
     * @return float
     */
    public static function get_total_points_in_system() {
        global $wpdb;
        $table = WPR_Database::points_table();

        $total = $wpdb->get_var( "SELECT SUM(points_balance) FROM $table" );

        return $total ? floatval( $total ) : 0;
    }

    /**
     * Get points log for a specific user
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public static function get_user_log( $user_id, $limit = 20 ) {
        global $wpdb;
        $table = WPR_Database::log_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            )
        );
    }

    /**
     * Set user points directly (admin override)
     *
     * @param int   $user_id
     * @param float $points
     * @return bool
     */
    public static function set_points( $user_id, $points ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $points   = floatval( $points );
        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d", $user_id )
        );

        if ( $existing ) {
            $diff = $points - $existing->points_balance;

            $wpdb->update(
                $table,
                array(
                    'points_balance' => $points,
                    'last_updated'   => current_time( 'mysql' ),
                ),
                array( 'user_id' => $user_id ),
                array( '%f', '%s' ),
                array( '%d' )
            );

            // Log the adjustment
            $log_table = WPR_Database::log_table();
            $wpdb->insert(
                $log_table,
                array(
                    'user_id'     => $user_id,
                    'points'      => $diff,
                    'type'        => 'admin_adjust',
                    'description' => sprintf( 'Admin set balance to %s', number_format( $points, 2 ) ),
                    'created_at'  => current_time( 'mysql' ),
                ),
                array( '%d', '%f', '%s', '%s', '%s' )
            );

        } else {
            $wpdb->insert(
                $table,
                array(
                    'user_id'        => $user_id,
                    'points_balance' => $points,
                    'total_earned'   => max( 0, $points ),
                    'total_spent'    => 0,
                    'last_updated'   => current_time( 'mysql' ),
                ),
                array( '%d', '%f', '%f', '%f', '%s' )
            );
        }

        return true;
    }

    /**
     * Search users with points by name or email
     *
     * @param string $search
     * @param int    $limit
     * @return array
     */
    public static function search_users( $search, $limit = 20, $offset = 0 ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $like = '%' . $wpdb->esc_like( $search ) . '%';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, u.display_name, u.user_email
                 FROM $table p
                 JOIN {$wpdb->users} u ON p.user_id = u.ID
                 WHERE u.display_name LIKE %s OR u.user_email LIKE %s
                 ORDER BY p.points_balance DESC
                 LIMIT %d OFFSET %d",
                $like,
                $like,
                $limit,
                $offset
            )
        );
    }

    /**
     * Delete all points data for a specific user
     *
     * @param int $user_id
     * @return bool
     */
    public static function delete_user_points( $user_id ) {
        global $wpdb;

        $table_points = WPR_Database::points_table();
        $table_log    = WPR_Database::log_table();

        $wpdb->delete( $table_log, array( 'user_id' => $user_id ), array( '%d' ) );
        $result = $wpdb->delete( $table_points, array( 'user_id' => $user_id ), array( '%d' ) );

        return $result !== false;
    }

    /**
     * Delete all points data for all users
     *
     * @return bool
     */
    public static function delete_all_points() {
        global $wpdb;

        $table_points = WPR_Database::points_table();
        $table_log    = WPR_Database::log_table();

        $wpdb->query( "TRUNCATE TABLE $table_log" );
        $result = $wpdb->query( "TRUNCATE TABLE $table_points" );

        return $result !== false;
    }

    /**
     * Pick a weighted random raffle winner from ALL users with points.
     * Winner is determined server-side for integrity (BUG-001 fix).
     * Includes ALL eligible users, not just top 100 (BUG-002 fix).
     *
     * @return object|null Winner user data or null if no eligible users
     */
    public static function pick_raffle_winner() {
        global $wpdb;
        $table = WPR_Database::points_table();

        // Get ALL users with points > 0 (no LIMIT)
        $users = $wpdb->get_results(
            "SELECT p.user_id, p.points_balance, u.display_name, u.user_email
             FROM $table p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.points_balance > 0
             ORDER BY p.points_balance DESC"
        );

        if ( empty( $users ) ) {
            return null;
        }

        $total_points = 0;
        foreach ( $users as $user ) {
            $total_points += floatval( $user->points_balance );
        }

        if ( $total_points <= 0 ) {
            return null;
        }

        // Weighted random selection
        $random     = (float) mt_rand() / (float) mt_getrandmax() * $total_points;
        $cumulative = 0;
        $winner     = $users[0];

        foreach ( $users as $user ) {
            $cumulative += floatval( $user->points_balance );
            if ( $random <= $cumulative ) {
                $winner = $user;
                break;
            }
        }

        return $winner;
    }

    /**
     * Get total count of users matching a search query
     *
     * @param string $search
     * @return int
     */
    public static function get_search_users_count( $search ) {
        global $wpdb;
        $table = WPR_Database::points_table();

        $like = '%' . $wpdb->esc_like( $search ) . '%';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM $table p
                 JOIN {$wpdb->users} u ON p.user_id = u.ID
                 WHERE u.display_name LIKE %s OR u.user_email LIKE %s",
                $like,
                $like
            )
        );
    }
}
