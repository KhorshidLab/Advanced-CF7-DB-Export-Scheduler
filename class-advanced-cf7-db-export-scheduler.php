<?php 
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       khorshidlab.com
 * @since      1.0.0
 * @package    advanced_cf7_db_export_scheduler
 */

class Advanced_Cf7_Db_Export_Scheduler {

    public function __construct() {

        global $wpdb;
        
        $this->plugin_name      = 'Advanced CF7 DB Export Scheduler';
		$this->version          = '1.0.1';
        $this->wpdb             = $wpdb;
        $this->separator        = ",";
        $this->table            = $this->wpdb->prefix . 'cf7_vdata_entry';

        $this->define_admin_hooks();

	}

    /**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

        add_action( 'admin_menu', [ $this, 'add_submenu_to_advanced_cf7_db_menu' ] );
        add_action( 'cf7_db_schedule_report_hook', [ $this, 'cf7_db_schedule_report_exec' ] );
        add_action( 'admin_init', [ $this, 'save_schedule_report_form' ] );
        add_action( 'admin_init', [ $this, 'send_report_to_email' ] );
        add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
        add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );

	}

    public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'kh-acsr',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

    public function add_submenu_to_advanced_cf7_db_menu() {
        
        add_submenu_page(
            'contact-form-listing',
            __( 'Forms Schedule Reports Settings', 'kh-acsr' ),
            __( 'Forms Schedule Reports Settings', 'kh-acsr' ),
            'manage_options',
            'export_forms_settings',
            [ $this, 'export_forms_settings_content' ],
        );

    }

    public function export_forms_settings_content() {
            
        $forms = get_posts( [ 'post_type' => 'wpcf7_contact_form', 'numberposts' => -1 ] );
        $reports_options  = get_option( 'kh-schedule-reports-options' );
        ?>
        <div class="wrap">
            <H1><?php _e( 'Forms Schedule Reports Settings', 'kh-acsr' ) ?></H1>
            <hr>
            <h3><?php _e( 'Select your forms', 'kh-acsr' ) ?></h3>

            <form method="post">
                <?php
                if( $forms ) {
                    foreach( $forms as $form ) {
                        ?>
                        <p>
                            <input id="schedule-report-form-id-<?php echo $form->ID ?>" name="schedule-report-form-id[]" value="<?php echo $form->ID ?>" type="checkbox" <?php echo isset( $reports_options['schedule-report-form-id'] ) && in_array( $form->ID, $reports_options['schedule-report-form-id'] ) ? 'checked="checked"' : '' ?> />
                            <label for="schedule-report-form-id-<?php echo $form->ID ?>"><?php echo $form->post_title ?></label>
                        </p>
                        <?php
                    }
                }
                ?>
                <br>
                <h3><?php _e( 'Report Settings', 'kh-acsr' ) ?></h3>

                <p>
                    <input id="kh-schedule-report-send-email" name="send-email" value="1" type="checkbox" <?php echo isset( $reports_options['send-email'] ) ? 'checked="checked"' : '' ?> />
                    <label for="kh-schedule-report-send-email"><?php _e( 'Send exported file to email after the report completed', 'kh-acsr' ) ?></label>
                </p>

                <p>
                    <input id="kh-schedule-report-remove-db-data" name="remove-db-data" value="1" type="checkbox" <?php echo isset( $reports_options['remove-db-data'] ) ? 'checked="checked"' : '' ?> />
                    <label for="kh-schedule-report-remove-db-data"><?php _e( 'Remove Advanced CF7 data rows from database after the report completed', 'kh-acsr' ) ?></label>
                </p>

                <p>
                    <label for="kh-schedule-report-interval"><?php _e( 'Enter schedule report intervals in days', 'kh-acsr' ) ?></label>
                    <input id="kh-schedule-report-interval" name="report-interval" value="<?php echo isset( $reports_options['report-interval'] ) ? $reports_options['report-interval'] : '30' ?>" type="number" />
                </p>

                <p><input type="submit" name="schedule-report-form-submit" id="submit" class="button button-primary" value="<?php _e( 'Save', 'kh-acsr' ) ?>"></p>
            </form>

            <br>
            <hr>
            <h3><?php _e( 'Send Report to Email', 'kh-acsr' ) ?></h3>
            <p><?php _e( 'You can send selected forms reports to your email immediately. Before that, make sure you have been saved your selected forms.', 'kh-acsr' ) ?></p>
            <form method="post">
                <input type="email" name="email" placeholder="<?php _e( 'Enter your email address', 'kh-acsr' ) ?>">
                <input type="submit" name="schedule-report-send-to-email" class="button button-primary" value="<?php _e( 'Send Email', 'kh-acsr' ) ?>">
            </form>
        </div>
        <?php

    }

    public function save_schedule_report_form() {

        if( isset( $_POST['schedule-report-form-submit'] ) ) {
            $options = [];

            foreach( $_POST as $k => $v ) {
                if( $k != 'schedule-report-form-submit' ) {
                    $options[ $k ] = $v;
                }
            }

            update_option( 'kh-schedule-reports-options', $options );

            if( isset( $options['report-interval'] ) ) {
                $this->setup_schedule_report_cron();
            }
        }

    }

    public function cf7_db_schedule_report_exec() {

        // Check for current user privileges 
        if( !current_user_can( 'manage_options' ) ) { 
            return false; 
        }

        // Check if we are in WP-Admin
        if( !is_admin() ) { 
            return false; 
        }

        $reports_options  = get_option( 'kh-schedule-reports-options' );

        $csv_output = $this->generate_csv();

        if( isset( $reports_options['send-email'] ) ) {
            $send_email = $this->send_output_csv_to_admin_email( $csv_output );

            if( $send_email ) {
                if( isset( $reports_options['remove-db-data'] ) ) {
                    $this->remove_advanced_cf7_db_data();
                }
            }
        }
    
    }

    public static function setup_schedule_report_cron() {

        wp_schedule_event( time(), 'kh_custom_interval', 'cf7_db_schedule_report_hook' );

    }

    public static function unset_schedule_report_cron() {

        $timestamp = wp_next_scheduled( 'cf7_db_schedule_report_hook' );
        
        wp_unschedule_event( $timestamp, 'cf7_db_schedule_report_hook' );

    }

    public function add_cron_interval( $schedules ) {

        $reports_options  = get_option( 'kh-schedule-reports-options' );
        $report_interval = isset( $reports_options['report-interval'] ) ? $reports_options['report-interval'] : '30';

        $schedules['kh_custom_interval'] = [
            'interval' => $report_interval * DAY_IN_SECONDS,
            'display'  => esc_html__( 'CF7 Schedule Report Custom Interval' )
        ];

        return $schedules;

    }

    /**
     * Fetch results from table and return a string containing all the data
     *
     * @return string
     */
    public function generate_csv() {

        ob_start();

        $csv_output = '';
        $csv_output .= $this->get_columns();
        $csv_output .= "\n";
        $csv_output .= $this->get_data();

        $filename    = "advanced_cf7_db_export_scheduler_" . date_i18n( 'Y-m-d@H-i', current_time('timestamp') ) . ".csv";
        $upload      = wp_upload_dir();
        $upload_dir  = $upload['basedir'];
        $upload_dir  = $upload_dir . '/khorshid-exports/';

        if ( !is_dir( $upload_dir ) ) {
            wp_mkdir_p( $upload_dir );
        }

        $fh = fopen( $upload_dir . $filename, 'w' );

        if( $fh === FALSE ) {
            die('Failed to open temporary file');
        }

        fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) ); // UTF-8
        fwrite( $fh, $csv_output );
        rewind( $fh );
        fclose( $fh );

        ob_end_flush();

        return $upload_dir . $filename;

    }

    public function send_output_csv_to_admin_email( $attachment_path, $to = null ) {

        require ABSPATH . 'wp-includes/pluggable.php';

        $date     = date_i18n( 'j F Y', current_time( 'timestamp' ) ) . ' @ ' . date_i18n( 'H:i:s', current_time( 'timestamp' ) );
		$to       = $to == null ? get_option('admin_email') : $to;
		$body     = __( 'Please find the exported file of Advanced CF7 schedule reports attached to this email.', 'kh-acsr' ) . ' <br>';
        $subject  = __( 'Advanced CF7 Export - Date: ', 'kh-acsr' ) . $date;
        $body    .= __( '<br> Report Date: ', 'kh-acsr' ) . $date;
        $headers  = [ 'Content-Type: text/html; charset=UTF-8' ];

		$send = wp_mail( $to, $subject, $body, $headers, $attachment_path );

        return $send;

    }

    public function remove_advanced_cf7_db_data() {

        $this->wpdb->query( "TRUNCATE TABLE `" . $this->table . "`" );       
        $this->wpdb->query( "TRUNCATE TABLE `wp_cf7_vdata`" ); 

    }

    /**
     * Get table columns as a string separated by $this->separator;
     *
     * @return string
     */
    public function get_columns() {

        $output = "";
        $query  = "SHOW COLUMNS FROM `" . $this->table . "`";
        $result = $this->wpdb->get_results( $query );
        
        if ( count($result) > 0 ) {
            foreach ( $result as $row ) {
                $output = $output . $row->Field . $this->separator;
            }

            $output = substr( $output, 0, -1 );
        } else {
            $output = __( 'No form found!', 'kh-acsr' );
        }

        return $output;

    }

    /**
     * Get table data as a string separated by $this->separator
     *
     * @return string
     */
    public function get_data() {

        $reports_options    = get_option( 'kh-schedule-reports-options' );
        $output             = "";
        $ids                = implode( ',', $reports_options['schedule-report-form-id'] );
        $query              = "SELECT * FROM `" . $this->table . "` WHERE cf7_id IN(" . $ids . ")";
        $values             = $this->wpdb->get_results( $query );

        if( $values ) {
            foreach ( $values as $rowr ) {
                $fields = array_values( (array)$rowr );
                $output .= implode( $this->separator, $fields );
                $output .= "\n";
            }
        } else {
            $output = __( 'No form found!', 'kh-acsr' );
        }

        return $output;

    }

    public function send_report_to_email() {

        if( isset( $_POST['schedule-report-send-to-email'] ) ) {
            $csv_output = $this->generate_csv();
            $send_email = $this->send_output_csv_to_admin_email( $csv_output, $_POST['email'] );
            
            if( $send_email ) {
                add_action( 'admin_notices', function () {
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e( 'The report email sent successfully!', 'sample-text-domain' ); ?></p>
                    </div>
                    <?php
                } );
            } else {
                add_action( 'admin_notices', function () {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php _e( 'An error occurred while sending the report email!', 'sample-text-domain' ); ?></p>
                    </div>
                    <?php
                } );
            }
        }
    }

}