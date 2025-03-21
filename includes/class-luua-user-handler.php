<?php
class LUUA_User_Handler {
    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $message = '';
        if ( isset( $_POST['luua_bulk_create_users'] ) && isset( $_POST['luua_listing_ids'] ) ) {
            $result = self::handle_bulk_create_users( $_POST['luua_listing_ids'] );
            $message = $result['message'];
        }

        if ( isset( $_GET['luua_single_create_user'] ) && isset( $_GET['listing_id'] ) ) {
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'luua_create_single_user' ) ) {
                $result = self::handle_bulk_create_users( array( intval( $_GET['listing_id'] ) ) );
                $message = $result['message'];
            } else {
                $message = __( 'Nonce verification failed. Action not processed.', 'listeo-listing-user-assign' );
            }
        }

        $filter_admin = isset( $_GET['luua_filter_admin'] ) ? sanitize_text_field( $_GET['luua_filter_admin'] ) : '0';
        $admin_user_ids = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $posts_per_page = get_option( 'luua_listings_per_page', 30 );

        $total_args = array(
            'post_type'      => 'listing',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        $total_listings_query = new WP_Query( $total_args );
        $total_listings = $total_listings_query->post_count;

        $args = array(
            'post_type'      => 'listing',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
        );

        if ( $filter_admin === '0' ) {
            $args['author__not_in'] = $admin_user_ids;
        } elseif ( $filter_admin === '2' ) {
            $args['author__in'] = $admin_user_ids;
        }

        $listings_query = new WP_Query( $args );
        
        $filtered_total_args = array(
            'post_type'      => 'listing',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );
        if ( $filter_admin === '0' ) {
            $filtered_total_args['author__not_in'] = $admin_user_ids;
        } elseif ( $filter_admin === '2' ) {
            $filtered_total_args['author__in'] = $admin_user_ids;
        }
        $filtered_total_query = new WP_Query( $filtered_total_args );
        $filtered_total = $filtered_total_query->post_count;

        $send_email_enabled = get_option( 'luua_send_email', 0 );
        ?>

        <div class="wrap luua-wrap">
            <h1><?php esc_html_e( 'Listing User Assign', 'listeo-listing-user-assign' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo strpos( $message, 'failed' ) !== false ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <form method="get" class="luua-filter-form">
                <input type="hidden" name="page" value="luua-listing-user-assign" />
                <div class="luua-radio-group">
                    <label><input type="radio" name="luua_filter_admin" value="0" <?php checked( $filter_admin, '0' ); ?> /> <?php esc_html_e( 'Hide admin-owned', 'listeo-listing-user-assign' ); ?></label>
                    <label><input type="radio" name="luua_filter_admin" value="1" <?php checked( $filter_admin, '1' ); ?> /> <?php esc_html_e( 'Show all', 'listeo-listing-user-assign' ); ?></label>
                    <label><input type="radio" name="luua_filter_admin" value="2" <?php checked( $filter_admin, '2' ); ?> /> <?php esc_html_e( 'Show only admin-owned', 'listeo-listing-user-assign' ); ?></label>
                    <input type="submit" class="button luua-button" value="<?php esc_attr_e( 'Filter', 'listeo-listing-user-assign' ); ?>" />
                </div>
                <div class="luua-listing-count">
                    <?php printf( esc_html__( '[%d listings fetched / %d listings in total]', 'listeo-listing-user-assign' ), $filtered_total, $total_listings ); ?>
                </div>
            </form>

            <?php if ( $listings_query->have_posts() ) : ?>
                <form method="post" class="luua-form">
                    <?php wp_nonce_field( 'luua_bulk_action', 'luua_nonce' ); ?>
                    <table class="wp-list-table widefat fixed striped luua-table">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="luua-check-all" /></th>
                                <th><?php esc_html_e( 'Current Owner', 'listeo-listing-user-assign' ); ?></th>
                                <th><?php esc_html_e( 'Listing Title', 'listeo-listing-user-assign' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'listeo-listing-user-assign' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'listeo-listing-user-assign' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ( $listings_query->have_posts() ) : $listings_query->the_post(); ?>
                                <?php
                                $listing_id = get_the_ID();
                                $owner_id = get_post_field( 'post_author', $listing_id );
                                $owner_user = get_userdata( $owner_id );
                                $owner_name = $owner_user ? $owner_user->user_login : __( 'No user', 'listeo-listing-user-assign' );
                                $listing_email = get_post_meta( $listing_id, '_email', true );
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="luua_listing_ids[]" value="<?php echo esc_attr( $listing_id ); ?>" /></td>
                                    <td><?php echo esc_html( $owner_name ); ?></td>
                                    <td>
                                        <strong><a href="<?php echo esc_url( get_edit_post_link( $listing_id ) ); ?>"><?php the_title(); ?></a></strong>
                                        <div><a href="<?php echo esc_url( get_permalink( $listing_id ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'listeo-listing-user-assign' ); ?></a></div>
                                    </td>
                                    <td><?php echo esc_html( $listing_email ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( add_query_arg( array(
                                            'luua_single_create_user' => 1,
                                            'listing_id' => $listing_id,
                                            '_wpnonce' => wp_create_nonce( 'luua_create_single_user' ),
                                            'luua_filter_admin' => $filter_admin,
                                        ), admin_url( 'admin.php?page=luua-listing-user-assign' ) ) ); ?>" class="button luua-button">
                                            <?php echo $send_email_enabled ? esc_html__( 'Create/Assign - Queue Email', 'listeo-listing-user-assign' ) : esc_html__( 'Create User & Assign', 'listeo-listing-user-assign' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <p><input type="submit" name="luua_bulk_create_users" class="button luua-button-primary" value="<?php esc_attr_e( 'Bulk Create Users', 'listeo-listing-user-assign' ); ?>"></p>
                </form>

                <?php
                $total_pages = $listings_query->max_num_pages;
                if ( $total_pages > 1 ) {
                    echo '<div class="luua-pagination">';
                    echo paginate_links( array(
                        'base' => add_query_arg( array( 'paged' => '%#%', 'luua_filter_admin' => $filter_admin ) ),
                        'format' => '',
                        'current' => max( 1, $paged ),
                        'total' => $total_pages,
                        'prev_text' => __( '« Previous', 'listeo-listing-user-assign' ),
                        'next_text' => __( 'Next »', 'listeo-listing-user-assign' ),
                    ) );
                    echo '</div>';
                }
                wp_reset_postdata();
                ?>
            <?php else : ?>
                <p class="luua-no-results"><?php esc_html_e( 'No Listings found.', 'listeo-listing-user-assign' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_bulk_create_users( $listing_ids ) {
        global $wpdb;
        $filter_admin = isset( $_GET['luua_filter_admin'] ) ? sanitize_text_field( $_GET['luua_filter_admin'] ) : '0';
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $send_email = get_option( 'luua_send_email', 0 );
        $email_template = get_option( 'luua_email_template', LUUA_Settings::get_default_email_template() );
        $email_subject = get_option( 'luua_email_subject', 'Your New Account' );
        $table_name = $wpdb->prefix . 'luua_email_queue';
        $users_created = 0;
        $emails_queued = 0;
        $message = '';

        if ( isset( $_POST['luua_nonce'] ) && ! wp_verify_nonce( $_POST['luua_nonce'], 'luua_bulk_action' ) ) {
            return array( 'message' => __( 'Security check failed. Please try again.', 'listeo-listing-user-assign' ) );
        }

        foreach ( $listing_ids as $listing_id ) {
            $listing_id = intval( $listing_id );
            $post = get_post( $listing_id );

            if ( ! $post || $post->post_type !== 'listing' ) {
                error_log( "LUUA: Invalid listing ID or post type: $listing_id" );
                $message .= sprintf( __( 'Invalid listing ID or post type: %d. ', 'listeo-listing-user-assign' ), $listing_id );
                continue;
            }

            $listing_title = $post->post_title;
            $listing_email = get_post_meta( $listing_id, '_email', true );

            if ( empty( $listing_email ) ) {
                error_log( "LUUA: No email found for listing ID: $listing_id" );
                $message .= sprintf( __( 'No email found for listing ID: %d. ', 'listeo-listing-user-assign' ), $listing_id );
                continue;
            }

            $existing_user = get_user_by( 'email', $listing_email );
            if ( ! $existing_user ) {
                $random_password = wp_generate_password( 12 );
                $email_prefix = strtok( $listing_email, '@' );
                $base_username = sanitize_user( $listing_title . '-' . $email_prefix, true );
                $username = $base_username;
                $counter = 1;

                while ( username_exists( $username ) ) {
                    $username = $base_username . $counter;
                    $counter++;
                }

                $user_id = wp_insert_user( array(
                    'user_login' => $username,
                    'user_email' => sanitize_email( $listing_email ),
                    'user_pass' => $random_password,
                    'display_name' => $listing_title,
                    'role' => 'owner',
                    'send_user_notification' => false
                ) );

                if ( is_wp_error( $user_id ) ) {
                    error_log( "LUUA: User creation failed for $listing_email with username $username: " . $user_id->get_error_message() );
                    $message .= sprintf( __( 'Failed to create user for listing %d: %s. ', 'listeo-listing-user-assign' ), $listing_id, $user_id->get_error_message() );
                    continue;
                }

                wp_update_post( array( 'ID' => $listing_id, 'post_author' => $user_id ) );
                $users_created++;

                if ( $send_email ) {
                    $reset_link = wp_login_url() . '?action=rp&key=' . get_password_reset_key( get_userdata( $user_id ) ) . '&login=' . rawurlencode( $username );
                    $email_content = str_replace(
                        array( '{username}', '{email}', '{password_reset}' ),
                        array( $username, $listing_email, $reset_link ),
                        $email_template
                    );
                    $inserted = $wpdb->insert(
                        $table_name,
                        array(
                            'listing_id' => $listing_id,
                            'recipient' => $listing_email,
                            'subject' => $email_subject,
                            'content' => $email_content,
                            'status' => 'queued',
                        )
                    );
                    if ( $inserted ) {
                        $emails_queued++;
                    } else {
                        error_log( "LUUA: Failed to queue email for $listing_email: " . $wpdb->last_error );
                        $message .= sprintf( __( 'Failed to queue email for listing %d: %s. ', 'listeo-listing-user-assign' ), $listing_id, $wpdb->last_error );
                    }
                }
            } else {
                wp_update_post( array( 'ID' => $listing_id, 'post_author' => $existing_user->ID ) );
                $users_created++;
            }
        }

        $message .= sprintf(
            __( 'Processed %d listings: %d users created, %d emails queued for processing.', 'listeo-listing-user-assign' ),
            count( $listing_ids ),
            $users_created,
            $emails_queued
        );
        wp_safe_redirect( add_query_arg( array( 'paged' => $paged, 'luua_filter_admin' => $filter_admin, 'luua_done' => 1, 'message' => urlencode( $message ) ), admin_url( 'admin.php?page=luua-listing-user-assign' ) ) );
        exit;
    }
}