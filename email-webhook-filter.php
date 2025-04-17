<?php
/*
Plugin Name: Email Webhook Filter
Description: Sends email details as a JSON payload via a webhook if email subject or body matches specified regexp patterns.
Author: Limit Login Attempts Reloaded
Author URI: https://www.limitloginattempts.com/
Text Domain: email-webhook-filter
Version: 1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Email_Webhook_Filter {

    public function __construct() {
        // Create settings page.
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Hook into the email sending process.
        add_action( 'phpmailer_init', array( $this, 'check_email_and_trigger_webhook' ) );
    }

    /**
     * Add the settings page to the WordPress admin menu.
     */
    public function add_plugin_menu() {
        add_options_page(
            'Email Webhook Filter Settings',
            'Email Webhook Filter',
            'manage_options',
            'email-webhook-filter',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register the plugin settings and settings fields.
     */
    public function register_settings() {
        register_setting( 'email_webhook_filter_settings_group', 'email_webhook_filter_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'email_webhook_filter_main_section',
            'Main Settings',
            null,
            'email-webhook-filter'
        );

        add_settings_field(
            'webhook_url',
            'Webhook URL',
            array( $this, 'webhook_url_callback' ),
            'email-webhook-filter',
            'email_webhook_filter_main_section'
        );

        add_settings_field(
            'triggering_patterns',
            'Triggering Patterns',
            array( $this, 'triggering_patterns_callback' ),
            'email-webhook-filter',
            'email_webhook_filter_main_section'
        );

        add_settings_field(
            'auth_type',
            'Authentication Type',
            array( $this, 'auth_type_callback' ),
            'email-webhook-filter',
            'email_webhook_filter_main_section'
        );

        add_settings_field(
            'security_key',
            'Security Key',
            array( $this, 'security_key_callback' ),
            'email-webhook-filter',
            'email_webhook_filter_main_section'
        );
    }

    /**
     * Sanitize settings input.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['webhook_url'] ) ) {
            $sanitized['webhook_url'] = esc_url_raw( $input['webhook_url'] );
        }

        if ( isset( $input['triggering_patterns'] ) ) {
            $sanitized['triggering_patterns'] = sanitize_textarea_field( $input['triggering_patterns'] );
        }

        if ( isset( $input['auth_type'] ) && in_array( $input['auth_type'], array( 'header', 'body' ), true ) ) {
            $sanitized['auth_type'] = $input['auth_type'];
        } else {
            $sanitized['auth_type'] = 'header';
        }

        if ( isset( $input['auth_field'] ) ) {
            $sanitized['auth_field'] = sanitize_text_field( $input['auth_field'] );
        }

        if ( isset( $input['security_key'] ) ) {
            $sanitized['security_key'] = sanitize_text_field( $input['security_key'] );
        }

        return $sanitized;
    }

    /**
     * Callback for the Webhook URL field.
     */
    public function webhook_url_callback() {
        $options = get_option( 'email_webhook_filter_settings' );
        $webhook_url = isset( $options['webhook_url'] ) ? $options['webhook_url'] : '';
        echo '<input type="text" name="email_webhook_filter_settings[webhook_url]" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
    }

    /**
     * Callback for the Triggering Patterns field.
     */
    public function triggering_patterns_callback() {
        $options = get_option( 'email_webhook_filter_settings' );
        $patterns = isset( $options['triggering_patterns'] ) ? $options['triggering_patterns'] : '';
        echo '<textarea name="email_webhook_filter_settings[triggering_patterns]" rows="5" cols="50">' . esc_textarea( $patterns ) . '</textarea>';
        echo '<p class="description">Enter one regexp per line.</p>';
    }

    /**
     * Callback for the Authentication Type field and adjacent field for the key’s field name.
     */
    public function auth_type_callback() {
        $options = get_option( 'email_webhook_filter_settings' );
        $auth_type = isset( $options['auth_type'] ) ? $options['auth_type'] : 'header';
        $auth_field = isset( $options['auth_field'] ) ? $options['auth_field'] : '';
        ?>
        <select name="email_webhook_filter_settings[auth_type]">
            <option value="header" <?php selected( $auth_type, 'header' ); ?>>Header</option>
            <option value="body" <?php selected( $auth_type, 'body' ); ?>>Request Body (JSON)</option>
        </select>
        &nbsp;
        <input type="text" name="email_webhook_filter_settings[auth_field]" value="<?php echo esc_attr( $auth_field ); ?>" placeholder="Field Name" />
        <?php
    }

    /**
     * Callback for the Security Key field.
     */
    public function security_key_callback() {
        $options = get_option( 'email_webhook_filter_settings' );
        $security_key = isset( $options['security_key'] ) ? $options['security_key'] : '';
        echo '<input type="text" name="email_webhook_filter_settings[security_key]" value="' . esc_attr( $security_key ) . '" class="regular-text" />';
    }

    /**
     * Output the settings page HTML.
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Email Webhook Filter Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'email_webhook_filter_settings_group' ); ?>
                <?php do_settings_sections( 'email-webhook-filter' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Hook into PHPMailer to check email content and trigger the webhook if needed.
     *
     * @param PHPMailer $phpmailer The PHPMailer instance.
     */
    public function check_email_and_trigger_webhook( $phpmailer ) {
        // Retrieve the saved settings.
        $settings = get_option( 'email_webhook_filter_settings' );
        if ( empty( $settings ) ) {
            return;
        }

        $webhook_url       = isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : '';
        $triggering_str    = isset( $settings['triggering_patterns'] ) ? $settings['triggering_patterns'] : '';
        $auth_type         = isset( $settings['auth_type'] ) ? $settings['auth_type'] : 'header';
        $auth_field        = isset( $settings['auth_field'] ) ? $settings['auth_field'] : '';
        $security_key      = isset( $settings['security_key'] ) ? $settings['security_key'] : '';

        // If no webhook URL or no triggering patterns are set, do nothing.
        if ( empty( $webhook_url ) || empty( $triggering_str ) ) {
            return;
        }

        // Get email subject and body.
        $subject = isset( $phpmailer->Subject ) ? $phpmailer->Subject : '';
        $body    = isset( $phpmailer->Body ) ? $phpmailer->Body : '';

        // Split patterns on new lines.
        $patterns = preg_split( '/\r\n|\r|\n/', $triggering_str );
        $matched  = false;

        if ( is_array( $patterns ) ) {
            foreach ( $patterns as $pattern ) {
                $pattern = trim( $pattern );
                if ( empty( $pattern ) ) {
                    continue;
                }
                // Use error suppression (@) in case an invalid regexp is provided.
                if ( @preg_match( $pattern, $subject ) || @preg_match( $pattern, $body ) ) {
                    $matched = true;
                    break;
                }
            }
        }

        // If none of the patterns match, exit.
        if ( ! $matched ) {
            return;
        }

        // Prepare the JSON payload.
        $payload = array(
            'subject' => $subject,
            'body'    => $body,
        );

        // If a security key is provided, add it according to the selected authentication type.
        if ( ! empty( $security_key ) && ! empty( $auth_field ) ) {
            if ( 'body' === $auth_type ) {
                $payload[ $auth_field ] = $security_key;
            }
        }

        // Prepare the request arguments.
        $args = array(
            'body'    => json_encode( $payload ),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 5,
        );

        // If the authentication type is header, add the header with the security key.
        if ( ! empty( $security_key ) && ! empty( $auth_field ) && 'header' === $auth_type ) {
            $args['headers'][ $auth_field ] = $security_key;
        }

        // Send the webhook request.
        wp_remote_post( $webhook_url, $args );
    }
}

// Initialize the plugin.
new Email_Webhook_Filter();
