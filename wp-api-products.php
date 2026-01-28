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

function wp_api_products_get_jwt_token() {

    // ---- Read saved settings ----
    $api_url  = get_option( 'wp_api_products_api_url' );
    $username = get_option( 'wp_api_products_username' );
    $password = get_option( 'wp_api_products_password' );

    // ---- Basic validation ----
    if ( empty( $api_url ) || empty( $username ) || empty( $password ) ) {
        return false;
    }

    // ---- Send login request ----
    $response = wp_remote_post(
        $api_url . '/login',
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => json_encode(
                array(
                    'username' => $username,
                    'password' => $password,
                )
            ),
            'timeout' => 15,
        )
    );

    // ---- Handle request error ----
    if ( is_wp_error( $response ) ) {
        return false;
    }

    // ---- Decode response ----
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['token'] ) ) {
        return false;
    }

    // ---- Store token ----
    update_option( 'wp_api_products_jwt', $body['token'] );

    return $body['token'];
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
        return false;
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

