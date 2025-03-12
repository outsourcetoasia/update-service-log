<?php
/*
Plugin Name: Update Service Log
Plugin URI: https://github.com/outsourcetoasia/update-service-log/releases
Description: Logs plugin and theme updates with username, date, and time and displays it in the Dashboard
Version: 1.0.9
Requires at least: 6.6
Requires PHP: 8.0.0
Tested up to: 6.7.2
Author: OutsourceToAsia
Author URI: http://www.outsourcetoasia.de
Contributors: Tom Steinczhorn
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl.html
Tags: update, log, service log, dashboard notices
Text Domain: ota
GitHub Plugin URI: https://github.com/outsourcetoasia/update-service-log
GitHub Release Asset: true
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Include the updater class file.
require_once plugin_dir_path( __FILE__ ) . 'github-plugin-updater.php';

// Initialize the updater (replace with your GitHub username and repo).
new GitHub_Plugin_Updater( __FILE__, 'outsourcetoasia', 'update-service-log' );

class UpdateServiceLog {
	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'create_log_table' ] );
		add_action( 'upgrader_process_complete', [ $this, 'log_updates' ], 10, 2 );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	public function create_log_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'update_service_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            update_time DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function log_updates( $upgrader_object, $options ) {
		if ( ! isset( $options['type'] ) || ! isset( $options['action'] ) || $options['action'] !== 'update' ) {
			return;
		}

		$current_user = wp_get_current_user();
		$username     = $current_user->user_login;
		global $wpdb;
		$table_name = $wpdb->prefix . 'update_service_log';

		if ( $options['type'] === 'plugin' && ! empty( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
				$wpdb->insert( $table_name, [
					'username'    => $username,
					'item_name'   => $plugin_data['Name'],
					'item_type'   => 'Plugin',
					'update_time' => current_time( 'mysql' ),
				] );
			}
		}

		if ( $options['type'] === 'theme' && ! empty( $options['themes'] ) ) {
			foreach ( $options['themes'] as $theme ) {
				$theme_data = wp_get_theme( $theme );
				$wpdb->insert( $table_name, [
					'username'    => $username,
					'item_name'   => $theme_data->get( 'Name' ),
					'item_type'   => 'Theme',
					'update_time' => current_time( 'mysql' ),
				] );
			}
		}
	}

	public function register_dashboard_widget() {
		wp_add_dashboard_widget( 'update_logger_widget', 'Update Logs', [ $this, 'display_dashboard_widget' ] );
	}

	public function display_dashboard_widget() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'update_service_log';
		$logs       = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY update_time DESC LIMIT 10" );
		echo '<ul>';
		foreach ( $logs as $log ) {
			echo '<li><strong>' . esc_html( $log->username ) . '</strong> updated <em>' . esc_html( $log->item_name ) . '</em> (' . esc_html( $log->item_type ) . ') on ' . esc_html( $log->update_time ) . '</li>';
		}
		echo '</ul>';
	}
}

new UpdateServiceLog();
