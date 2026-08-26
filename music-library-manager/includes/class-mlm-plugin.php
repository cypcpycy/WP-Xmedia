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
	private const API_RULE_OPTION = 'mlm_api_rule';
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
		add_action( 'before_delete_post', array( $this, 'delete_track_attachments' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_data' ) );
		add_action( 'media_buttons', array( $this, 'classic_editor_music_button' ), 20, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );
		add_action( 'wp_ajax_mlm_search_music', array( $this, 'ajax_search_music' ) );
		add_action( 'wp_ajax_mlm_album_songs', array( $this, 'ajax_album_songs' ) );
		add_action( 'wp_ajax_mlm_resolve_music', array( $this, 'ajax_resolve_music' ) );
		add_action( 'wp_ajax_mlm_import_music', array( $this, 'ajax_import_music' ) );
		add_action( 'wp_ajax_mlm_qq_status', array( $this, 'ajax_qq_status' ) );
		add_action( 'wp_ajax_mlm_qq_login_start', array( $this, 'ajax_qq_login_start' ) );
		add_action( 'wp_ajax_mlm_qq_login_poll', array( $this, 'ajax_qq_login_poll' ) );
		add_action( 'wp_ajax_mlm_qq_logout', array( $this, 'ajax_qq_logout' ) );
		add_action( 'wp_ajax_mlm_qq_stream', array( $this, 'ajax_qq_stream' ) );
		add_action( 'wp_ajax_mlm_qq_lyrics', array( $this, 'ajax_qq_lyrics' ) );
		add_shortcode( 'music', array( $this, 'single_shortcode' ) );
		add_shortcode( 'music_list', array( $this, 'list_shortcode' ) );
		add_shortcode( 'music_playlist', array( $this, 'playlist_shortcode' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'hide_track_post_states' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'allow_lyrics_mime' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'redirect_playlist_taxonomy_page' ) );
		add_action( 'admin_post_mlm_import_api_rule', array( $this, 'import_api_rule' ) );
		add_action( 'admin_post_mlm_save_playlist_tracks', array( $this, 'save_playlist_tracks' ) );
		add_action( 'admin_post_mlm_save_playlist_name', array( $this, 'save_playlist_name' ) );
		add_action( 'admin_post_mlm_delete_playlist', array( $this, 'delete_playlist' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'tiny_mce_before_init', array( $this, 'classic_editor_mce_init' ) );
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
		wp_add_inline_script( 'mlm-music-block', 'window.mlmBlockData = ' . wp_json_encode( $this->music_library_data() ) . ';', 'before' );
	}

	private function music_library_data(): array {
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		$tracks = array();
		$track_map = array();
		$track_identities = array(); $seen_audio = array(); $seen_song = array();
		foreach ( $posts as $post ) {
			$cover_id = absint( get_post_meta( $post->ID, '_mlm_cover_attachment_id', true ) );
			$cover_thumbnail = $cover_id ? wp_get_attachment_image_url( $cover_id, 'thumbnail' ) : '';
			$item = array(
				'id' => $post->ID, 'title' => get_the_title( $post ),
				'artist' => (string) get_post_meta( $post->ID, '_mlm_artist', true ),
				'album' => (string) get_post_meta( $post->ID, '_mlm_album', true ),
				'cover' => $cover_thumbnail ?: $this->attachment_url( $post->ID, 'cover' ),
				'audio' => $this->attachment_url( $post->ID, 'audio' ),
			);
			$identity = $this->track_identity_keys( $post, $item );
			$track_map[ $post->ID ] = $item; $track_identities[ $post->ID ] = $identity;
			if ( ( $identity['audio'] && isset( $seen_audio[ $identity['audio'] ] ) ) || ( $identity['song'] && isset( $seen_song[ $identity['song'] ] ) ) ) { continue; }
			if ( $identity['audio'] ) { $seen_audio[ $identity['audio'] ] = true; }
			if ( $identity['song'] ) { $seen_song[ $identity['song'] ] = true; }
			$tracks[] = $item;
		}
		$playlists = array();
		$terms = get_terms( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$ids = get_objects_in_term( $term->term_id, self::PLAYLIST_TAXONOMY );
				$items = array(); $playlist_audio = array(); $playlist_song = array();
				foreach ( $ids as $id ) {
					if ( ! isset( $track_map[ $id ] ) ) { continue; }
					$identity = $track_identities[ $id ];
					if ( ( $identity['audio'] && isset( $playlist_audio[ $identity['audio'] ] ) ) || ( $identity['song'] && isset( $playlist_song[ $identity['song'] ] ) ) ) { continue; }
					if ( $identity['audio'] ) { $playlist_audio[ $identity['audio'] ] = true; }
					if ( $identity['song'] ) { $playlist_song[ $identity['song'] ] = true; }
					$items[] = $track_map[ $id ];
				}
				$playlists[] = array( 'id' => $term->term_id, 'name' => $term->name, 'count' => count( $items ), 'tracks' => $items );
			}
		}
		return array( 'tracks' => $tracks, 'playlists' => $playlists );
	}

	public function classic_editor_music_button( string $editor_id ): void {
		if ( ! current_user_can( 'edit_posts' ) || 'content' !== $editor_id ) { return; }
		echo '<button type="button" class="button" id="mlm-classic-insert"><span class="dashicons dashicons-format-audio" aria-hidden="true"></span> 插入音乐</button>';
	}

	private function track_identity_keys( WP_Post $post, array $item ): array {
		$audio_key = ''; $attachment_id = absint( get_post_meta( $post->ID, '_mlm_audio_attachment_id', true ) );
		if ( $attachment_id ) {
			$hash = (string) get_post_meta( $attachment_id, '_mlm_file_sha256', true ); $file = get_attached_file( $attachment_id );
			if ( ! $hash && $file && is_readable( $file ) ) { $hash = (string) hash_file( 'sha256', $file ); if ( $hash ) { update_post_meta( $attachment_id, '_mlm_file_sha256', $hash ); } }
			$audio_key = $hash ? 'hash:' . $hash : 'attachment:' . $attachment_id;
		} elseif ( ! empty( $item['audio'] ) ) { $audio_key = 'url:' . strtolower( untrailingslashit( (string) $item['audio'] ) ); }
		$normalize = static function ( string $value ): string { return (string) preg_replace( '/[\p{P}\p{Z}\s]+/u', '', mb_strtolower( trim( $value ) ) ); };
		$song_key = $normalize( (string) $item['title'] ) . '|' . $normalize( (string) $item['artist'] );
		return array( 'audio' => $audio_key, 'song' => '|' === $song_key ? '' : $song_key );
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
		echo '<div class="mlm-qq-status"><button type="button" class="button" id="mlm-qq-login" hidden disabled>QQ 扫码登录</button><button type="button" class="button" id="mlm-qq-logout" hidden disabled>退出登录</button></div><div id="mlm-qq-panel" hidden><img id="mlm-qq-qr" alt="QQ 登录二维码"><p id="mlm-qq-hint">扫码后请在手机上确认。</p></div>';
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
		if ( in_array( get_post_status( $post_id ), array( 'draft', 'pending', 'auto-draft' ), true ) && '' !== trim( get_the_title( $post_id ) ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		}
		do_action( 'mlm_track_saved', $post_id, $data );
	}

	public function delete_track_attachments( int $post_id, WP_Post $post ): void {
		if ( self::POST_TYPE !== $post->post_type ) { return; }
		$attachment_ids = array_filter( array_map( 'absint', array(
			get_post_meta( $post_id, '_mlm_audio_attachment_id', true ),
			get_post_meta( $post_id, '_mlm_cover_attachment_id', true ),
			get_post_meta( $post_id, '_mlm_lyrics_attachment_id', true ),
			get_post_thumbnail_id( $post_id ),
		) ) );
		foreach ( array_unique( $attachment_ids ) as $attachment_id ) {
			$used_elsewhere = get_posts( array(
				'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
				'post__not_in' => array( $post_id ),
				'meta_query' => array( 'relation' => 'OR',
					array( 'key' => '_mlm_audio_attachment_id', 'value' => $attachment_id, 'compare' => '=' ),
					array( 'key' => '_mlm_cover_attachment_id', 'value' => $attachment_id, 'compare' => '=' ),
					array( 'key' => '_mlm_lyrics_attachment_id', 'value' => $attachment_id, 'compare' => '=' ),
					array( 'key' => '_thumbnail_id', 'value' => $attachment_id, 'compare' => '=' ),
				),
			) );
			if ( ! $used_elsewhere && 'attachment' === get_post_type( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
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
		else { $attached_file = get_attached_file( $id ); if ( $attached_file && is_readable( $attached_file ) ) { update_post_meta( $id, '_mlm_file_sha256', hash_file( 'sha256', $attached_file ) ); } }
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

	public function classic_editor_mce_init( array $init ): array {
		if ( ! is_admin() ) return $init;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			$css = plugins_url( '../assets/css/classic-editor.css', __FILE__ );
			$init['content_css'] = isset( $init['content_css'] ) ? $init['content_css'] . ',' . $css : $css;
		}
		return $init;
	}

	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) { return; }
		if ( in_array( $screen->post_type, array( 'post', 'page' ), true ) && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style( 'mlm-classic-editor', MLM_URL . 'assets/css/classic-editor.css', array( 'dashicons' ), MLM_VERSION );
			wp_enqueue_script( 'mlm-classic-editor', MLM_URL . 'assets/js/classic-editor.js', array( 'jquery' ), MLM_VERSION, true );
			wp_localize_script( 'mlm-classic-editor', 'mlmClassicEditor', array( 'library' => $this->music_library_data() ) );
			return;
		}
		if ( self::POST_TYPE !== $screen->post_type ) { return; }
		if ( false !== strpos( $hook, '_page_mlm-playlists' ) ) {
			wp_enqueue_style( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.css', array(), '1.10.1' );
			wp_enqueue_style( 'mlm-admin-playlists', MLM_URL . 'assets/css/admin-playlists.css', array(), MLM_VERSION );
			wp_enqueue_style( 'mlm-admin-playlist-editor', MLM_URL . 'assets/css/admin-playlist-editor.css', array( 'mlm-admin-playlists' ), MLM_VERSION );
			wp_enqueue_script( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
			wp_enqueue_script( 'mlm-admin-playlists', MLM_URL . 'assets/js/admin-playlists.js', array( 'mlm-aplayer-admin' ), MLM_VERSION, true );
			wp_enqueue_script( 'mlm-admin-playlist-editor', MLM_URL . 'assets/js/admin-playlist-editor.js', array( 'mlm-admin-playlists' ), MLM_VERSION, true );
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.css', array(), '1.10.1' );
		wp_enqueue_style( 'mlm-admin', MLM_URL . 'assets/css/admin.css', array(), MLM_VERSION );
		wp_enqueue_style( 'mlm-admin-music', MLM_URL . 'assets/css/admin-music.css', array( 'mlm-admin' ), MLM_VERSION );
		wp_enqueue_script( 'mlm-aplayer-admin', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
		wp_enqueue_script( 'mlm-admin', MLM_URL . 'assets/js/admin.js', array( 'jquery', 'wp-data', 'mlm-aplayer-admin' ), MLM_VERSION, true );
		wp_localize_script( 'mlm-admin', 'mlmAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mlm_search_music' ), 'postId' => get_the_ID() ) );
	}

	public function admin_menu(): void {
		remove_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'edit-tags.php?taxonomy=' . self::PLAYLIST_TAXONOMY . '&amp;post_type=' . self::POST_TYPE );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '音乐播放列表', '音乐播放列表', 'edit_posts', 'mlm-playlists', array( $this, 'render_playlists_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '音乐库设置', '设置', 'manage_options', 'mlm-settings', array( $this, 'render_settings_page' ) );
	}

	public function redirect_playlist_taxonomy_page(): void {
		global $pagenow;
		if ( 'edit-tags.php' !== $pagenow || self::PLAYLIST_TAXONOMY !== ( $_GET['taxonomy'] ?? '' ) || self::POST_TYPE !== ( $_GET['post_type'] ?? '' ) || isset( $_GET['tag_ID'] ) || isset( $_GET['mlm_manage'] ) ) { return; }
		wp_safe_redirect( add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function save_playlist_tracks(): void {
		if ( ! current_user_can( 'manage_categories' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		$playlist_id = absint( $_POST['playlist_id'] ?? 0 ); check_admin_referer( 'mlm_save_playlist_tracks_' . $playlist_id );
		$term = get_term( $playlist_id, self::PLAYLIST_TAXONOMY ); if ( ! $term || is_wp_error( $term ) ) { wp_die( '播放列表不存在。', '', array( 'response' => 404 ) ); }
		$selected = array_values( array_filter( array_map( 'absint', (array) ( $_POST['track_ids'] ?? array() ) ) ) );
		$mode = sanitize_key( $_POST['save_mode'] ?? 'members' );
		if ( 'add' === $mode ) {
			foreach ( $selected as $track_id ) { if ( self::POST_TYPE === get_post_type( $track_id ) ) { wp_set_object_terms( $track_id, array( $playlist_id ), self::PLAYLIST_TAXONOMY, true ); } }
			$this->redirect_playlist_admin( $playlist_id, $selected ? '所选歌曲已加入歌单。' : '没有选择要加入的歌曲。', true );
		}
		$current = array_map( 'intval', (array) get_objects_in_term( $playlist_id, self::PLAYLIST_TAXONOMY ) );
		$kept = array_fill_keys( $selected, true );
		foreach ( $current as $track_id ) { if ( ! isset( $kept[ $track_id ] ) ) { wp_remove_object_terms( $track_id, $playlist_id, self::PLAYLIST_TAXONOMY ); } }
		$this->redirect_playlist_admin( $playlist_id, '歌单曲目已保存。', true );
	}

	public function save_playlist_name(): void {
		if ( ! current_user_can( 'manage_categories' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		$playlist_id = absint( $_POST['playlist_id'] ?? 0 ); check_admin_referer( 'mlm_save_playlist_name_' . $playlist_id );
		$name = sanitize_text_field( wp_unslash( $_POST['playlist_name'] ?? '' ) );
		if ( '' === $name ) { wp_die( '歌单名称不能为空。', '', array( 'response' => 400 ) ); }
		$result = wp_update_term( $playlist_id, self::PLAYLIST_TAXONOMY, array( 'name' => $name ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) ); }
		$this->redirect_playlist_admin( $playlist_id, '歌单名称已更新。' );
	}

	public function delete_playlist(): void {
		if ( ! current_user_can( 'manage_categories' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		$playlist_id = absint( $_POST['playlist_id'] ?? 0 ); check_admin_referer( 'mlm_delete_playlist_' . $playlist_id );
		$term = get_term( $playlist_id, self::PLAYLIST_TAXONOMY ); if ( ! $term || is_wp_error( $term ) ) { wp_die( '播放列表不存在。', '', array( 'response' => 404 ) ); }
		$delete_tracks = 'with_tracks' === sanitize_key( $_POST['delete_mode'] ?? 'playlist_only' ); $deleted_tracks = 0;
		if ( $delete_tracks ) { foreach ( (array) get_objects_in_term( $playlist_id, self::PLAYLIST_TAXONOMY ) as $track_id ) { if ( current_user_can( 'delete_post', $track_id ) && wp_delete_post( (int) $track_id, true ) ) { $deleted_tracks++; } } }
		wp_delete_term( $playlist_id, self::PLAYLIST_TAXONOMY );
		$this->redirect_playlist_admin( 0, $delete_tracks ? '播放列表已删除，并永久删除其中 ' . $deleted_tracks . ' 首歌曲及其独占媒体文件。' : '播放列表已删除，歌曲和媒体文件均已保留。' );
	}

	private function redirect_playlist_admin( int $playlist_id, string $message, bool $editing = false ): void {
		$args = array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists', 'mlm_playlist_message' => $message ); if ( $playlist_id ) { $args['playlist_id'] = $playlist_id; }
		if ( $editing ) { $args['edit_playlist'] = 1; }
		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) ); exit;
	}

	public function render_playlists_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		$playlist_id = absint( $_GET['playlist_id'] ?? 0 );
		$manage_url = add_query_arg( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'post_type' => self::POST_TYPE, 'mlm_manage' => 1 ), admin_url( 'edit-tags.php' ) );
		echo '<div class="wrap mlm-playlists-admin">';
		if ( isset( $_GET['mlm_playlist_message'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_playlist_message'] ) ) ) . '</p></div>'; }
		if ( $playlist_id ) {
			$term = get_term( $playlist_id, self::PLAYLIST_TAXONOMY );
			if ( ! $term || is_wp_error( $term ) ) { echo '<h1>音乐播放列表</h1><div class="notice notice-error"><p>找不到这个歌单。</p></div></div>'; return; }
			$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => array( 'publish', 'draft', 'private' ), 'orderby' => 'date', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) );
			$audio = array(); $unavailable = array();
			foreach ( $query->posts as $post ) {
				$url = $this->attachment_url( $post->ID, 'audio' );
				if ( ! $url ) { $unavailable[] = $post; continue; }
				$audio[] = array( 'name' => wp_strip_all_tags( get_the_title( $post ) ), 'artist' => wp_strip_all_tags( (string) get_post_meta( $post->ID, '_mlm_artist', true ) ), 'url' => esc_url_raw( $url ), 'cover' => esc_url_raw( $this->attachment_url( $post->ID, 'cover' ) ), 'lrc' => sanitize_textarea_field( (string) get_post_meta( $post->ID, '_mlm_lyrics', true ) ) );
			}
			$back_url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists' ), admin_url( 'edit.php' ) );
			$editing = ! empty( $_GET['edit_playlist'] );
			$edit_url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists', 'playlist_id' => $term->term_id, 'edit_playlist' => 1 ), admin_url( 'edit.php' ) );
			$view_url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists', 'playlist_id' => $term->term_id ), admin_url( 'edit.php' ) );
			echo '<p><a class="button" href="' . esc_url( $back_url ) . '">← 返回全部歌单</a></p>';
			echo '<form class="mlm-playlist-title-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_save_playlist_name"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '">'; wp_nonce_field( 'mlm_save_playlist_name_' . $term->term_id );
			echo '<div id="titlediv"><div id="titlewrap"><label class="screen-reader-text" for="mlm-playlist-title">歌单名称</label><input type="text" id="mlm-playlist-title" name="playlist_name" value="' . esc_attr( $term->name ) . '" autocomplete="off"></div></div><div class="mlm-playlist-title-actions"><span>' . count( $query->posts ) . ' 首歌曲，' . count( $audio ) . ' 首可播放</span><button type="submit" class="button">更新歌单名称</button></div></form>';
			echo '<div class="mlm-playlist-heading"><div></div>' . ( $editing ? '<a class="button" href="' . esc_url( $view_url ) . '">完成编辑</a>' : '<a class="button button-primary" href="' . esc_url( $edit_url ) . '">编辑歌单</a>' ) . '</div>';
			if ( $audio ) { echo '<div class="mlm-admin-album-player" data-playlist="' . esc_attr( wp_json_encode( $audio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"></div>'; }
			else { echo '<div class="mlm-playlist-empty"><p>这个歌单还没有可播放的音频文件。</p></div>'; }
			if ( $unavailable ) { echo '<h2>暂不可播放</h2><ul class="mlm-unavailable-tracks">'; foreach ( $unavailable as $post ) { echo '<li><a href="' . esc_url( get_edit_post_link( $post->ID, 'raw' ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>：尚未导入音频文件</li>'; } echo '</ul>'; }
			$member_ids_list = array_map( 'intval', (array) get_objects_in_term( $term->term_id, self::PLAYLIST_TAXONOMY ) );
			if ( $editing ) {
				echo '<section class="mlm-playlist-members"><h2>歌单中的歌曲</h2><p>取消勾选不再需要的歌曲，然后保存。</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_save_playlist_tracks"><input type="hidden" name="save_mode" value="members"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '">'; wp_nonce_field( 'mlm_save_playlist_tracks_' . $term->term_id ); echo '<div class="mlm-track-checklist">';
				foreach ( $query->posts as $track ) { $cover = $this->attachment_url( $track->ID, 'cover' ); echo '<label class="mlm-track-choice"><input type="checkbox" name="track_ids[]" value="' . (int) $track->ID . '" checked><span class="mlm-choice-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="">' : '<span class="dashicons dashicons-format-audio"></span>' ) . '</span><span><strong>' . esc_html( get_the_title( $track ) ) . '</strong><small>' . esc_html( (string) get_post_meta( $track->ID, '_mlm_artist', true ) ) . '</small></span></label>'; }
				echo '</div>'; submit_button( '保存歌单曲目', 'primary', 'submit', false ); echo '</form></section>';
				$page = max( 1, absint( $_GET['track_page'] ?? 1 ) );
				$available_query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 30, 'paged' => $page, 'orderby' => 'title', 'order' => 'ASC', 'post__not_in' => $member_ids_list ) );
				echo '<section class="mlm-playlist-members"><h2>从音乐库加入歌曲</h2><p>这里仅显示尚未加入本歌单的歌曲，每页 30 首。</p>';
				if ( $available_query->posts ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_save_playlist_tracks"><input type="hidden" name="save_mode" value="add"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '">'; wp_nonce_field( 'mlm_save_playlist_tracks_' . $term->term_id ); echo '<div class="mlm-track-checklist">'; foreach ( $available_query->posts as $track ) { $cover = $this->attachment_url( $track->ID, 'cover' ); echo '<label class="mlm-track-choice"><input type="checkbox" name="track_ids[]" value="' . (int) $track->ID . '"><span class="mlm-choice-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="">' : '<span class="dashicons dashicons-format-audio"></span>' ) . '</span><span><strong>' . esc_html( get_the_title( $track ) ) . '</strong><small>' . esc_html( (string) get_post_meta( $track->ID, '_mlm_artist', true ) ) . '</small></span></label>'; } echo '</div>'; submit_button( '将所选歌曲加入歌单', 'primary', 'submit', false ); echo '</form>'; } else { echo '<p>音乐库中的所有歌曲都已经在这个歌单里。</p>'; }
				if ( $available_query->max_num_pages > 1 ) { $page_base = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists', 'playlist_id' => $term->term_id, 'edit_playlist' => 1, 'track_page' => '%#%' ), admin_url( 'edit.php' ) ); echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => $page_base, 'format' => '', 'current' => $page, 'total' => $available_query->max_num_pages, 'prev_text' => '‹', 'next_text' => '›' ) ) ) . '</div></div>'; }
				echo '</section>';
			}
			if ( current_user_can( 'manage_categories' ) ) { echo '<section class="mlm-playlist-danger"><h2>删除播放列表</h2><p>可只删除列表，也可以同时永久删除列表中的歌曲记录和相关媒体文件。</p><form class="mlm-delete-playlist-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_delete_playlist"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '"><input type="hidden" name="delete_mode" value="playlist_only">'; wp_nonce_field( 'mlm_delete_playlist_' . $term->term_id ); submit_button( '删除播放列表', 'delete', 'submit', false ); echo '</form></section>'; }
			echo '</div>'; return;
		}
		$terms = get_terms( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
		echo '<div class="mlm-playlists-heading"><div><h1>音乐播放列表</h1><p>选择一个歌单，查看并播放其中的全部音乐。</p></div><a class="button button-primary" href="' . esc_url( $manage_url ) . '">新建或管理歌单</a></div>';
		if ( is_wp_error( $terms ) || ! $terms ) { echo '<div class="mlm-playlist-empty"><p>还没有歌单，请先创建一个音乐播放列表。</p></div></div>'; return; }
		echo '<div class="mlm-playlist-grid">';
		foreach ( $terms as $term ) {
			$ids = get_objects_in_term( $term->term_id, self::PLAYLIST_TAXONOMY ); $cover = '';
			foreach ( array_slice( (array) $ids, 0, 10 ) as $id ) { $cover = $this->attachment_url( (int) $id, 'cover' ); if ( $cover ) { break; } }
			$url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-playlists', 'playlist_id' => $term->term_id ), admin_url( 'edit.php' ) );
			echo '<a class="mlm-playlist-card" href="' . esc_url( $url ) . '"><span class="mlm-playlist-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="">' : '<span class="dashicons dashicons-playlist-audio"></span>' ) . '</span><span class="mlm-playlist-meta"><strong>' . esc_html( $term->name ) . '</strong><small>' . count( (array) $ids ) . ' 首歌曲</small></span><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		}
		echo '</div></div>';
	}

	public function register_settings(): void {
		register_setting( 'mlm_settings_group', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_settings' ), 'default' => $this->default_settings() ) );
	}

	public function sanitize_settings( array $input ): array {
		$stored = (array) get_option( self::OPTION, array() );
		return array(
			'api_base' => esc_url_raw( untrailingslashit( $input['api_base'] ?? ( $stored['api_base'] ?? $this->default_settings()['api_base'] ) ) ),
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

	private function api_rule(): array {
		$rule = (array) get_option( self::API_RULE_OPTION, array() );
		return isset( $rule['endpoints'] ) && is_array( $rule['endpoints'] ) ? $rule : array();
	}

	private function api_path( string $name, array $values = array(), string $fallback = '' ): string {
		$rule = $this->api_rule();
		$path = (string) ( $rule['endpoints'][ $name ]['path'] ?? $fallback );
		foreach ( $values as $key => $value ) {
			$path = str_replace( '{' . $key . '}', rawurlencode( (string) $value ), $path );
		}
		return $path;
	}

	public function import_api_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mlm_import_api_rule' );
		$file = $_FILES['mlm_api_rule_file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) ) {
			$this->redirect_rule_import( '请选择有效的 JSON 接口规则文件。', false );
		}
		if ( (int) ( $file['size'] ?? 0 ) > 256 * KB_IN_BYTES ) {
			$this->redirect_rule_import( '接口规则文件不能超过 256 KB。', false );
		}
		$contents = file_get_contents( $file['tmp_name'] );
		$rule = json_decode( (string) $contents, true );
		$required = array( 'search', 'album', 'details', 'resource', 'login_status', 'login_start', 'login_poll', 'logout' );
		if ( ! is_array( $rule ) || 'wp-xmedia-api-rule/v1' !== ( $rule['schema'] ?? '' ) || empty( $rule['base_url'] ) || empty( $rule['endpoints'] ) || ! is_array( $rule['endpoints'] ) ) {
			$this->redirect_rule_import( '接口规则格式或版本不受支持。', false );
		}
		$base_url = esc_url_raw( untrailingslashit( (string) $rule['base_url'] ) );
		$is_local_docker_api = 'http://music-search:8000' === $base_url;
		if ( ! wp_http_validate_url( $base_url ) && ! $is_local_docker_api ) { $this->redirect_rule_import( '接口根地址无效。', false ); }
		$clean_endpoints = array();
		foreach ( $rule['endpoints'] as $name => $endpoint ) {
			$name = sanitize_key( $name );
			$path = sanitize_text_field( (string) ( $endpoint['path'] ?? '' ) );
			$method = strtoupper( sanitize_key( $endpoint['method'] ?? 'GET' ) );
			if ( $name && $path && '/' === $path[0] && false === strpos( $path, '://' ) && in_array( $method, array( 'GET', 'POST' ), true ) ) {
				$clean_endpoints[ $name ] = array( 'method' => $method, 'path' => $path );
			}
		}
		foreach ( $required as $name ) {
			if ( empty( $clean_endpoints[ $name ]['path'] ) ) {
				$this->redirect_rule_import( '接口规则缺少必要端点：' . $name, false );
			}
		}
		$clean_rule = array(
			'schema' => 'wp-xmedia-api-rule/v1',
			'name' => sanitize_text_field( $rule['name'] ?? 'Music Search API' ),
			'version' => sanitize_text_field( $rule['version'] ?? '' ),
			'base_url' => $base_url,
			'source' => sanitize_key( $rule['source'] ?? 'qq' ),
			'defaults' => is_array( $rule['defaults'] ?? null ) ? $rule['defaults'] : array(),
			'endpoints' => $clean_endpoints,
			'response' => is_array( $rule['response'] ?? null ) ? $rule['response'] : array(),
			'qualities' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $rule['qualities'] ?? array() ) ) ) ),
		);
		update_option( self::API_RULE_OPTION, $clean_rule, false );
		$settings = $this->settings();
		$settings['api_base'] = $base_url;
		update_option( self::OPTION, $settings, false );
		$this->redirect_rule_import( '接口规则已导入并启用。', true );
	}

	private function redirect_rule_import( string $message, bool $success ): void {
		$url = add_query_arg(
			array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-settings', 'mlm_rule_status' => $success ? 'success' : 'error', 'mlm_rule_message' => $message ),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = $this->settings();
		echo '<div class="wrap"><h1>音乐库设置</h1>';
		if ( isset( $_GET['mlm_rule_message'] ) ) {
			$notice_class = 'success' === ( $_GET['mlm_rule_status'] ?? '' ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_rule_message'] ) ) ) . '</p></div>';
		}
		echo '<form method="post" action="options.php">';
		settings_fields( 'mlm_settings_group' );
		echo '<table class="form-table"><tr><th scope="row">自动导入媒体</th><td><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[auto_import]" value="1" ' . checked( ! empty( $s['auto_import'] ), true, false ) . '> 新歌曲默认勾选自动导入远程文件</label></td></tr>';
		$limits = array( 'max_image_mb' => '封面最大容量', 'max_audio_mb' => '音频最大容量', 'max_lyrics_mb' => '歌词文件最大容量' );
		foreach ( $limits as $key => $label ) { printf( '<tr><th scope="row"><label for="mlm_%1$s">%2$s</label></th><td><input class="small-text" type="number" min="1" id="mlm_%1$s" name="%3$s[%1$s]" value="%4$d"> MB</td></tr>', esc_attr( $key ), esc_html( $label ), esc_attr( self::OPTION ), (int) $s[ $key ] ); }
		echo '</table>';
		submit_button();
		echo '</form><hr><h2>导入 API 接口规则</h2><p>选择兼容的 JSON 规则文件后，插件会自动配置 API 根地址和端点模板；规则文件不应包含 Cookie、Token 或账号密码。</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mlm_import_api_rule">';
		wp_nonce_field( 'mlm_import_api_rule' );
		echo '<input type="file" name="mlm_api_rule_file" accept="application/json,.json" required> ';
		submit_button( '导入并启用接口规则', 'secondary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( MLM_URL . 'templates/wp-xmedia-api-rule.json' ) . '" download="wp-xmedia-api-rule.json">下载规则模板</a>';
		$rule = $this->api_rule();
		if ( $rule ) { echo '<p class="description">当前规则：' . esc_html( (string) ( $rule['name'] ?? '' ) ) . ' ' . esc_html( (string) ( $rule['version'] ?? '' ) ) . '（' . esc_html( (string) ( $rule['base_url'] ?? '' ) ) . '）</p>'; }
		echo '</form><hr><h2>播放列表</h2><p>可在“音乐库 → 音乐播放列表”中自定义列表，并给歌曲勾选所属列表。插入整张列表使用：<code>[music_playlist id=&quot;播放列表ID&quot;]</code> 或 <code>[music_playlist name=&quot;播放列表名称&quot;]</code>。</p><hr><h2>扩展接口</h2><p><code>mlm_remote_asset_url</code>、<code>mlm_max_remote_asset_size</code>、<code>mlm_asset_imported</code>、<code>mlm_track_saved</code></p></div>';
	}

	public function ajax_search_music(): void {
		check_ajax_referer( 'mlm_search_music', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( array( 'message' => '权限不足。' ), 403 ); }
		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		$page = min( 100, max( 1, absint( $_POST['page'] ?? 1 ) ) );
		if ( mb_strlen( $term ) < 2 ) { wp_send_json_error( array( 'message' => '请输入至少 2 个字符。' ), 400 ); }
		$data = $this->music_api_request( $this->api_path( 'search', array( 'query' => $term, 'source' => 'qq', 'limit' => 20, 'page' => $page ), '/api/search?' . http_build_query( array( 'q' => $term, 'source' => 'qq', 'limit' => 20, 'page' => $page ) ) ) );
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
		wp_send_json_success( array( 'results' => $results, 'page' => $page, 'has_more' => ! empty( $data['pagination']['has_next'] ), 'pagination' => $data['pagination'] ?? array() ) );
	}

	public function ajax_album_songs(): void {
		$this->check_music_ajax();
		$album_mid = sanitize_text_field( wp_unslash( $_POST['album_mid'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $album_mid ) ) { wp_send_json_error( array( 'message' => '专辑标识无效。' ), 400 ); }
		$data = $this->music_api_request( $this->api_path( 'album', array( 'album_mid' => $album_mid ), '/api/album/' . rawurlencode( $album_mid ) ) );
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
		if ( ! $base ) { wp_send_json_error( array( 'message' => '请先在音乐库设置中导入 API 接口规则。' ), 500 ); }
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
		wp_send_json_success( $this->music_api_request( $this->api_path( 'login_status', array(), '/api/qq/login/status' ) ) );
	}

	public function ajax_qq_login_start(): void {
		$this->check_music_ajax();
		wp_send_json_success( $this->music_api_request( $this->api_path( 'login_start', array( 'login_type' => 'qq' ), '/api/qq/login/start' ), 'POST' ) );
	}

	public function ajax_qq_login_poll(): void {
		$this->check_music_ajax();
		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		wp_send_json_success( $this->music_api_request( $this->api_path( 'login_poll', array( 'identifier' => $identifier, 'login_type' => 'qq' ), '/api/qq/login/poll?identifier=' . rawurlencode( $identifier ) ) ) );
	}

	public function ajax_qq_logout(): void {
		$this->check_music_ajax();
		wp_send_json_success( $this->music_api_request( $this->api_path( 'logout', array(), '/api/qq/login/logout' ), 'POST' ) );
	}

	public function ajax_resolve_music(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_POST['track_id'] ?? '' ) );
		$quality = $this->sanitize_quality( $_POST['quality'] ?? 'standard' );
		wp_send_json_success( $this->music_api_request( $this->api_path( 'resource', array( 'track_id' => $track_id, 'quality' => $quality ), '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $quality ) ) ) );
	}

	public function ajax_qq_stream(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_REQUEST['track_id'] ?? '' ) );
		$quality = $this->sanitize_quality( $_REQUEST['quality'] ?? 'standard' );
		$data = $this->music_api_request( $this->api_path( 'resource', array( 'track_id' => $track_id, 'quality' => $quality ), '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $quality ) ) );
		if ( empty( $data['available'] ) || empty( $data['url'] ) ) { status_header( 404 ); wp_die( esc_html( $data['message'] ?? '当前音质不可用。' ) ); }
		wp_redirect( esc_url_raw( $data['url'] ), 302, 'Music Library Manager' );
		exit;
	}

	public function ajax_qq_lyrics(): void {
		$this->check_music_ajax();
		$track_id = sanitize_text_field( wp_unslash( $_POST['track_id'] ?? '' ) );
		if ( '' === $track_id ) { wp_send_json_error( array( 'message' => '歌曲标识无效。' ), 400 ); }
		$data = $this->music_api_request( $this->api_path( 'details', array( 'track_id' => $track_id ), '/api/details/' . rawurlencode( $track_id ) ) );
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
		$resource = $this->music_api_request( $this->api_path( 'resource', array( 'track_id' => $track_id, 'quality' => $quality ), '/api/resource/' . rawurlencode( $track_id ) . '?quality=' . rawurlencode( $quality ) ) );
		if ( empty( $resource['available'] ) || empty( $resource['url'] ) ) { wp_send_json_error( array( 'message' => sanitize_text_field( $resource['message'] ?? '当前音质不可用。' ) ), 422 ); }
		$reuse_audio_id = absint( $_POST['reuse_duplicate_id'] ?? 0 );
		if ( $reuse_audio_id && ( 'attachment' !== get_post_type( $reuse_audio_id ) || 0 !== strpos( (string) get_post_mime_type( $reuse_audio_id ), 'audio/' ) ) ) { wp_send_json_error( array( 'message' => '要引用的媒体附件无效。' ), 400 ); }
		if ( ! $reuse_audio_id ) {
			$duplicate_id = $this->find_duplicate_remote_audio( esc_url_raw( $resource['url'] ) );
			if ( is_wp_error( $duplicate_id ) ) { wp_send_json_error( array( 'message' => '音频校验失败：' . $duplicate_id->get_error_message() ), 502 ); }
			if ( $duplicate_id ) { wp_send_json_error( array( 'duplicate' => true, 'attachment_id' => $duplicate_id, 'attachment_title' => get_the_title( $duplicate_id ), 'attachment_url' => wp_get_attachment_url( $duplicate_id ), 'message' => '媒体库中已有内容完全相同的音频文件。是否引用已有文件继续导入？' ), 409 ); }
		}
		$details = $this->music_api_request( $this->api_path( 'details', array( 'track_id' => $track_id ), '/api/details/' . rawurlencode( $track_id ) ) );
		$lyrics = (string) ( $details['lyrics'] ?? '' );
		$post_id = ! empty( $_POST['bulk'] ) ? 0 : absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			$post_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => sanitize_text_field( $item['title'] ) ), true );
			if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 ); }
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) { wp_send_json_error( array( 'message' => '无权编辑当前歌曲。' ), 403 ); }
		wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $item['title'] ), 'post_status' => 'publish' ) );
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
		$audio_id = $reuse_audio_id ?: $this->sideload_asset( esc_url_raw( $resource['url'] ), $post_id, sanitize_text_field( $item['title'] ), 'audio' );
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
		wp_send_json_success( array( 'message' => $reuse_audio_id ? '已添加歌曲并引用媒体库中的相同音频文件。' : '已添加到音乐库并导入媒体文件。', 'reused_audio' => (bool) $reuse_audio_id, 'post_id' => $post_id, 'edit_url' => get_edit_post_link( $post_id, 'raw' ), 'playlist_edit_url' => $playlist_edit_url, 'audio_url' => wp_get_attachment_url( $audio_id ), 'cover_url' => $cover_id && ! is_wp_error( $cover_id ) ? wp_get_attachment_url( $cover_id ) : '', 'lyrics_url' => $lyrics_id && ! is_wp_error( $lyrics_id ) ? wp_get_attachment_url( $lyrics_id ) : '', 'lyrics' => $lyrics ) );
	}

	private function find_duplicate_remote_audio( string $url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return $tmp; }
		$settings = $this->settings();
		if ( filesize( $tmp ) > (int) $settings['max_audio_mb'] * MB_IN_BYTES ) { wp_delete_file( $tmp ); return new WP_Error( 'mlm_file_too_large', '远程文件超过允许大小。' ); }
		$duplicate_id = $this->find_duplicate_audio_file( $tmp ); wp_delete_file( $tmp );
		return $duplicate_id;
	}

	private function find_duplicate_audio_file( string $file_path ) {
		$size = filesize( $file_path ); $hash = hash_file( 'sha256', $file_path );
		if ( ! $hash ) { return new WP_Error( 'mlm_hash_failed', '无法计算音频文件指纹。' ); }
		$known = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'audio', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_mlm_file_sha256', 'meta_value' => $hash ) );
		if ( $known ) { return (int) $known[0]; }
		$candidates = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'audio', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		foreach ( $candidates as $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! is_readable( $file ) || filesize( $file ) !== $size ) { continue; }
			$candidate_hash = (string) get_post_meta( $attachment_id, '_mlm_file_sha256', true );
			if ( ! $candidate_hash ) { $candidate_hash = (string) hash_file( 'sha256', $file ); if ( $candidate_hash ) { update_post_meta( $attachment_id, '_mlm_file_sha256', $candidate_hash ); } }
			if ( hash_equals( $hash, $candidate_hash ) ) { return (int) $attachment_id; }
		}
		return 0;
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
		wp_register_style( 'mlm-player-layout-fix', MLM_URL . 'assets/css/player-layout-fix.css', array( 'mlm-player' ), MLM_VERSION );
		wp_register_script( 'mlm-aplayer', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
		wp_register_script( 'mlm-player', MLM_URL . 'assets/js/player.js', array( 'mlm-aplayer' ), MLM_VERSION, true );
	}

	private function enqueue_front_assets(): void {
		wp_enqueue_style( 'mlm-player' );
		wp_enqueue_style( 'mlm-player-layout-fix' );
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
		$audio = array();
		foreach ( $query->posts as $post ) {
			$url = $this->attachment_url( $post->ID, 'audio' );
			if ( ! $url ) { continue; }
			$audio[] = array( 'name' => wp_strip_all_tags( get_the_title( $post ) ), 'artist' => wp_strip_all_tags( (string) get_post_meta( $post->ID, '_mlm_artist', true ) ), 'url' => esc_url_raw( $url ), 'cover' => esc_url_raw( $this->attachment_url( $post->ID, 'cover' ) ), 'lrc' => sanitize_textarea_field( (string) get_post_meta( $post->ID, '_mlm_lyrics', true ) ) );
		}
		if ( ! $audio ) { return '<p>该播放列表暂无可播放音频。</p>'; }
		$this->enqueue_front_assets();
		return '<section class="mlm-playlist"><h3>' . esc_html( $term->name ) . '</h3><article class="mlm-player mlm-playlist-player"><div class="mlm-aplayer" data-mlm-audio="' . esc_attr( wp_json_encode( $audio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"></div></article></section>';
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
			'mlm_artist' => '作者', 'mlm_album' => '专辑名称', 'mlm_category' => '分类', 'mlm_actions' => '操作',
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
			if ( current_user_can( 'delete_post', $post_id ) ) { printf( ' | <a class="submitdelete" href="%s" onclick="return window.confirm(\'永久删除这首歌曲以及它独占的音频、封面和歌词文件？此操作无法撤销。\');">永久删除歌曲及文件</a>', esc_url( get_delete_post_link( $post_id, '', true ) ) ); }
		}
	}

	public function row_actions( array $actions, WP_Post $post ): array {
		return self::POST_TYPE === $post->post_type ? array() : $actions;
	}

	public function hide_track_post_states( array $states, WP_Post $post ): array {
		return self::POST_TYPE === $post->post_type ? array() : $states;
	}
}
