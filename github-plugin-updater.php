<?php
if ( ! class_exists( 'GitHub_Plugin_Updater' ) ) {
	class GitHub_Plugin_Updater {
		private $plugin_slug;
		private $plugin_data;
		private $github_api_result;
		private $plugin_file;
		private $github_username;
		private $github_repo;

		public function __construct( $plugin_file, $github_username, $github_repo ) {
			$this->plugin_file   = $plugin_file;
			$this->plugin_slug   = plugin_basename( $plugin_file );
			$this->github_username = $github_username;
			$this->github_repo   = $github_repo;

			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
			add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 10, 3 );
			add_filter( 'upgrader_post_install', [ $this, 'after_install' ], 10, 3 );
		}

		public function check_for_update( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$github_api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
			$response = wp_remote_get( $github_api_url );

			if ( is_wp_error( $response ) ) {
				return $transient;
			}

			$release = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! isset( $release->tag_name ) ) {
				return $transient;
			}

			$new_version   = $release->tag_name;
			$plugin_version = $this->get_plugin_version();

			if ( version_compare( $new_version, $plugin_version, '>' ) ) {
				$transient->response[ $this->plugin_slug ] = (object) [
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_slug,
					'new_version' => $new_version,
					'url'         => $release->html_url,
					'package'     => $release->assets[0]->browser_download_url ?? $release->zipball_url,
				];
			}

			return $transient;
		}

		public function plugin_popup( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || $args->slug !== $this->plugin_slug ) {
				return $result;
			}

			return (object) [
				'name'          => $this->plugin_data['Name'],
				'slug'          => $this->plugin_slug,
				'version'       => $this->get_plugin_version(),
				'author'        => $this->plugin_data['Author'],
				'homepage'      => $this->plugin_data['PluginURI'],
				'download_link' => $this->github_api_result->zipball_url ?? '',
			];
		}

		private function get_plugin_version() {
			if ( ! $this->plugin_data ) {
				$this->plugin_data = get_plugin_data( $this->plugin_file );
			}
			return $this->plugin_data['Version'];
		}

		public function after_install( $response, $hook_extra, $result ) {
			global $wp_filesystem;
			$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->plugin_slug );
			$wp_filesystem->move( $result['destination'], $plugin_folder );
			$result['destination'] = $plugin_folder;
			return $result;
		}
	}
}
