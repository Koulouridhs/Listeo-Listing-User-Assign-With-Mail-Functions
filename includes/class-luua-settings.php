<?php
class LUUA_Settings {
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_luua_test_email', array( __CLASS__, 'send_test_email' ) );
    }

    public static function register_settings() {
        register_setting( 'luua_settings_group', 'luua_listings_per_page', array( 'sanitize_callback' => 'absint', 'default' => 30 ) );
        register_setting( 'luua_settings_group', 'luua_send_email', array( 'sanitize_callback' => 'absint', 'default' => 0 ) );
        register_setting( 'luua_settings_group', 'luua_email_subject', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Your New Account' ) );
        register_setting( 'luua_settings_group', 'luua_email_template', array( 'sanitize_callback' => 'wp_kses_post', 'default' => self::get_default_email_template() ) );
        register_setting( 'luua_settings_group', 'luua_email_batch_size', array( 'sanitize_callback' => 'absint', 'default' => 5 ) );
        register_setting( 'luua_settings_group', 'luua_email_batch_interval', array( 'sanitize_callback' => 'absint', 'default' => 5 ) );

        // Handle reset request
        if ( isset( $_GET['luua_reset_settings'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'luua_reset_settings' ) ) {
            self::reset_to_defaults();
            wp_safe_redirect( admin_url( 'admin.php?page=luua-settings&tab=settings&reset=1' ) );
            exit;
        }
    }

    public static function render_settings_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'luua_email_queue';
        $queued = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'queued'" );
        $sent = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'sent'" );
        $failed = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'failed'" );
        $total = $queued + $sent + $failed;
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
        $send_email_enabled = get_option( 'luua_send_email', 0 );
        $filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';

        // Ensure non-empty values for settings
        $email_subject = get_option( 'luua_email_subject', 'Your New Account' );
        if ( empty( $email_subject ) ) {
            $email_subject = 'Your New Account';
            update_option( 'luua_email_subject', $email_subject );
        }
        $email_template = get_option( 'luua_email_template', self::get_default_email_template() );
        if ( empty( $email_template ) ) {
            $email_template = self::get_default_email_template();
            update_option( 'luua_email_template', $email_template );
        }
        $email_batch_size = get_option( 'luua_email_batch_size', 5 );
        if ( empty( $email_batch_size ) ) {
            $email_batch_size = 5;
            update_option( 'luua_email_batch_size', $email_batch_size );
        }
        $email_batch_interval = get_option( 'luua_email_batch_interval', 5 );
        if ( empty( $email_batch_interval ) ) {
            $email_batch_interval = 5;
            update_option( 'luua_email_batch_interval', $email_batch_interval );
        }

        if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === 'luua_settings_group' && isset( $_POST['luua_send_email'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=luua-settings&tab=email' ) );
            exit;
        }

        if ( isset( $_GET['reset'] ) && $_GET['reset'] == 1 ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings have been reset to defaults.', 'listeo-listing-user-assign' ) . '</p></div>';
        }
        ?>
        <div class="wrap luua-settings-wrap">
            <h1><?php esc_html_e( 'Listing User Assign Settings', 'listeo-listing-user-assign' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=luua-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'listeo-listing-user-assign' ); ?></a>
                <a href="?page=luua-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email', 'listeo-listing-user-assign' ); ?></a>
                <a href="?page=luua-settings&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Logs', 'listeo-listing-user-assign' ); ?>
                    <?php if ( $queued > 0 ) : ?>
                        <span class="luua-badge"><?php echo esc_html( $queued ); ?></span>
                    <?php endif; ?>
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields( 'luua_settings_group' ); ?>

                <?php if ( $active_tab === 'settings' ) : ?>
                    <table class="form-table">
                        <tr class="luua-form-table-row">
                            <th class="luua-form-table-header"><label for="luua_listings_per_page"><?php esc_html_e( 'Listings per page', 'listeo-listing-user-assign' ); ?></label></th>
                            <td><input type="number" name="luua_listings_per_page" id="luua_listings_per_page" value="<?php echo esc_attr( get_option( 'luua_listings_per_page', 30 ) ); ?>" min="1" class="small-text" /></td>
                        </tr>
                        <tr class="luua-form-table-row">
                            <th class="luua-form-table-header"><label for="luua_send_email"><?php esc_html_e( 'Send Email on User Creation', 'listeo-listing-user-assign' ); ?></label></th>
                            <td>
                                <label><input type="checkbox" name="luua_send_email" id="luua_send_email" value="1" <?php checked( 1, $send_email_enabled ); ?> /> <?php esc_html_e( 'Enable email notifications (queued)', 'listeo-listing-user-assign' ); ?></label>
                                <p class="description"><?php esc_html_e( 'Save changes to configure email settings.', 'listeo-listing-user-assign' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                    <p><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=luua-settings&tab=settings&luua_reset_settings=1' ), 'luua_reset_settings' ) ); ?>" class="button luua-reset-button" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all settings to defaults?', 'listeo-listing-user-assign' ); ?>');"><?php esc_html_e( 'Reset to Defaults', 'listeo-listing-user-assign' ); ?></a></p>

                <?php elseif ( $active_tab === 'email' ) : ?>
                    <?php if ( $send_email_enabled ) : ?>
                        <input type="hidden" name="luua_send_email" value="1" />
                        <table class="form-table">
                            <tr class="luua-form-table-row">
                                <th class="luua-form-table-header"><label for="luua_email_subject"><?php esc_html_e( 'Email Subject', 'listeo-listing-user-assign' ); ?></label></th>
                                <td>
                                    <input type="text" name="luua_email_subject" id="luua_email_subject" value="<?php echo esc_attr( $email_subject ); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e( 'The subject line for the email.', 'listeo-listing-user-assign' ); ?></p>
                                </td>
                            </tr>
                            <tr class="luua-form-table-row">
                                <th class="luua-form-table-header"><label><?php esc_html_e( 'Email Template', 'listeo-listing-user-assign' ); ?></label></th>
                                <td>
                                    <?php wp_editor( $email_template, 'luua_email_template', array( 'textarea_name' => 'luua_email_template', 'media_buttons' => false, 'teeny' => true ) ); ?>
                                    <p class="description"><?php esc_html_e( 'Available placeholders: {username}, {email}, {password_reset}', 'listeo-listing-user-assign' ); ?></p>
                                </td>
                            </tr>
                            <tr class="luua-form-table-row">
                                <th class="luua-form-table-header"><label for="luua_email_batch_size"><?php esc_html_e( 'Emails per Batch', 'listeo-listing-user-assign' ); ?></label></th>
                                <td>
                                    <input type="number" name="luua_email_batch_size" id="luua_email_batch_size" value="<?php echo esc_attr( $email_batch_size ); ?>" min="1" class="small-text" />
                                    <p class="description"><?php esc_html_e( 'Number of emails to send per batch.', 'listeo-listing-user-assign' ); ?></p>
                                </td>
                            </tr>
                            <tr class="luua-form-table-row">
                                <th class="luua-form-table-header"><label for="luua_email_batch_interval"><?php esc_html_e( 'Batch Interval (minutes)', 'listeo-listing-user-assign' ); ?></label></th>
                                <td>
                                    <input type="number" name="luua_email_batch_interval" id="luua_email_batch_interval" value="<?php echo esc_attr( $email_batch_interval ); ?>" min="1" class="small-text" />
                                    <p class="description"><?php esc_html_e( 'Interval between batches in minutes.', 'listeo-listing-user-assign' ); ?></p>
                                </td>
                            </tr>
                            <tr class="luua-form-table-row">
                                <th class="luua-form-table-header"><label for="luua_test_email"><?php esc_html_e( 'Test Email', 'listeo-listing-user-assign' ); ?></label></th>
                                <td>
                                    <input type="email" id="luua_test_email" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your email address', 'listeo-listing-user-assign' ); ?>" />
                                    <button type="button" class="button luua-test-email"><?php esc_html_e( 'Send Test Email', 'listeo-listing-user-assign' ); ?></button>
                                    <p class="description"><?php esc_html_e( 'Send a test email to verify your settings.', 'listeo-listing-user-assign' ); ?></p>
                                    <div id="luua-test-email-response"></div>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    <?php else : ?>
                        <p class="luua-notice"><?php esc_html_e( 'Please enable Send Email on User Creation to configure email settings.', 'listeo-listing-user-assign' ); ?></p>
                    <?php endif; ?>

                <?php elseif ( $active_tab === 'logs' ) : ?>
                    <div class="luua-logs">
                        <h2><?php esc_html_e( 'Email Queue Logs', 'listeo-listing-user-assign' ); ?></h2>
                        <div class="luua-filter">
                            <label><?php esc_html_e( 'Filter:', 'listeo-listing-user-assign' ); ?></label>
                            <select onchange="window.location.href='?page=luua-settings&tab=logs&filter=' + this.value;">
                                <option value="all" <?php selected( $filter, 'all' ); ?>><?php esc_html_e( 'All', 'listeo-listing-user-assign' ); ?></option>
                                <option value="queued" <?php selected( $filter, 'queued' ); ?>><?php esc_html_e( 'Queued', 'listeo-listing-user-assign' ); ?></option>
                                <option value="sent" <?php selected( $filter, 'sent' ); ?>><?php esc_html_e( 'Sent', 'listeo-listing-user-assign' ); ?></option>
                                <option value="failed" <?php selected( $filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'listeo-listing-user-assign' ); ?></option>
                            </select>
                        </div>
                        <?php
                        $where = $filter === 'all' ? '' : "WHERE status = '$filter'";
                        $logs = $wpdb->get_results( "SELECT id, recipient, subject, status, sent_at FROM $table_name $where ORDER BY created_at DESC LIMIT 20" );
                        ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'ID', 'listeo-listing-user-assign' ); ?></th>
                                    <th><?php esc_html_e( 'Recipient', 'listeo-listing-user-assign' ); ?></th>
                                    <th><?php esc_html_e( 'Subject', 'listeo-listing-user-assign' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'listeo-listing-user-assign' ); ?></th>
                                    <th><?php esc_html_e( 'Sent At', 'listeo-listing-user-assign' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( $logs ) : ?>
                                    <?php foreach ( $logs as $log ) : ?>
                                        <tr>
                                            <td><?php echo esc_html( $log->id ); ?></td>
                                            <td><?php echo esc_html( $log->recipient ); ?></td>
                                            <td><?php echo esc_html( $log->subject ); ?></td>
                                            <td><span class="luua-status <?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( ucfirst( $log->status ) ); ?></span></td>
                                            <td><?php echo $log->sent_at ? esc_html( $log->sent_at ) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr><td colspan="5"><?php esc_html_e( 'No logs found.', 'listeo-listing-user-assign' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <p><strong><?php esc_html_e( 'Total:', 'listeo-listing-user-assign' ); ?></strong> <?php echo esc_html( $total ); ?> | 
                           <strong><?php esc_html_e( 'Queued:', 'listeo-listing-user-assign' ); ?></strong> <?php echo esc_html( $queued ); ?> | 
                           <strong><?php esc_html_e( 'Sent:', 'listeo-listing-user-assign' ); ?></strong> <?php echo esc_html( $sent ); ?> | 
                           <strong><?php esc_html_e( 'Failed:', 'listeo-listing-user-assign' ); ?></strong> <?php echo esc_html( $failed ); ?> | 
                           <?php if ( $total > 0 ) : ?><strong><?php esc_html_e( 'Progress:', 'listeo-listing-user-assign' ); ?></strong> <?php echo esc_html( round( ( $sent / $total ) * 100, 2 ) ); ?>%<?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.luua-test-email').on('click', function() {
                    var email = $('#luua_test_email').val();
                    if (!email) {
                        alert('<?php esc_html_e( 'Please enter an email address.', 'listeo-listing-user-assign' ); ?>');
                        return;
                    }
                    $.post(ajaxurl, {
                        action: 'luua_test_email',
                        email: email,
                        subject: $('#luua_email_subject').val(),
                        template: $('#luua_email_template').val(),
                        _wpnonce: '<?php echo wp_create_nonce( 'luua_settings_group' ); ?>' // Add nonce here
                    }, function(response) {
                        $('#luua-test-email-response').html(response.data);
                    });
                });
            });
        </script>
        <?php
    }

    public static function send_test_email() {
        check_ajax_referer( 'luua_settings_group', '_wpnonce' );
        $email = sanitize_email( $_POST['email'] );
        $subject = sanitize_text_field( $_POST['subject'] );
        $template = wp_kses_post( $_POST['template'] );
        $test_content = str_replace(
            array( '{username}', '{email}', '{password_reset}' ),
            array( 'TestUser', $email, 'https://example.com/reset' ),
            $template
        );

        // Check if wp_mail function exists
        if ( ! function_exists( 'wp_mail' ) ) {
            wp_send_json_error( '<p class="luua-error">' . esc_html__( 'wp_mail function not available. Please check your WordPress installation.', 'listeo-listing-user-assign' ) . '</p>' );
            return;
        }

        // Attempt to send the email
        $sent = wp_mail( $email, $subject, $test_content );

        // If wp_mail returns false, provide a more informative message
        if ( $sent ) {
            wp_send_json_success( '<p class="luua-success">' . esc_html__( 'Test email sent successfully!', 'listeo-listing-user-assign' ) . '</p>' );
        } else {
            // Since the email might still be sent despite wp_mail returning false
            $message = '<p class="luua-error">' . esc_html__( 'wp_mail reported failure, but the email may have been sent. Please check your inbox/spam folder. If not received, verify your SMTP settings or WordPress mail configuration.', 'listeo-listing-user-assign' ) . '</p>';
            wp_send_json_error( $message );
        }
    }

    public static function get_default_email_template() {
        return "Dear {username},\n\n" .
               "We are pleased to inform you that your account has been successfully created. Below are your account details for your reference:\n\n" .
               "Email: {email}\n\n" .
               "To get started, please reset your password using the following link:\n" .
               "{password_reset}\n\n" .
               "If you have any questions or require assistance, feel free to contact our support team at [email].\n\n" .
               "Thank you for choosing us!\n\n" .
               "Best regards,\n" .
               "[Brand Name]";
    }

    public static function reset_to_defaults() {
        $success = true;
        $success &= update_option( 'luua_listings_per_page', 30 );
        $success &= update_option( 'luua_send_email', 0 );
        $success &= update_option( 'luua_email_subject', 'Your New Account' );
        $success &= update_option( 'luua_email_template', self::get_default_email_template() );
        $success &= update_option( 'luua_email_batch_size', 5 );
        $success &= update_option( 'luua_email_batch_interval', 5 );

        if ( ! $success ) {
            error_log( "LUUA: Failed to reset some settings to defaults." );
        }
    }
}

LUUA_Settings::init();