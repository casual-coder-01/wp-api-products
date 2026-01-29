<?php
/**
 * Plugin Name: WP API Products
 * Description: Fetch products from an external Node.js API using JWT authentication.
 * Version: 1.0.0
 * Author: Abhinav Thakur
 */

/**
 * ==================================================
 * SECURITY CHECK
 * ==================================================
 * Prevent direct access to this file.
 * This is a standard WordPress security practice.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



/**
 * ==================================================
 * SECTION 1: ADMIN MENU SETUP
 * ==================================================
 * Adds a new menu item in WordPress admin sidebar
 * so the admin can configure API settings.
 */

add_action( 'admin_menu', 'wp_api_products_add_admin_menu' );

function wp_api_products_add_admin_menu() {

    add_menu_page(
        'API Products Settings',       // Page title
        'API Products',                // Menu title
        'manage_options',              // Capability required
        'wp-api-products',             // Menu slug
        'wp_api_products_settings_page', // Callback to render page
        'dashicons-admin-generic',     // Icon
        25                              // Position
    );
}

/**
 * ==================================================
 * SECTION 2: SETTINGS PAGE UI
 * ==================================================
 * Displays the settings form in WordPress admin.
 * WordPress handles saving via options.php.
 */

function wp_api_products_settings_page() {
    ?>
    <div class="wrap">
        <h1>API Products Settings</h1>

        <form method="post" action="options.php">
            <?php
            // Output security fields for the settings group
            settings_fields( 'wp_api_products_settings' );

            // Output all settings sections and fields
            do_settings_sections( 'wp-api-products' );

            // Save button
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * ==================================================
 * SECTION 3: REGISTER SETTINGS & FIELDS
 * ==================================================
 * Registers options and creates input fields
 * using the WordPress Settings API.
 */

add_action( 'admin_init', 'wp_api_products_register_settings' );

function wp_api_products_register_settings() {

    // ---- Register stored options ----
    register_setting( 'wp_api_products_settings', 'wp_api_products_api_url' );
    register_setting( 'wp_api_products_settings', 'wp_api_products_username' );
    register_setting( 'wp_api_products_settings', 'wp_api_products_password' );

    // ---- Create a settings section ----
    add_settings_section(
        'wp_api_products_main_section',
        'API Configuration',
        null,
        'wp-api-products'
    );

    // ---- Add individual fields ----
    add_settings_field(
        'wp_api_products_api_url',
        'API Base URL',
        'wp_api_products_api_url_field',
        'wp-api-products',
        'wp_api_products_main_section'
    );

    add_settings_field(
        'wp_api_products_username',
        'API Username',
        'wp_api_products_username_field',
        'wp-api-products',
        'wp_api_products_main_section'
    );

    add_settings_field(
        'wp_api_products_password',
        'API Password',
        'wp_api_products_password_field',
        'wp-api-products',
        'wp_api_products_main_section'
    );
}

/**
 * ==================================================
 * SECTION 4: SETTINGS FIELD CALLBACKS
 * ==================================================
 * Each function renders one input field.
 */

function wp_api_products_api_url_field() {
    $value = get_option( 'wp_api_products_api_url', '' );
    echo '<input type="text" class="regular-text" name="wp_api_products_api_url" value="' . esc_attr( $value ) . '">';
}

function wp_api_products_username_field() {
    $value = get_option( 'wp_api_products_username', '' );
    echo '<input type="text" class="regular-text" name="wp_api_products_username" value="' . esc_attr( $value ) . '">';
}

function wp_api_products_password_field() {
    $value = get_option( 'wp_api_products_password', '' );
    echo '<input type="password" class="regular-text" name="wp_api_products_password" value="' . esc_attr( $value ) . '">';
}

/**
 * ==================================================
 * SECTION 5: AUTHENTICATION (JWT LOGIN)
 * ==================================================
 * Logs into the Node.js API and stores JWT token
 * inside WordPress options for reuse.
 */

/**
 * ==================================================
 * JWT AUTHENTICATION
 * ==================================================
 * Logs into the external Node.js API
 * and returns a clear success / failure result.
 */

function wp_api_products_get_jwt_token() {

    // Load centralized error messages
    $messages = wp_api_products_error_messages();

    // STEP 1: Read saved API settings
    $api_url  = get_option( 'wp_api_products_api_url' );
    $username = get_option( 'wp_api_products_username' );
    $password = get_option( 'wp_api_products_password' );

    // STEP 2: Check if settings are missing
    if ( empty( $api_url ) || empty( $username ) || empty( $password ) ) {
        return array(
            'success' => false,
            'message' => $messages['missing_settings'],
        );
    }

    // STEP 3: Send login request to API
    $response = wp_remote_post(
        $api_url . '/login',
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(
                array(
                    'username' => $username,
                    'password' => $password,
                )
            ),
            'timeout' => 15,
        )
    );

    // STEP 4: Handle network-level error
    if ( is_wp_error( $response ) ) {

    // Store readable error for admin
    update_option(
        'wp_api_products_last_error',
        'Unable to connect to API server. Please ensure the Node.js service is running.'
    );

    // Log technical error for developers
    error_log( 'WP API Products: JWT login failed. ' . $response->get_error_message() );

    return array(
        'success' => false,
        'message' => $messages['api_unreachable'],
    );
}



    // STEP 5: Decode API response
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // STEP 6: Validate token existence
    if ( empty( $body['token'] ) ) {
        return array(
            'success' => false,
            'message' => $messages['auth_failed'],
        );
    }

    // STEP 7: Save JWT token in WordPress
    update_option( 'wp_api_products_jwt', $body['token'] );

    // STEP 8: Return success result
    return array(
        'success' => true,
        'token'   => $body['token'],
    );
}


/**
 * ==================================================
 * SECTION 6: FETCH PRODUCTS FROM API
 * ==================================================
 * Uses stored JWT token to access protected endpoint.
 */

function wp_api_products_fetch_products() {

    $api_url = get_option( 'wp_api_products_api_url' );
    $token   = get_option( 'wp_api_products_jwt' );

    if ( empty( $api_url ) || empty( $token ) ) {
        return false;
    }

    $response = wp_remote_get(
        $api_url . '/products',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {

    // Log detailed error for developers
    error_log( 'WP API Products: Product fetch failed. ' . $response->get_error_message() );

    return array(
        'success' => false,
        'message' => $messages['api_unreachable'],
    );
}


    return json_decode( wp_remote_retrieve_body( $response ), true );
}

/**
 * ==================================================
 * SECTION 7: FRONTEND SHORTCODE
 * ==================================================
 * Displays products using [api_products].
 */

function wp_api_products_shortcode() {

    $products = wp_api_products_fetch_products();

    if ( ! $products || empty( $products['data'] ) ) {
        return '<p>Products are currently unavailable.</p>';
    }

    ob_start();
    ?>
    <div class="wp-api-products">
        <ul>
            <?php foreach ( $products['data'] as $product ) : ?>
                <li>
                    <strong><?php echo esc_html( $product['name'] ); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'api_products', 'wp_api_products_shortcode' );


/**
 * ==================================================
 * ADMIN NOTICE FOR CONFIGURATION ISSUES
 * ==================================================
 * Shows a warning to admins if API settings are missing.
 */

add_action( 'admin_notices', 'wp_api_products_admin_notice_missing_settings' );

function wp_api_products_admin_notice_missing_settings() {

    // Only show to admins
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

        // Check if there is a stored API error
    $last_error = get_option( 'wp_api_products_last_error' );

    if ( ! empty( $last_error ) ) {

        echo '<div class="notice notice-error">';
        echo '<p><strong>WP API Products Error:</strong> ' . esc_html( $last_error ) . '</p>';
        echo '</div>';

        // Do not show other notices if a real error exists
        return;
    }


    // Read required settings
    $api_url  = get_option( 'wp_api_products_api_url' );
    $username = get_option( 'wp_api_products_username' );
    $password = get_option( 'wp_api_products_password' );

    // If everything is set, do nothing
    if ( ! empty( $api_url ) && ! empty( $username ) && ! empty( $password ) ) {
        return;
    }

    // Show warning notice
    echo '<div class="notice notice-warning">';
    echo '<p><strong>WP API Products:</strong> API configuration is incomplete. Please go to <em>API Products</em> settings and fill all required fields.</p>';
    echo '</div>';
}

