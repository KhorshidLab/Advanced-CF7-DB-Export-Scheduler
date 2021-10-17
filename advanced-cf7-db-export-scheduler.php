<?php

/**
 *
 * @link              khorshidlab.com
 * @since             1.0.0
 * @package           advanced-cf7-db-export-scheduler
 *
 * @wordpress-plugin
 * Plugin Name:       Advanced CF7 DB Export Scheduler
 * Plugin URI:        https://khorshidlab.com/
 * Description:       Export Scheduler Extension for Advanced CF7 DB
 * Version:           1.0.1
 * Author:            Khorshid
 * Author URI:        https://khorshidlab.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kh-acsr
 * Domain Path:       /languages
 */

 // If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'class-advanced-cf7-db-export-scheduler.php';

/**
 * The code that runs during plugin activation.
 */

function activate_advanced_cf7_db_export_scheduler() {

	Advanced_Cf7_Db_Export_Scheduler::setup_schedule_report_cron();

}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_advanced_cf7_db_export_scheduler() {

	Advanced_Cf7_Db_Export_Scheduler::unset_schedule_report_cron();

}

register_activation_hook( __FILE__, 'activate_advanced_cf7_db_export_scheduler' );
register_deactivation_hook( __FILE__, 'deactivate_advanced_cf7_db_export_scheduler' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_advanced_cf7_db_export_scheduler() {

	$plugin = new Advanced_Cf7_Db_Export_Scheduler();

}

run_advanced_cf7_db_export_scheduler();