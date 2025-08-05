<?php
/**
 * Plugin Name: Email Webhook Filter
 * Description: Sends email details as a JSON payload via a webhook if email subject or body matches specified regexp patterns.
 * Author: Limit Login Attempts Reloaded
 * Author URI: https://www.limitloginattempts.com/
 * Text Domain: email-webhook-filter
 * Version: 1.0.0
 *
 * @package Email_Webhook_Filter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Email_Webhook_Filter', false ) ) {

    /**
     * Main plugin class.
     */
    class Email_Webhook_Filter {

        /**
         * Constructor.
         */
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'phpmailer_init', array( $this, 'check_email_and_trigger_webhook' ) );
        }

        /**
         * Add plugin settings page.
         */
        public function add_plugin_menu() {
			if ( current_user_can( 'manage_options' ) ) {
				add_options_page(
					__( 'Email Webhook Filter Settings', 'email-webhook-filter' ),
					__( 'Email Webhook Filter', 'email-webhook-filter' ),
					'manage_options',
					'email-webhook-filter',
					array( $this, 'render_settings_page' )
				);
			}
        }

        /**
         * Register plugin settings.
         */
        public function register_settings() {
            register_setting(
                'email_webhook_filter_settings_group',
                'email_webhook_filter_settings',
                array( $this, 'sanitize_settings' )
            );

            add_settings_section(
                'email_webhook_filter_main_section',
                __( 'Main Settings', 'email-webhook-filter' ),
                '__return_false',
                'email-webhook-filter'
            );

            add_settings_field(
                'webhook_url',
                __( 'Webhook URL', 'email-webhook-filter' ),
                array( $this, 'webhook_url_field' ),
                'email-webhook-filter',
                'email_webhook_filter_main_section'
            );

            add_settings_field(
                'triggering_patterns',
                __( 'Triggering Patterns', 'email-webhook-filter' ),
                array( $this, 'triggering_patterns_field' ),
                'email-webhook-filter',
                'email_webhook_filter_main_section'
            );

            add_settings_field(
                'auth_type',
                __( 'Authentication Type', 'email-webhook-filter' ),
                array( $this, 'auth_type_field' ),
                'email-webhook-filter',
                'email_webhook_filter_main_section'
            );

            add_settings_field(
                'security_key',
                __( 'Security Key', 'email-webhook-filter' ),
                array( $this, 'security_key_field' ),
                'email-webhook-filter',
                'email_webhook_filter_main_section'
            );
        }

        /**
         * Sanitize settings.
         *
         * @param array $input Input settings.
         * @return array
         */
        public function sanitize_settings( $input ) {
            $sanitized = array();

            if ( isset( $input['webhook_url'] ) && ! empty( $input['webhook_url'] ) ) {
                $sanitized['webhook_url'] = esc_url_raw( $input['webhook_url'] );
            }

            if ( isset( $input['triggering_patterns'] ) ) {
                $lines = preg_split( '/\r\n|\r|\n/', $input['triggering_patterns'] );
                $valid = array();

                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( '' === $line ) {
                        continue;
                    }
                    if ( $this->is_valid_regexp( $line ) ) {
                        $valid[] = $line;
                    }
                }
                $sanitized['triggering_patterns'] = implode( PHP_EOL, $valid );
            }

            $sanitized['auth_type'] = ( isset( $input['auth_type'] ) && in_array( $input['auth_type'], array( 'header', 'body' ), true ) ) ? $input['auth_type'] : 'header';

            if ( isset( $input['auth_field'] ) ) {
                $sanitized['auth_field'] = sanitize_text_field( $input['auth_field'] );
            }

            if ( isset( $input['security_key'] ) ) {
                $sanitized['security_key'] = sanitize_text_field( $input['security_key'] );
            }

            return $sanitized;
        }

        /**
         * Validate a regular expression.
         *
         * @param string $pattern Regexp to test.
         * @return bool
         */
		private function is_valid_regexp( $pattern ) {
			preg_match( $pattern, '' );
			return PREG_NO_ERROR === preg_last_error();
		}

        /**
         * Get merged plugin settings with defaults.
         *
         * @return array
         */
        private function get_settings() {
            $defaults = array(
                'webhook_url'         => '',
                'triggering_patterns' => '',
                'auth_type'           => 'header',
                'auth_field'          => '',
                'security_key'        => '',
            );

            $saved = get_option( 'email_webhook_filter_settings' );
            return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
        }

		private function matches_patterns( $subject, $body, $patterns_raw ) {
			$patterns = preg_split( '/\r\n|\r|\n/', $patterns_raw );

			foreach ( $patterns as $pattern ) {
				$pattern = trim( $pattern );
				if ( '' === $pattern || ! $this->is_valid_regexp( $pattern ) ) {
					continue;
				}

				if ( preg_match( $pattern, $subject ) || preg_match( $pattern, $body ) ) {
					return true;
				}
			}

			return false;
		}

        /**
         * Send webhook payload.
         *
         * @param array $payload  JSON payload.
         * @param array $settings Plugin settings.
         * @return bool
         */
        private function send_webhook( $payload, $settings ) {
            $auth_type    = $settings['auth_type'];
            $auth_field   = $settings['auth_field'];
            $security_key = $settings['security_key'];

            if ( ! empty( $security_key ) && ! empty( $auth_field ) && 'body' === $auth_type ) {
                $payload[ $auth_field ] = $security_key;
            }

            $args = array(
                'body'    => wp_json_encode( $payload ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 5,
            );

            if ( ! empty( $security_key ) && ! empty( $auth_field ) && 'header' === $auth_type ) {
                $args['headers'][ $auth_field ] = $security_key;
            }

            $response = wp_remote_post( $settings['webhook_url'], $args );

            if ( is_wp_error( $response ) ) {
                if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log( '[EmailWebhookFilter] Webhook error: ' . $response->get_error_message() );
                }
                return false;
            }

            return true;
        }

        /**
         * Hook: Check email content and send webhook if it matches.
         *
         * @param PHPMailer $phpmailer PHPMailer instance.
         */
        public function check_email_and_trigger_webhook( $phpmailer ) {
            $settings = $this->get_settings();

            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            $subject_line = isset( $phpmailer->Subject ) ? $phpmailer->Subject : '';

            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            $body_content = isset( $phpmailer->Body ) ? $phpmailer->Body : '';

			$patterns_raw = isset( $settings['triggering_patterns'] ) ? $settings['triggering_patterns'] : '';

			if ( ! $this->matches_patterns( $subject_line, $body_content, $patterns_raw ) ) {
				return;
			}

			$payload = array(
				'subject' => $subject_line,
				'body'    => $body_content,
			);

			$this->send_webhook( $payload, $settings );
        }

        /**
         * Render settings page.
         */
        public function render_settings_page() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Email Webhook Filter Settings', 'email-webhook-filter' ); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'email_webhook_filter_settings_group' );
                    do_settings_sections( 'email-webhook-filter' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        /**
         * Webhook URL input field.
         */
        public function webhook_url_field() {
            $options     = get_option( 'email_webhook_filter_settings' );
            $webhook_url = isset( $options['webhook_url'] ) ? $options['webhook_url'] : '';
            echo '<input type="text" class="regular-text" name="email_webhook_filter_settings[webhook_url]" value="' . esc_attr( $webhook_url ) . '">';
        }

        /**
         * Triggering patterns input field.
         */
        public function triggering_patterns_field() {
            $options  = get_option( 'email_webhook_filter_settings' );
            $patterns = isset( $options['triggering_patterns'] ) ? $options['triggering_patterns'] : '';
            echo '<textarea name="email_webhook_filter_settings[triggering_patterns]" rows="5" cols="50">' . esc_textarea( $patterns ) . '</textarea>';
            echo '<p class="description">' . esc_html__( 'Enter one regexp per line.', 'email-webhook-filter' ) . '</p>';
        }

        /**
         * Authentication type input field.
         */
        public function auth_type_field() {
            $options     = get_option( 'email_webhook_filter_settings' );
            $auth_type   = isset( $options['auth_type'] ) ? $options['auth_type'] : 'header';
            $auth_field  = isset( $options['auth_field'] ) ? $options['auth_field'] : '';

            echo '<select name="email_webhook_filter_settings[auth_type]">';
            echo '<option value="header"' . selected( $auth_type, 'header', false ) . '>' . esc_html__( 'Header', 'email-webhook-filter' ) . '</option>';
            echo '<option value="body"' . selected( $auth_type, 'body', false ) . '>' . esc_html__( 'Request body (JSON)', 'email-webhook-filter' ) . '</option>';
            echo '</select>';
            echo '&nbsp;';
            echo '<input type="text" name="email_webhook_filter_settings[auth_field]" placeholder="' . esc_attr__( 'Field Name', 'email-webhook-filter' ) . '" value="' . esc_attr( $auth_field ) . '">';
        }

        /**
         * Security key input field.
         */
        public function security_key_field() {
            $options      = get_option( 'email_webhook_filter_settings' );
            $security_key = isset( $options['security_key'] ) ? $options['security_key'] : '';
            echo '<input type="text" class="regular-text" name="email_webhook_filter_settings[security_key]" value="' . esc_attr( $security_key ) . '">';
        }
    }

    new Email_Webhook_Filter();
}