<?php
/**
 * Plugin Name: CodeWing Updater
 * Description: This plugin automates updates from a custom server for CodeWing.
 * Version: 1.0
 * Author: CodeWing
 * Author URI: https://your-website.com
 * License: GPL
 * Text Domain: codewing-updater
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CodeWing_Updater' ) ) {

    /**
     * Class CodeWing_Updater
     *
     * Handles the update checking functionality for the CodeWing plugin.
     */
    class CodeWing_Updater {

        /**
         * Plugin slug.
         *
         * @var string
         */
        public $plugin_slug;

        /**
         * Cache key.
         *
         * @var string
         */
        public $cache_key;

        /**
         * Constructor.
         */
        public function __construct() {
            $this->plugin_slug = 'codewing-updater';
            $this->cache_key = 'codewing_custom_upd';
            add_action( 'init', array( $this, 'init' ) );
        }

        /**
         * Initialize the plugin.
         */
        public function init() {
            // Check for updates
            add_filter( 'site_transient_update_plugins', array( $this, 'check_for_updates' ) );
            // Purge cache after update
            add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
            // Plugins API
            add_filter( 'plugins_api', array( $this, 'plugins_info' ), 20, 3 );
        }

        /**
         * Get the current plugin version.
         *
         * @return string The current version of the plugin.
         */
        public function get_version() {
            return '1.0'; // Update this version whenever necessary
        }

        /**
         * Check for updates securely.
         *
         * @param object $transient The plugin update transient.
         * @return object The modified plugin update transient.
         */
        public function check_for_updates( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $remote = $this->get_remote_update_info();

            if ( 
                $remote && 
                version_compare( $this->get_version(), $remote->version, '<' ) && 
                version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' ) && 
                version_compare( $remote->requires_php, PHP_VERSION, '<=' ) 
            ) {
                $res = new stdClass();
                $res->slug = $this->plugin_slug;
                $res->plugin = plugin_basename( __FILE__ );
                $res->new_version = sanitize_text_field( $remote->version );
                $res->tested = sanitize_text_field( $remote->tested );
                $res->package = esc_url( $remote->download_url );

                $transient->response[ $res->plugin ] = $res;
            }            

            return $transient;
        }

        /**
         * Get remote update information securely.
         *
         * @return object|false The decoded remote response or false on failure.
         */
        private function get_remote_update_info() {
            $remote = get_transient( $this->cache_key );

            if ( false === $remote ) {
                $response = wp_remote_get(
                    'http://sagar-n3jr.wp1.site/wp-content/uploads/2024/10/updater-info.json',
                    array(
                        'timeout' => 10,
                        'headers' => array(
                            'Accept' => 'application/json',
                        ),
                    )
                );

                if ( 
                    is_wp_error( $response ) || 
                    200 !== wp_remote_retrieve_response_code( $response ) || 
                    empty( wp_remote_retrieve_body( $response ) ) 
                ) {
                    return false;
                }

                $remote = json_decode( wp_remote_retrieve_body( $response ) );

                // Set transient cache for 24 hours
                set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );
            }

            return $remote;
        }

        /**
         * Plugins Info
         * 
         * @param mixed $res
         * @param mixed $action
         * @param mixed $args
         * @return object|false Plugins details.
         */
        public function plugins_info( $res, $action, $args ){
            // do nothing if you're not getting plugin information right now
			if( 'plugin_information' !== $action ) {
				return $res;
			}

			// do nothing if it is not our plugin
			if( $this->plugin_slug !== $args->slug ) {
				return $res;
			}

			// get remote Update info
			$remote = $this->get_remote_update_info();

			if( ! $remote ) {
				return $res;
			}

			$res = new stdClass();

			$res->name = $remote->name;
			$res->slug = $remote->slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = $remote->author;
			$res->author_profile = $remote->author_profile;
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = $remote->requires_php;
			$res->last_updated = $remote->last_updated;

			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
			);

			if( ! empty( $remote->banners ) ) {
				$res->banners = array(
					'low' => $remote->banners->low,
					'high' => $remote->banners->high
				);
			}

			return $res;

        }

        /**
         * Purge cache after an update.
         *
         * @param WP_Upgrader $upgrader The upgrader object.
         * @param array $options Update details.
         */
        public function purge_cache( $upgrader, $options ) {
            if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
                delete_transient( $this->cache_key );
            }
        }

    }

    // Initialize the plugin.
    new CodeWing_Updater();
}
