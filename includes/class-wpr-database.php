<?php
/**
 * Database handler for WooPoints
 *
 * Creates and manages the custom tables for storing
 * user points and points transaction history.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPR_Database {

    /**
     * Create custom database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Points balance table
        $table_points = $wpdb->prefix . 'wpr_points';
        $sql_points = "CREATE TABLE $table_points (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points_balance decimal(10,2) NOT NULL DEFAULT 0,
            total_earned decimal(10,2) NOT NULL DEFAULT 0,
            total_spent decimal(10,2) NOT NULL DEFAULT 0,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // Points transaction log
        $table_log = $wpdb->prefix . 'wpr_points_log';
        $sql_log = "CREATE TABLE $table_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points decimal(10,2) NOT NULL,
            type varchar(50) NOT NULL,
            reference_id bigint(20) unsigned DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Spin wheel history
        $table_spins = $wpdb->prefix . 'wpr_spin_history';
        $sql_spins = "CREATE TABLE $table_spins (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            prize_label varchar(255) NOT NULL,
            prize_value varchar(255) NOT NULL,
            prize_type varchar(50) NOT NULL,
            spun_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_points );
        dbDelta( $sql_log );
        dbDelta( $sql_spins );
    }

    /**
     * Get points table name
     */
    public static function points_table() {
        global $wpdb;
        return $wpdb->prefix . 'wpr_points';
    }

    /**
     * Get log table name
     */
    public static function log_table() {
        global $wpdb;
        return $wpdb->prefix . 'wpr_points_log';
    }

    /**
     * Get spin history table name
     */
    public static function spin_table() {
        global $wpdb;
        return $wpdb->prefix . 'wpr_spin_history';
    }
}
