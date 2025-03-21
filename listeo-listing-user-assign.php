<?php
/*
Plugin Name: Listeo Listing User Assign
Description: A plugin for bulk user creation from custom post type "listing" with customizable settings and email queuing.
Version: 1.2
Author: George Koulouridhs
Text Domain: listeo-listing-user-assign
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LUUA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LUUA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include necessary classes
require_once LUUA_PLUGIN_DIR . 'includes/class-luua-settings.php';
require_once LUUA_PLUGIN_DIR . 'includes/class-luua-user-handler.php';

// Load text domain for translations
add_action( 'plugins_loaded', 'luua_load_textdomain' );
function luua_load_textdomain() {
    load_plugin_textdomain( 'listeo-listing-user-assign', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Register activation hook
register_activation_hook( __FILE__, 'luua_activate' );
function luua_activate() {
    add_role( 'owner', __( 'Owner', 'listeo-listing-user-assign' ), array( 'read' => true ) );
    global $wpdb;
    $table_name = $wpdb->prefix . 'luua_email_queue';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        listing_id BIGINT(20) UNSIGNED NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        status ENUM('queued', 'sent', 'failed') DEFAULT 'queued',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    if ( ! wp_next_scheduled( 'luua_process_email_queue' ) ) {
        wp_schedule_event( time(), 'luua_custom_interval', 'luua_process_email_queue' );
        error_log( "LUUA: Cron event scheduled on activation." );
    }
}

// Register deactivation hook to clean up cron event
register_deactivation_hook( __FILE__, 'luua_deactivate' );
function luua_deactivate() {
    wp_clear_scheduled_hook( 'luua_process_email_queue' );
    error_log( "LUUA: Cron event cleared on deactivation." );
}

// Initialize settings and admin menu
add_action( 'admin_menu', 'luua_register_admin_menu' );
function luua_register_admin_menu() {
    add_menu_page(
        __( 'Listing User Assign', 'listeo-listing-user-assign' ),
        __( 'Listing User Assign', 'listeo-listing-user-assign' ),
        'manage_options',
        'luua-listing-user-assign',
        'luua_admin_page_callback',
        'dashicons-admin-users',
        25
    );

    add_submenu_page(
        'luua-listing-user-assign',
        __( 'Settings', 'listeo-listing-user-assign' ),
        __( 'Settings', 'listeo-listing-user-assign' ),
        'manage_options',
        'luua-settings',
        array( 'LUUA_Settings', 'render_settings_page' )
    );

    if ( ! wp_next_scheduled( 'luua_process_email_queue' ) ) {
        wp_schedule_event( time(), 'luua_custom_interval', 'luua_process_email_queue' );
        error_log( "LUUA: Cron event rescheduled on admin menu load." );
    }
}

// Admin page callback
function luua_admin_page_callback() {
    LUUA_User_Handler::render_admin_page();
}

// Enqueue styles and scripts
add_action( 'admin_enqueue_scripts', 'luua_enqueue_admin_assets' );
function luua_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'luua-listing-user-assign' ) !== false || strpos( $hook, 'luua-settings' ) !== false ) {
        wp_enqueue_style( 'luua-admin-styles', LUUA_PLUGIN_URL . 'assets/css/admin-styles.css', array(), '1.2' );
        wp_enqueue_script( 'luua-admin-scripts', LUUA_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'jquery' ), '1.2', true );
    }
}

// Define custom cron interval
add_filter( 'cron_schedules', 'luua_add_cron_interval' );
function luua_add_cron_interval( $schedules ) {
    $interval = get_option( 'luua_email_batch_interval', 5 ) * 60; // Convert minutes to seconds
    $schedules['luua_custom_interval'] = array(
        'interval' => $interval,
        'display'  => __( 'LUUA Custom Interval', 'listeo-listing-user-assign' ),
    );
    error_log( "LUUA: Cron interval set to $interval seconds." );
    return $schedules;
}

// Process email queue
add_action( 'luua_process_email_queue', 'luua_process_email_queue_callback' );
function luua_process_email_queue_callback() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'luua_email_queue';
    $batch_size = get_option( 'luua_email_batch_size', 5 );
    $max_execution_time = ini_get( 'max_execution_time' ) - 5; // Leave 5 seconds buffer
    $start_time = microtime( true );

    error_log( "LUUA: Cron started. Batch size: $batch_size, Table: $table_name" );
    $emails = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM $table_name WHERE status = 'queued' LIMIT %d",
        $batch_size
    ) );

    if ( empty( $emails ) ) {
        error_log( "LUUA: No emails found in queue." );
        return;
    }

    foreach ( $emails as $email ) {
        // Check if execution time is close to limit
        if ( ( microtime( true ) - $start_time ) > $max_execution_time ) {
            error_log( "LUUA: Execution time limit reached. Stopping email processing." );
            break;
        }

        error_log( "LUUA: Processing email ID {$email->id} to {$email->recipient}" );
        $sent = wp_mail( $email->recipient, $email->subject, $email->content );
        $status = $sent ? 'sent' : 'failed';
        $wpdb->update(
            $table_name,
            array( 'status' => $status, 'sent_at' => current_time( 'mysql' ) ),
            array( 'id' => $email->id )
        );
        if ( $sent ) {
            error_log( "LUUA: Email ID {$email->id} sent successfully." );
        } else {
            error_log( "LUUA: Email ID {$email->id} failed to send." );
        }
    }
}