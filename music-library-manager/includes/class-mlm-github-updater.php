<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MLM_GitHub_Updater {
	private const REPOSITORY = 'cypcpycy/WP-Xmedia';
	private const SLUG = 'music-library-manager';
	private const CACHE_KEY = 'mlm_github_release';

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'http_request_args', array( $this, 'authorize_github_request' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}
		$release = $this->release();
		if ( is_wp_error( $release ) || empty( $release['version'] ) || ! version_compare( $release['version'], MLM_VERSION, '>' ) ) {
			return $transient;
		}
		$plugin = plugin_basename( MLM_FILE );
		$update = (object) array(
			'id' => 'https://github.com/' . self::REPOSITORY,
			'slug' => self::SLUG,
			'plugin' => $plugin,
			'new_version' => $release['version'],
			'url' => $release['html_url'],
			'package' => $release['package'],
			'tested' => '6.8',
			'requires_php' => '7.4',
		);
		$transient->response[ $plugin ] = $update;
		return $transient;
	}

	public function plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}
		return (object) array(
			'name' => 'WP-Xmedia 音乐资料库管理器',
			'slug' => self::SLUG,
			'version' => $release['version'],
			'author' => '<a href="https://github.com/cypcpycy">cypcpycy</a>',
			'homepage' => 'https://github.com/' . self::REPOSITORY,
			'requires' => '6.2',
			'requires_php' => '7.4',
			'tested' => '6.8',
			'last_updated' => $release['published_at'],
			'download_link' => $release['package'],
			'sections' => array(
				'description' => '<p>管理歌曲、导入媒体文件，并通过 APlayer、区块和短代码在 WordPress 中播放音乐。</p>',
				'changelog' => '<div class="mlm-release-notes">' . nl2br( esc_html( $release['body'] ) ) . '</div>',
			),
		);
	}

	public function authorize_github_request( array $args, string $url ): array {
		if ( 0 !== strpos( $url, 'https://api.github.com/repos/' . self::REPOSITORY . '/' ) ) {
			return $args;
		}
		$args['headers']['Accept'] = false !== strpos( $url, '/contents/' ) ? 'application/vnd.github.raw+json' : 'application/vnd.github+json';
		$args['headers']['X-GitHub-Api-Version'] = '2022-11-28';
		$args['headers']['User-Agent'] = 'WP-Xmedia/' . MLM_VERSION;
		return $args;
	}

	public function clear_cache_after_update( $upgrader, array $options ): void {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	private function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$url = 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest';
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return new WP_Error( 'mlm_github_release', 'GitHub 发布信息读取失败。' );
		}
		$version = ltrim( sanitize_text_field( $data['tag_name'] ), 'vV' );
		$filename = 'WP-Xmedia-' . $version . '.zip';
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( $filename === ( $asset['name'] ?? '' ) ) {
				$package = esc_url_raw( $asset['browser_download_url'] ?? '' );
				break;
			}
		}
		if ( ! $package ) {
			$package = 'https://api.github.com/repos/' . self::REPOSITORY . '/contents/dist/' . rawurlencode( $filename ) . '?ref=' . rawurlencode( (string) $data['tag_name'] );
		}
		$release = array(
			'version' => $version,
			'tag' => sanitize_text_field( $data['tag_name'] ),
			'html_url' => esc_url_raw( $data['html_url'] ?? 'https://github.com/' . self::REPOSITORY . '/releases' ),
			'package' => $package,
			'body' => sanitize_textarea_field( $data['body'] ?? '' ),
			'published_at' => sanitize_text_field( $data['published_at'] ?? '' ),
		);
		set_site_transient( self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}
}
