<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MLM_Plugin {
	private static $instance;
	private const POST_TYPE = 'mlm_track';
	private const TAXONOMY = 'mlm_music_category';
	private const PLAYLIST_TAXONOMY = 'mlm_playlist';
	private const OPTION = 'mlm_settings';
	private const META_KEYS = array( 'artist', 'album', 'audio_url', 'cover_url', 'lyrics', 'lyrics_url', 'source_url', 'duration' );

	public static function instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_track' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_data' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_action( 'wp_ajax_mlm_search_music', array( $this, 'ajax_search_music' ) );
		add_action( 'wp_ajax_mlm_album_songs', array( $this, 'ajax_album_songs' ) );
		add_action( 'wp_ajax_mlm_resolve_music', array( $this, 'ajax_resolve_music' ) );
		add_action( 'wp_ajax_mlm_import_music', array( $this, 'ajax_import_music' ) );
		add_action( 'wp_ajax_mlm_qq_status', array( $this, 'ajax_qq_status' ) );
		add_action( 'wp_ajax_mlm_qq_login_start', array( $this, 'ajax_qq_login_start' ) );
		add_action( 'wp_ajax_mlm_qq_login_poll', array( $this, 'ajax_qq_login_poll' ) );
		add_action( 'wp_ajax_mlm_qq_stream', array( $this, 'ajax_qq_stream' ) );
		add_action( 'wp_ajax_mlm_qq_lyrics', array( $this, 'ajax_qq_lyrics' ) );
		add_shortcode( 'music', array( $this, 'single_shortcode' ) );
		add_shortcode( 'music_list', array( $this, 'list_shortcode' ) );
		add_shortcode( 'music_playlist', array( $this, 'playlist_shortcode' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'allow_lyrics_mime' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'render_editor_hint' ) );
		add_filter( 'the_content', array( $this, 'append_player_to_track_page' ) );
	}

	public static function activate(): void {
		self::instance()->register_post_type();
		flush_rewrite_rules();
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name' => '音乐库', 'singular_name' => '歌曲', 'add_new' => '添加歌曲',
					'add_new_item' => '添加歌曲', 'edit_item' => '编辑歌曲', 'search_items' => '搜索音乐库',
				),
				'public' => true,
				'show_in_rest' => true,
				'menu_icon' => 'dashicons-format-audio',
				'supports' => array( 'title' ),
				'has_archive' => true,
				'rewrite' => array( 'slug' => 'music' ),
			)
		);
		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels' => array( 'name' => '音乐分类', 'singular_name' => '音乐分类', 'add_new_item' => '添加音乐分类' ),
				'public' => true,
				'show_admin_column' => false,
				'show_in_rest' => true,
				'hierarchical' => true,
				'rewrite' => array( 'slug' => 'music-category' ),
			)
		);
		register_taxonomy(
			self::PLAYLIST_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels' => array( 'name' => '音乐播放列表', 'singular_name' => '播放列表', 'add_new_item' => '新建播放列表', 'edit_item' => '编辑播放列表' ),
				'public' => false, 'show_ui' => true, 'show_admin_column' => true,
				'show_in_rest' => true, 'hierarchical' => false,
			)
		);
		$this->register_music_block();
	}

	private function register_music_block(): void {
		wp_register_script(
			'mlm-music-block',
			MLM_URL . 'assets/js/music-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-core-data', 'wp-server-side-render', 'wp-i18n' ),
			MLM_VERSION,
			true
		);
		wp_register_style( 'mlm-music-block-editor', MLM_URL . 'assets/css/music-block-editor.css', array( 'wp-edit-blocks' ), MLM_VERSION );
		register_block_type(
			'mlm/music-player',
			array(
				'api_version' => 3,
				'editor_script' => 'mlm-music-block',
				'editor_style' => 'mlm-music-block-editor',
				'attributes' => array(
					'trackId' => array( 'type' => 'integer', 'default' => 0 ),
					'showLyrics' => array( 'type' => 'boolean', 'default' => true ),
				),
				'render_callback' => array( $this, 'render_music_block' ),
			)
		);
		register_block_type(
			'mlm/music-playlist',
			array(
				'api_version' => 3,
				'editor_script' => 'mlm-music-block',
				'editor_style' => 'mlm-music-block-editor',
				'attributes' => array( 'playlistId' => array( 'type' => 'integer', 'default' => 0 ) ),
				'render_callback' => array( $this, 'render_playlist_block' ),
			)
		);
	}

	public function enqueue_block_editor_data(): void {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		$tracks = array();
		$track_map = array();
		foreach ( $posts as $post ) {
			$item = array(
				'id' => $post->ID, 'title' => get_the_title( $post ),
				'artist' => (string) get_post_meta( $post->ID, '_mlm_artist', true ),
				'album' => (string) get_post_meta( $post->ID, '_mlm_album', true ),
				'cover' => $this->attachment_url( $post->ID, 'cover' ),
				'audio' => $this->attachment_url( $post->ID, 'audio' ),
			);
			$tracks[] = $item; $track_map[ $post->ID ] = $item;
		}
		$playlists = array();
		$terms = get_terms( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$ids = get_objects_in_term( $term->term_id, self::PLAYLIST_TAXONOMY );
				$items = array();
				foreach ( $ids as $id ) { if ( isset( $track_map[ $id ] ) ) { $items[] = $track_map[ $id ]; } }
				$playlists[] = array( 'id' => $term->term_id, 'name' => $term->name, 'count' => count( $items ), 'tracks' => $items );
			}
		}
		wp_add_inline_script( 'mlm-music-block', 'window.mlmBlockData = ' . wp_json_encode( array( 'tracks' => $tracks, 'playlists' => $playlists ) ) . ';', 'before' );
	}

	public function render_music_block( array $attributes ): string {
		$track_id = absint( $attributes['trackId'] ?? 0 );
		if ( ! $track_id ) { return ''; }
		return $this->single_shortcode(
			array(
				'id' => $track_id,
				'lyrics' => empty( $attributes['showLyrics'] ) ? 'no' : 'yes',
			)
		);
	}

	public function render_playlist_block( array $attributes ): string {
		$playlist_id = absint( $attributes['playlistId'] ?? 0 );
		return $playlist_id ? $this->playlist_shortcode( array( 'id' => $playlist_id ) ) : '';
	}

	public function append_player_to_track_page( string $content ): string {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id || has_shortcode( $content, 'music' ) || has_block( 'mlm/music-player', $content ) ) {
			return $content;
		}
		$player = $this->single_shortcode( array( 'id' => $post_id, 'lyrics' => 'yes' ) );
		return $player . $content;
	}

	public function add_meta_boxes(): void {
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'authordiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'postcustom', self::POST_TYPE, 'normal' );
		remove_meta_box( 'commentstatusdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'commentsdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'trackbacksdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'revisionsdiv', self::POST_TYPE, 'normal' );
		remove_meta_box( 'postimagediv', self::POST_TYPE, 'side' );
		add_meta_box( 'mlm_search', '搜索歌曲资料', array( $this, 'render_search_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'mlm_track_data', '歌曲资料', array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function disable_block_editor( bool $use_block_editor, string $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : $use_block_editor;
	}

	public function title_placeholder( string $placeholder, WP_Post $post ): string {
		return self::POST_TYPE === $post->post_type ? '输入歌曲名称' : $placeholder;
	}

	public function render_editor_hint( WP_Post $post ): void {
		if ( self::POST_TYPE !== $post->post_type ) { return; }
		echo '<p class="mlm-editor-hint">填写必要歌曲资料即可。远程文件可在保存时自动导入媒体库，也可以直接点击“媒体库”选择已有文件。</p>';
	}

	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'mlm_save_track', 'mlm_nonce' );
		$cover = get_post_meta( $post->ID, '_mlm_cover_url', true );
		$source_url = get_post_meta( $post->ID, '_mlm_source_url', true );
		echo '<div class="mlm-media-overview"><div class="mlm-overview-cover"><strong>歌曲封面</strong><div id="mlm-cover-preview">';
		if ( $cover ) { printf( '<img src="%s" alt="">', esc_url( $cover ) ); } else { echo '<span class="mlm-empty-cover dashicons dashicons-format-image"></span>'; }
		echo '</div></div>';
		$this->render_attachment_status( $post->ID );
		echo '</div>';
		printf( '<input type="hidden" id="mlm_source_url" name="mlm[source_url]" value="%s">', esc_attr( $source_url ) );
		$fields = array(
			'artist' => array( '歌手', 'text' ), 'album' => array( '专辑', 'text' ),
			'audio_url' => array( '音频文件 URL', 'url' ), 'cover_url' => array( '封面图片 URL', 'url' ),
			'duration' => array( '时长（毫秒）', 'number' ),
		);
		echo '<div class="mlm-fields">';
		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, '_mlm_' . $key, true );
			printf( '<p><label for="mlm_%1$s"><strong>%2$s</strong></label><span class="mlm-input-row"><input class="widefat" type="%3$s" id="mlm_%1$s" name="mlm[%1$s]" value="%4$s">%5$s</span></p>', esc_attr( $key ), esc_html( $field[0] ), esc_attr( $field[1] ), esc_attr( $value ), in_array( $key, array( 'audio_url', 'cover_url', 'lyrics_url' ), true ) ? '<button type="button" class="button mlm-media-button" data-target="mlm_' . esc_attr( $key ) . '" data-type="' . esc_attr( $key ) . '">媒体库</button>' : '' );
		}
		$settings = $this->settings();
		printf( '<p class="mlm-import-option"><label><input type="checkbox" name="mlm[import_assets]" value="1" %s> 保存时自动把远程媒体文件导入 WordPress 媒体库</label></p>', checked( ! empty( $settings['auto_import'] ), true, false ) );
		echo '</div>';
	}

	public function render_search_box(): void {
		echo '<p>在弹窗中搜索、试听并导入完整歌曲资料。</p><button type="button" class="button button-primary button-large" id="mlm-open-search">打开音乐搜索</button>';
		echo '<div id="mlm-search-modal" class="mlm-modal" hidden><div class="mlm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mlm-modal-title"><header class="mlm-modal-header"><div><h2 id="mlm-modal-title">搜索并添加歌曲</h2><span id="mlm-qq-state">正在检查 QQ 音乐登录状态…</span></div><button type="button" class="mlm-modal-close" aria-label="关闭">×</button></header><div class="mlm-modal-body">';
		echo '<div class="mlm-qq-status"><button type="button" class="button" id="mlm-qq-login">QQ 扫码登录</button></div><div id="mlm-qq-panel" hidden><img id="mlm-qq-qr" alt="QQ 登录二维码"><p id="mlm-qq-hint">扫码后请在手机上确认。</p></div>';
		echo '<div class="mlm-search-controls"><input class="widefat" id="mlm-search-term" type="search" placeholder="输入歌曲名或歌手，例如：周杰伦 七里香"><select id="mlm-quality"><option value="standard">标准 MP3 128k</option><option value="hq">高品质 MP3 320k</option><option value="lossless">无损 FLAC</option><option value="master">臻品母带</option></select><button type="button" class="button button-primary" id="mlm-search-button">搜索</button><span class="spinner" id="mlm-search-spinner"></span></div><div id="mlm-search-message"></div><div id="mlm-search-results"></div><nav id="mlm-pagination" class="mlm-pagination" hidden><button type="button" class="button" id="mlm-prev-page">上一页</button><strong id="mlm-current-page">第 1 页</strong><button type="button" class="button" id="mlm-next-page">下一页</button></nav></div></div></div>';
	}

	public function save_track( int $post_id ): void {
		if ( ! isset( $_POST['mlm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mlm_nonce'] ) ), 'mlm_save_track' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$data = isset( $_POST['mlm'] ) && is_array( $_POST['mlm'] ) ? wp_unslash( $_POST['mlm'] ) : array();
		foreach ( self::META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) { continue; }
			$value = $data[ $key ] ?? '';
			if ( in_array( $key, array( 'audio_url', 'cover_url', 'lyrics_url', 'source_url' ), true ) ) { $value = esc_url_raw( $value ); }
			elseif ( 'lyrics' === $key ) { $value = sanitize_textarea_field( $value ); }
			elseif ( 'duration' === $key ) { $value = absint( $value ); }
			else { $value = sanitize_text_field( $value ); }
			update_post_meta( $post_id, '_mlm_' . $key, $value );
		}
		if ( ! empty( $data['import_assets'] ) ) {
			$this->import_assets( $post_id, $data );
		}
		do_action( 'mlm_track_saved', $post_id, $data );
	}

	private function render_attachment_status( int $post_id ): void {
		$labels = array( 'cover' => '封面附件', 'audio' => '音频附件' );
		echo '<div class="mlm-media-status"><strong>媒体库状态</strong><ul>';
		foreach ( $labels as $type => $label ) {
			$id = absint( get_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', true ) );
			if ( $id ) {
				printf( '<li>%s：<a href="%s">#%d %s</a></li>', esc_html( $label ), esc_url( get_edit_post_link( $id ) ), $id, esc_html( get_the_title( $id ) ) );
			} else {
				printf( '<li>%s：尚未导入</li>', esc_html( $label ) );
			}
		}
		echo '</ul></div>';
	}

	private function import_assets( int $post_id, array $data ): void {
		$album_title = sanitize_text_field( $data['album'] ?? '' ) ?: get_the_title( $post_id );
		$assets = array(
			'cover' => array( 'url' => $data['cover_url'] ?? '', 'title' => $album_title ),
			'audio' => array( 'url' => $data['audio_url'] ?? '', 'title' => get_the_title( $post_id ) ),
			'lyrics' => array( 'url' => $data['lyrics_url'] ?? '', 'title' => get_the_title( $post_id ) ),
		);
		$errors = array();
		foreach ( $assets as $type => $asset ) {
			$url = esc_url_raw( apply_filters( 'mlm_remote_asset_url', $asset['url'], $type, $post_id ) );
			if ( ! $url ) { continue; }
			$local_id = attachment_url_to_postid( $url );
			if ( $local_id ) {
				update_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', $local_id );
				update_post_meta( $post_id, '_mlm_' . $type . '_url', esc_url_raw( wp_get_attachment_url( $local_id ) ) );
				if ( 'cover' === $type ) { set_post_thumbnail( $post_id, $local_id ); }
				continue;
			}
			$current_id = absint( get_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', true ) );
			if ( $current_id ) {
				$current_url = (string) wp_get_attachment_url( $current_id );
				$original_url = (string) get_post_meta( $current_id, '_mlm_original_url', true );
				if ( $url === $current_url || $url === $original_url ) { continue; }
			}
			$attachment_id = $this->sideload_asset( $url, $post_id, $asset['title'], $type );
			if ( is_wp_error( $attachment_id ) ) {
				$errors[] = sprintf( '%s：%s', $asset['title'], $attachment_id->get_error_message() );
				continue;
			}
			update_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', $attachment_id );
			update_post_meta( $attachment_id, '_mlm_original_url', $url );
			$local_url = wp_get_attachment_url( $attachment_id );
			update_post_meta( $post_id, '_mlm_' . $type . '_url', esc_url_raw( $local_url ) );
			if ( 'cover' === $type ) { set_post_thumbnail( $post_id, $attachment_id ); }
			if ( 'lyrics' === $type && empty( $data['lyrics'] ) ) { $this->load_lyrics_text( $post_id, $attachment_id ); }
			do_action( 'mlm_asset_imported', $attachment_id, $post_id, $type, $url );
		}
		if ( $errors ) {
			set_transient( 'mlm_import_errors_' . get_current_user_id(), $errors, MINUTE_IN_SECONDS );
		}
	}

	private function sideload_asset( string $url, int $post_id, string $title, string $type ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$candidate_urls = array( $url );
		if ( 'cover' === $type && preg_match( '/T002R\d+x\d+M000/', $url ) ) {
			$candidate_urls = array( preg_replace( '/T002R\d+x\d+M000/', 'T002M000', $url ) );
			foreach ( array( 1500, 800, 500, 300 ) as $size ) {
				$candidate_urls[] = preg_replace( '/T002R\d+x\d+M000/', 'T002R' . $size . 'x' . $size . 'M000', $url );
			}
			$candidate_urls[] = $url;
			$candidate_urls = array_values( array_unique( $candidate_urls ) );
		}
		$tmp = null; $download_error = null;
		foreach ( $candidate_urls as $candidate_url ) {
			$tmp = download_url( $candidate_url, 30 );
			if ( ! is_wp_error( $tmp ) ) { $url = $candidate_url; break; }
			$download_error = $tmp;
		}
		if ( ! $tmp || is_wp_error( $tmp ) ) { return $download_error ?: new WP_Error( 'mlm_cover_download', '媒体文件下载失败。' ); }
		$settings = $this->settings();
		$limit_mb = 'cover' === $type ? $settings['max_image_mb'] : ( 'lyrics' === $type ? $settings['max_lyrics_mb'] : $settings['max_audio_mb'] );
		$max_size = (int) apply_filters( 'mlm_max_remote_asset_size', (int) $limit_mb * MB_IN_BYTES, $type, $post_id );
		if ( filesize( $tmp ) > $max_size ) { wp_delete_file( $tmp ); return new WP_Error( 'mlm_file_too_large', '远程文件超过允许大小。' ); }
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$extension = sanitize_key( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! $extension ) { $extension = 'cover' === $type ? 'jpg' : ( 'lyrics' === $type ? 'lrc' : 'mp3' ); }
		$name = sanitize_file_name( $title ) . '.' . $extension;
		$file = array( 'name' => $name, 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, $post_id, $title, array( 'post_title' => $title ) );
		if ( is_wp_error( $id ) ) { wp_delete_file( $tmp ); }
		return $id;
	}

	private function load_lyrics_text( int $post_id, int $attachment_id ): void {
		$file = get_attached_file( $attachment_id );
		if ( $file && is_readable( $file ) && filesize( $file ) <= 2 * MB_IN_BYTES ) {
			$content = file_get_contents( $file );
			if ( false !== $content ) { update_post_meta( $post_id, '_mlm_lyrics', sanitize_textarea_field( $content ) ); }
		}
	}

	public function allow_lyrics_mime( array $mimes ): array {
		$mimes['lrc'] = 'text/plain';
		return $mimes;
	}

	public function admin_notices(): void {
		$key = 'mlm_import_errors_' . get_current_user_id();
		$errors = get_transient( $key );
		if ( ! $errors ) { return; }
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p><strong>部分媒体导入失败：</strong></p><ul>';
		foreach ( $errors as $error ) { printf( '<li>%s</li>', esc_html( $error ) ); }
		echo '</ul></div>';
	}

	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) { return; }
		wp_enqueue_media();
		wp_enqueue_style( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.css', array(), '1.10.1' );
		wp_enqueue_style( 'mlm-admin', MLM_URL . 'assets/css/admin.css', array(), MLM_VERSION );
		wp_enqueue_style( 'mlm-admin-music', MLM_URL . 'assets/css/admin-music.css', array( 'mlm-admin' ), MLM_VERSION );
		wp_enqueue_script( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
		wp_enqueue_script( 'mlm-admin', MLM_URL . 'assets/js/admin.js', array( 'jquery', 'wp-data', 'mlm-aplayer-admin' ), MLM_VERSION, true );
		wp_localize_script( 'mlm-admin', 'mlmAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mlm_search_music' ), 'postId' => get_the_ID() ) );
	}

	public function admin_menu(): void {
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '音乐库设置', '设置', 'manage_options', 'mlm-settings', array( $this, 'render_settings_page' ) );
	}

	public function register_settings(): void {
		register_setting( 'mlm_settings_group', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_settings' ), 'default' => $this->default_settings() ) );
	}

	public function sanitize_settings( array $input ): array {
		return array(
			'api_base' => esc_url_raw( untrailingslashit( $input['api_base'] ?? '' ) ),
			'auto_import' => empty( $input['auto_import'] ) ? 0 : 1,
			'max_image_mb' => min( 50, max( 1, absint( $input['max_image_mb'] ?? 10 ) ) ),
			'max_audio_mb' => min( 500, max( 1, absint( $input['max_audio_mb'] ?? 50 ) ) ),
			'max_lyrics_mb' => min( 10, max( 1, absint( $input['max_lyrics_mb'] ?? 2 ) ) ),
		);
	}

	private function default_settings(): array {
		return array( 'api_base' => 'http://music-search:8000', 'auto_import' => 1, 'max_image_mb' => 10, 'max_audio_mb' => 50, 'max_lyrics_mb' => 2 );
	}

	private function settings(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), $this->default_settings() );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = $this->settings();
		echo '<div class="wrap"><h1>音乐库设置</h1><form method="post" action="options.php">';
		settings_fields( 'mlm_settings_group' );
		echo '<table class="form-table"><tr><th scope="row"><label for="mlm_api_base">远程音乐 API 地址</label></th><td><input class="regular-text" type="url" id="mlm_api_base" name="' . esc_attr( self::OPTION ) . '[api_base]" value="' . esc_attr( $s['api_base'] ) . '" placeholder="https://music-api.example.com"><p class="description">填写已部署的远程 API 根地址，不要以斜杠结尾。本地 Docker 默认使用 http://music-search:8000。</p></td></tr><tr><th scope="row">自动导入媒体</th><td><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[auto_import]" value="1" ' . checked( ! empty( $s['auto_import'] ), true, false ) . '> 新歌曲默认勾选自动导入远程文件</label></td></tr>';
		$limits = array( 'max_image_mb' => '封面最大容量', 'max_audio_mb' => '音频最大容量', 'max_lyrics_mb' => '歌词文件最大容量' );
		foreach ( $limits as $key => $label ) { printf( '<tr><th scope="row"><label for="mlm_%1$s">%2$s</label></th><td><input class="small-text" type="number" min="1" id="mlm_%1$s" name="%3$s[%1$s]" value="%4$d"> MB</td></tr>', esc_attr( $key ), esc_html( $label ), esc_attr( self::OPTION ), (int) $s[ $key ] ); }
		echo '</table>';
		submit_button();
		echo '</form><hr><h2>播放列表</h2><p>可在“音乐库 → 音乐播放列表”中自定义列表，并给歌曲勾选所属列表。插入整张列表使用：<code>[music_playlist id=&quot;播放列表ID&quot;]</code> 或 <code>[music_playlist name=&quot;播放列表名称&quot;]</code>。</p><hr><h2>扩展接口</h2><p><code>mlm_remote_asset_url</code>、<code>mlm_max_remote_asset_size</code>、<code>mlm_asset_imported</code>、<code>mlm_track_saved</code></p></div>';
	}

	public function ajax_search_music(): void {
		check_ajax_referer( 'mlm_search_music', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => '权限不足。' ), 403 ); }
		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		$page = min( 100, max( 1, absint( $_POST['page'] ?? 1 ) ) );
		if ( mb_strlen( $term ) < 2 ) { wp_send_json_error( array( 'message' => '请输入至少 2 个字符。' ), 400 ); }
		$data = $this->music_api_request( '/api/search?' . http_build_query( array( 'q' => $term, 'source' => 'qq', 'limit' => 20, 'page' => $page ) ) );
		$results = array();
		foreach ( $data['tracks'] ?? array() as $item ) {
			$album_mid = sanitize_text_field( $item['album_mid'] ?? '' );
			if ( ! $album_mid && ! empty( $item['album_id'] ) ) { $album_mid = preg_replace( '/^qqalbum_/', '', sanitize_text_field( $item['album_id'] ) ); }
			$results[] = array(
				'id' => sanitize_text_field( $item['id'] ?? '' ), 'mid' => sanitize_text_field( $item['mid'] ?? preg_replace( '/^qqtrack_/', '', $item['id'] ?? '' ) ),
				'media_mid' => sanitize_text_field( $item['media_mid'] ?? '' ), 'title' => sanitize_text_field( $item['name'] ?? '' ),
				'artist' => sanitize_text_field( $item['artist'] ?? '' ), 'album' => sanitize_text_field( $item['album'] ?? '' ), 'album_mid' => $album_mid,
				'cover_url' => esc_url_raw( $item['artwork_url'] ?? '' ), 'source_url' => esc_url_raw( $item['source_url'] ?? '' ),
				'duration' => absint( $item['duration_ms'] ?? 0 ), 'source' => sanitize_text_field( $item['source'] ?? 'qq' ),
			);
		}
		wp_send_json_success( array( 'results' => $results, 'page' => $page, 'has_more' => 20 === count( $results ) ) );
	}

	public function ajax_album_songs(): void {
		$this->check_music_ajax();
		$album_mid = sanitize_text_field( wp_unslash( $_POST['album_mid'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $album_mid ) ) { wp_send_json_error( array( 'message' => '专辑标识无效。' ), 400 ); }
		$data = $this->music_api_request( '/api/album/' . rawurlencode( $album_mid ) );
		$results = array();
		foreach ( $data['tracks'] ?? array() as $item ) {
			$results[] = array(
				'id' => sanitize_text_field( $item['id'] ?? '' ), 'mid' => sanitize_text_field( $item['mid'] ?? preg_replace( '/^qqtrack_/', '', $item['id'] ?? '' ) ),
				'media_mid' => sanitize_text_field( $item['media_mid'] ?? '' ), 'title' => sanitize_text_field( $item['name'] ?? '' ),
				'artist' => sanitize_text_field( $item['artist'] ?? '' ), 'album' => sanitize_text_field( $item['album'] ?? '' ), 'album_mid' => $album_mid,
				'cover_url' => esc_url_raw( $item['artwork_url'] ?? '' ), 'source_url' => esc_url_raw( $item['source_url'] ?? '' ),
				'duration' => absint( $item['duration_ms'] ?? 0 ), 'source' => 'qq',
			);
		}
		wp_send_json_success( array( 'results' => $results ) );
	}

	private function music_api_base(): string {
		$settings = $this->settings();
		return untrailingslashit( (string) apply_filters( 'mlm_music_api_base', $settings['api_base'] ) );
	}

	private function music_api_request( string $path, string $method = 'GET' ): array {
		$base = $this->music_api_base();
		if ( ! $base ) { wp_send_json_error( array( 'message' => '请先在音乐库设置中填写远程音乐 API 地址。' ), 500 ); }
		$response = wp_remote_request( $base . $path, array( 'method' => $method, 'timeout' => 45 ) );
		if ( is_wp_error( $response ) ) { wp_send_json_error( array( 'message' => '远程音乐 API 连接失败：' . $response->get_error_message() ), 502 ); }
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) { wp_send_json_error( array( 'message' => sanitize_text_field( $data['detail'] ?? '远程音乐 API 返回异常。' ) ), 502 ); }
		return $data;
	}

	private function check_music_ajax(): void {
		check_ajax_referer( 'mlm_search_music', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => '权限不足。' ), 403 ); }
	}

	public function ajax_qq_status(): void {
		$this->check_music_ajax();
		wp_send_json_success( $this->music_api_request( '/api/qq/login/status' ) );
	}

	public function ajax_qq_login_start(): void {
		$this->check_music_ajax();
		wp_send_json_success( $this->music_api_request( '/api/qq/login/start', 'POST' ) );
	}

	public function ajax_qq_login_poll(): void {
		$this->check_music_ajax();
		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		wp_send_json_success( $this->music_api_request( '/api/qq/login/poll?identifier=' . rawurlencode( $identifier ) ) );
	}

	public function ajax_resolve_music(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_POST['track_id'] ?? '' ) );
		$quality = $this->sanitize_quality( $_POST['quality'] ?? 'standard' );
		wp_send_json_success( $this->music_api_request( '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $quality ) ) );
	}

	public function ajax_qq_stream(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_REQUEST['track_id'] ?? '' ) );
		$data = $this->music_api_request( '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $this->sanitize_quality( $_REQUEST['quality'] ?? 'standard' ) ) );
		if ( empty( $data['available'] ) || empty( $data['url'] ) ) { status_header( 404 ); wp_die( esc_html( $data['message'] ?? '当前音质不可用。' ) ); }
		wp_redirect( esc_url_raw( $data['url'] ), 302, 'Music Library Manager' );
		exit;
	}

	public function ajax_qq_lyrics(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_POST['track_id'] ?? '' ) );
		if ( '' === $track_id ) { wp_send_json_error( array( 'message' => '歌曲标识无效。' ), 400 ); }
		$data = $this->music_api_request( '/api/details/' . rawurlencode( $track_id ) );
		wp_send_json_success( array( 'lyrics' => sanitize_textarea_field( (string) ( $data['lyrics'] ?? '' ) ) ) );
	}

	private function sanitize_quality( $quality ): string {
		$quality = sanitize_key( wp_unslash( (string) $quality ) );
		return in_array( $quality, array( 'standard', 'hq', 'lossless', 'master' ), true ) ? $quality : 'standard';
	}

	public function ajax_import_music(): void {
		$this->check_music_ajax();
		$item = json_decode( wp_unslash( $_POST['track'] ?? '' ), true );
		if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['title'] ) ) { wp_send_json_error( array( 'message' => '歌曲资料无效。' ), 400 ); }
		$quality = $this->sanitize_quality( $_POST['quality'] ?? 'standard' );
		$track_id = sanitize_text_field( $item['id'] );
		$resource = $this->music_api_request( '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $quality ) );
		if ( empty( $resource['available'] ) || empty( $resource['url'] ) ) { wp_send_json_error( array( 'message' => sanitize_text_field( $resource['message'] ?? '当前音质不可用。' ) ), 422 ); }
		$details = $this->music_api_request( '/api/details/' . rawurlencode( $track_id ) );
		$lyrics = (string) ( $details['lyrics'] ?? '' );
		$post_id = ! empty( $_POST['bulk'] ) ? 0 : absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			$post_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => sanitize_text_field( $item['title'] ) ), true );
			if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 ); }
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( array( 'message' => '无权编辑当前歌曲。' ), 403 ); }
		wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $item['title'] ) ) );
		$meta = array(
			'artist' => sanitize_text_field( $item['artist'] ?? '' ), 'album' => sanitize_text_field( $item['album'] ?? '' ),
			'source_url' => esc_url_raw( $item['source_url'] ?? '' ), 'duration' => absint( $item['duration'] ?? 0 ),
			'lyrics' => sanitize_textarea_field( $lyrics ),
		);
		foreach ( $meta as $key => $value ) { update_post_meta( $post_id, '_mlm_' . $key, $value ); }
		$playlist = sanitize_text_field( wp_unslash( $_POST['playlist'] ?? '' ) );
		$playlist_edit_url = '';
		if ( $playlist ) {
			wp_set_object_terms( $post_id, $playlist, self::PLAYLIST_TAXONOMY, true );
			$playlist_term = get_term_by( 'name', $playlist, self::PLAYLIST_TAXONOMY );
			if ( $playlist_term && ! is_wp_error( $playlist_term ) ) {
				$playlist_edit_url = add_query_arg( array( 'post_type' => self::POST_TYPE, self::PLAYLIST_TAXONOMY => $playlist_term->slug ), admin_url( 'edit.php' ) );
			}
		}
		$audio_id = $this->sideload_asset( esc_url_raw( $resource['url'] ), $post_id, sanitize_text_field( $item['title'] ), 'audio' );
		if ( is_wp_error( $audio_id ) ) { wp_send_json_error( array( 'message' => '音频导入失败：' . $audio_id->get_error_message() ), 502 ); }
		update_post_meta( $post_id, '_mlm_audio_attachment_id', $audio_id );
		update_post_meta( $post_id, '_mlm_audio_url', esc_url_raw( wp_get_attachment_url( $audio_id ) ) );
		$cover_id = 0;
		if ( ! empty( $item['cover_url'] ) ) {
			$cover_title = sanitize_text_field( $item['album'] ?? '' ) ?: sanitize_text_field( $item['title'] );
			$cover_id = $this->sideload_asset( esc_url_raw( $item['cover_url'] ), $post_id, $cover_title, 'cover' );
			if ( ! is_wp_error( $cover_id ) ) { update_post_meta( $post_id, '_mlm_cover_attachment_id', $cover_id ); update_post_meta( $post_id, '_mlm_cover_url', esc_url_raw( wp_get_attachment_url( $cover_id ) ) ); set_post_thumbnail( $post_id, $cover_id ); }
		}
		$lyrics_id = $this->sideload_lyrics_text( $lyrics, $post_id, sanitize_text_field( $item['title'] ) );
		if ( $lyrics_id && ! is_wp_error( $lyrics_id ) ) { update_post_meta( $post_id, '_mlm_lyrics_attachment_id', $lyrics_id ); update_post_meta( $post_id, '_mlm_lyrics_url', esc_url_raw( wp_get_attachment_url( $lyrics_id ) ) ); }
		wp_send_json_success( array( 'message' => '已添加到音乐库并导入媒体文件。', 'post_id' => $post_id, 'edit_url' => get_edit_post_link( $post_id, 'raw' ), 'playlist_edit_url' => $playlist_edit_url, 'audio_url' => wp_get_attachment_url( $audio_id ), 'cover_url' => $cover_id && ! is_wp_error( $cover_id ) ? wp_get_attachment_url( $cover_id ) : '', 'lyrics_url' => $lyrics_id && ! is_wp_error( $lyrics_id ) ? wp_get_attachment_url( $lyrics_id ) : '', 'lyrics' => $lyrics ) );
	}

	private function sideload_lyrics_text( string $lyrics, int $post_id, string $title ) {
		if ( '' === trim( $lyrics ) ) { return 0; }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( sanitize_file_name( $title ) . '.lrc' );
		if ( ! $tmp || false === file_put_contents( $tmp, $lyrics ) ) { return new WP_Error( 'mlm_lyrics_write', '无法创建歌词文件。' ); }
		$file = array( 'name' => sanitize_file_name( $title ) . '.lrc', 'tmp_name' => $tmp );
		$id = media_handle_sideload( $file, $post_id, $title . ' - 歌词', array( 'post_title' => $title . ' - 歌词' ) );
		if ( is_wp_error( $id ) ) { wp_delete_file( $tmp ); }
		return $id;
	}

	public function register_front_assets(): void {
		wp_register_style( 'mlm-aplayer', MLM_URL . 'assets/vendor/aplayer/APlayer.min.css', array(), '1.10.1' );
		wp_register_style( 'mlm-player', MLM_URL . 'assets/css/player.css', array( 'mlm-aplayer' ), MLM_VERSION );
		wp_register_script( 'mlm-aplayer', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
		wp_register_script( 'mlm-player', MLM_URL . 'assets/js/player.js', array( 'mlm-aplayer' ), MLM_VERSION, true );
	}

	private function enqueue_front_assets(): void {
		wp_enqueue_style( 'mlm-player' );
		wp_enqueue_script( 'mlm-player' );
	}

	public function single_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0, 'lyrics' => 'yes' ), $atts, 'music' );
		$post = get_post( absint( $atts['id'] ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { return ''; }
		$this->enqueue_front_assets();
		return $this->render_player( $post, 'yes' === strtolower( (string) $atts['lyrics'] ) );
	}

	public function list_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'limit' => 10, 'artist' => '' ), $atts, 'music_list' );
		$args = array( 'post_type' => self::POST_TYPE, 'posts_per_page' => min( 50, max( 1, absint( $atts['limit'] ) ) ), 'post_status' => 'publish' );
		if ( $atts['artist'] ) { $args['meta_query'] = array( array( 'key' => '_mlm_artist', 'value' => sanitize_text_field( $atts['artist'] ), 'compare' => 'LIKE' ) ); }
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) { return '<p>暂无音乐。</p>'; }
		$this->enqueue_front_assets();
		$html = '<div class="mlm-list">';
		foreach ( $query->posts as $post ) { $html .= $this->render_player( $post, false ); }
		return $html . '</div>';
	}

	public function playlist_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0, 'name' => '' ), $atts, 'music_playlist' );
		$term = absint( $atts['id'] ) ? get_term( absint( $atts['id'] ), self::PLAYLIST_TAXONOMY ) : get_term_by( 'name', sanitize_text_field( $atts['name'] ), self::PLAYLIST_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) { return ''; }
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) );
		if ( ! $query->have_posts() ) { return '<p>该播放列表暂无已发布歌曲。</p>'; }
		$this->enqueue_front_assets(); $html = '<section class="mlm-playlist"><h3>' . esc_html( $term->name ) . '</h3>';
		foreach ( $query->posts as $post ) { $html .= $this->render_player( $post, false ); }
		return $html . '</section>';
	}

	private function render_player( WP_Post $post, bool $show_lyrics ): string {
		$artist = get_post_meta( $post->ID, '_mlm_artist', true ); $album = get_post_meta( $post->ID, '_mlm_album', true );
		$audio = $this->attachment_url( $post->ID, 'audio' ); $cover = $this->attachment_url( $post->ID, 'cover' );
		$lyrics = get_post_meta( $post->ID, '_mlm_lyrics', true );
		$html = '<article class="mlm-player">';
		$html .= '<div class="mlm-info">';
		if ( $audio ) {
			$config = array(
				'name'   => wp_strip_all_tags( get_the_title( $post ) ),
				'artist' => wp_strip_all_tags( (string) $artist ),
				'url'    => esc_url_raw( $audio ),
				'cover'  => esc_url_raw( $cover ),
				'lrc'    => $show_lyrics ? sanitize_textarea_field( (string) $lyrics ) : '',
			);
			$html .= '<div class="mlm-aplayer" data-mlm-audio="' . esc_attr( wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"></div>';
		}
		else { $html .= '<p class="mlm-unavailable">暂无可播放音频</p>'; }
		return $html . '</div></article>';
	}

	private function attachment_url( int $post_id, string $type ): string {
		$id = absint( get_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', true ) );
		$url = $id ? wp_get_attachment_url( $id ) : '';
		return $url ? $url : (string) get_post_meta( $post_id, '_mlm_' . $type . '_url', true );
	}

	public function columns( array $columns ): array {
		return array(
			'cb' => $columns['cb'] ?? '<input type="checkbox">', 'mlm_cover' => '封面', 'title' => '歌曲名称',
			'mlm_artist' => '作者', 'mlm_album' => '专辑名称', 'mlm_category' => '分类', 'mlm_lyrics' => '歌词',
			'mlm_address' => '地址', 'mlm_actions' => '操作',
		);
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'mlm_cover' === $column ) {
			$cover_id = absint( get_post_meta( $post_id, '_mlm_cover_attachment_id', true ) );
			if ( $cover_id ) { echo wp_get_attachment_image( $cover_id, array( 96, 96 ), false, array( 'class' => 'mlm-list-cover' ) ); }
			elseif ( $this->attachment_url( $post_id, 'cover' ) ) { printf( '<img class="mlm-list-cover" src="%s" alt="">', esc_url( $this->attachment_url( $post_id, 'cover' ) ) ); }
		}
		if ( 'mlm_artist' === $column ) { echo esc_html( get_post_meta( $post_id, '_mlm_artist', true ) ); }
		if ( 'mlm_album' === $column ) { echo esc_html( get_post_meta( $post_id, '_mlm_album', true ) ); }
		if ( 'mlm_category' === $column ) {
			$terms = get_the_terms( $post_id, self::TAXONOMY );
			if ( $terms && ! is_wp_error( $terms ) ) { echo esc_html( implode( '、', wp_list_pluck( $terms, 'name' ) ) ); }
		}
		if ( 'mlm_lyrics' === $column ) {
			$id = absint( get_post_meta( $post_id, '_mlm_lyrics_attachment_id', true ) );
			if ( $id ) { printf( '<a href="%s">%s</a>', esc_url( wp_get_attachment_url( $id ) ), esc_html( wp_get_attachment_url( $id ) ) ); }
			elseif ( get_post_meta( $post_id, '_mlm_lyrics', true ) ) { echo '<span class="dashicons dashicons-yes-alt" title="已录入歌词"></span> 已录入'; }
		}
		if ( 'mlm_address' === $column ) {
			$url = $this->attachment_url( $post_id, 'audio' );
			if ( $url ) { printf( '<a href="%1$s">%1$s</a>', esc_url( $url ) ); }
		}
		if ( 'mlm_actions' === $column ) {
			printf( '<a href="%s">编辑</a>', esc_url( get_edit_post_link( $post_id ) ) );
			if ( current_user_can( 'delete_post', $post_id ) ) { printf( ' | <a class="submitdelete" href="%s">删除</a>', esc_url( get_delete_post_link( $post_id ) ) ); }
		}
	}

	public function row_actions( array $actions, WP_Post $post ): array {
		return self::POST_TYPE === $post->post_type ? array() : $actions;
	}
}
