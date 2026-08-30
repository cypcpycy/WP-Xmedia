<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MLM_Plugin_V9 {
	private static $instance;
	private $keep_track_media_on_delete = false;
	private $deferred_players = array();
	private $deferred_player_counter = 0;
	private $deferred_player_secret = '';
	private $track_reference_cache = null;
	private const POST_TYPE = 'mlm_track';
	private const TAXONOMY = 'mlm_music_category';
	private const PLAYLIST_TAXONOMY = 'mlm_playlist';
	private const OPTION = 'mlm_settings';
	private const API_RULE_OPTION = 'mlm_api_rule';
	private const META_KEYS = array( 'artist', 'album', 'audio_url', 'cover_url', 'lyrics', 'lyrics_url', 'source_url' );

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
		add_action( 'wp_ajax_mlm_import_hermit_item', array( $this, 'ajax_import_hermit_item' ) );
		add_action( 'wp_ajax_mlm_filter_hermit_records', array( $this, 'ajax_filter_hermit_records' ) );
		add_action( 'wp_ajax_mlm_upload_asset_item', array( $this, 'ajax_upload_asset_item' ) );
		add_action( 'wp_ajax_mlm_qq_status', array( $this, 'ajax_qq_status' ) );
		add_action( 'wp_ajax_mlm_qq_login_start', array( $this, 'ajax_qq_login_start' ) );
		add_action( 'wp_ajax_mlm_qq_login_poll', array( $this, 'ajax_qq_login_poll' ) );
		add_action( 'wp_ajax_mlm_qq_logout', array( $this, 'ajax_qq_logout' ) );
		add_action( 'wp_ajax_mlm_qq_stream', array( $this, 'ajax_qq_stream' ) );
		add_action( 'wp_ajax_mlm_qq_lyrics', array( $this, 'ajax_qq_lyrics' ) );
		add_action( 'wp_ajax_mlm_player_data', array( $this, 'ajax_player_data' ) );
		add_action( 'wp_ajax_nopriv_mlm_player_data', array( $this, 'ajax_player_data' ) );
		add_shortcode( 'music', array( $this, 'single_shortcode' ) );
		add_shortcode( 'music_list', array( $this, 'list_shortcode' ) );
		add_shortcode( 'music_playlist', array( $this, 'playlist_shortcode' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'track_reference_views' ) );
		add_action( 'restrict_manage_posts', array( $this, 'track_reference_dropdown' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_tracks_by_reference' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'hide_track_post_states' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'allow_lyrics_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'normalize_lyrics_filetype' ), 10, 5 );
		add_action( 'add_attachment', array( $this, 'maybe_link_lyrics_attachment' ) );
		add_action( 'delete_attachment', array( $this, 'clear_deleted_lyrics_attachment' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'redirect_playlist_taxonomy_page' ) );
		add_action( 'admin_post_mlm_import_api_rule', array( $this, 'import_api_rule' ) );
		add_action( 'admin_post_mlm_import_hermit_json', array( $this, 'import_hermit_json' ) );
		add_action( 'admin_post_mlm_bulk_upload_assets', array( $this, 'bulk_upload_assets' ) );
		add_action( 'wp_ajax_mlm_bulk_upload_assets_batch', array( $this, 'bulk_upload_assets' ) );
		add_action( 'admin_post_mlm_save_playlist_tracks', array( $this, 'save_playlist_tracks' ) );
		add_action( 'admin_post_mlm_save_playlist_name', array( $this, 'save_playlist_name' ) );
		add_action( 'admin_post_mlm_delete_playlist', array( $this, 'delete_playlist' ) );
		add_action( 'admin_post_mlm_delete_track', array( $this, 'delete_track' ) );
		add_action( 'admin_post_mlm_migrate_hermit_shortcodes', array( $this, 'migrate_hermit_shortcodes' ) );
		add_action( 'admin_post_mlm_organize_lyrics', array( $this, 'organize_lyrics' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_track_delete_actions' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'tiny_mce_before_init', array( $this, 'classic_editor_mce_init' ) );
		add_filter( 'content_save_pre', array( $this, 'restore_classic_previews' ), 9, 1 );
		add_filter( 'the_content', array( $this, 'restore_classic_previews' ), 8, 1 );
		add_action( 'edit_form_after_title', array( $this, 'render_editor_hint' ) );
		add_filter( 'the_content', array( $this, 'append_player_to_track_page' ), PHP_INT_MAX, 1 );
		add_filter( 'the_content', array( $this, 'resolve_deferred_players' ), PHP_INT_MAX, 1 );
	}

	private function maybe_defer_player( string $html ): string {
		if ( '' === $html || ! doing_filter( 'the_content' ) ) { return $html; }
		if ( '' === $this->deferred_player_secret ) {
			$this->deferred_player_secret = wp_generate_uuid4() . '|' . microtime( true ) . '|' . wp_rand();
		}
		$this->deferred_player_counter++;
		$digest = hash_hmac( 'sha256', (string) $this->deferred_player_counter, $this->deferred_player_secret );
		$token = '<!--mlm-deferred-player-' . $digest . '-->';
		$this->deferred_players[ $token ] = $html;
		return $token;
	}

	public function resolve_deferred_players( string $content ): string {
		if ( ! $this->deferred_players ) { return $content; }
		$content = strtr( $content, $this->deferred_players );
		$this->deferred_players = array();
		return $content;
	}

	/** Convert visual-only Classic Editor cards back into plugin shortcodes. */
	public function restore_classic_previews( string $content ): string {
		if ( false === strpos( $content, 'mlm-classic-preview' ) || ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}
		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded = $document->loadHTML( '<?xml encoding="UTF-8"><div id="mlm-classic-preview-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) { return $content; }
		$xpath = new DOMXPath( $document );
		$nodes = $xpath->query( '//span[contains(concat(" ", normalize-space(@class), " "), " mlm-classic-preview ")]' );
		if ( ! $nodes || 0 === $nodes->length ) { return $content; }
		foreach ( iterator_to_array( $nodes ) as $node ) {
			$kind = strtolower( trim( $node->getAttribute( 'data-mlm-kind' ) ) );
			$id = absint( $node->getAttribute( 'data-mlm-id' ) );
			if ( ! $id || ! in_array( $kind, array( 'track', 'playlist' ), true ) ) { continue; }
			$autoplay = $this->shortcode_bool( $node->getAttribute( 'data-mlm-autoplay' ) ) ? 'yes' : 'no';
			if ( 'playlist' === $kind ) {
				$shortcode = sprintf( '[music_playlist id="%d" autoplay="%s"]', $id, $autoplay );
			} else {
				$lyrics = $node->hasAttribute( 'data-mlm-lyrics' ) && ! $this->shortcode_bool( $node->getAttribute( 'data-mlm-lyrics' ) ) ? 'no' : 'yes';
				$shortcode = sprintf( '[music id="%d" lyrics="%s" autoplay="%s"]', $id, $lyrics, $autoplay );
			}
			$node->parentNode->replaceChild( $document->createTextNode( $shortcode ), $node );
		}
		$root = $document->getElementById( 'mlm-classic-preview-root' );
		if ( ! $root ) { return $content; }
		$result = '';
		foreach ( $root->childNodes as $child ) { $result .= $document->saveHTML( $child ); }
		return $result;
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
					'autoplay' => array( 'type' => 'boolean', 'default' => false ),
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
				'attributes' => array(
					'playlistId' => array( 'type' => 'integer', 'default' => 0 ),
					'autoplay' => array( 'type' => 'boolean', 'default' => false ),
				),
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
			$item = array(
				'id' => $post->ID, 'title' => get_the_title( $post ),
				'artist' => (string) get_post_meta( $post->ID, '_mlm_artist', true ),
				'album' => (string) get_post_meta( $post->ID, '_mlm_album', true ),
				'cover' => $this->attachment_url( $post->ID, 'cover' ),
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
				$ids = $this->playlist_track_ids( (int) $term->term_id );
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
				'autoplay' => empty( $attributes['autoplay'] ) ? 'no' : 'yes',
			)
		);
	}

	public function render_playlist_block( array $attributes ): string {
		$playlist_id = absint( $attributes['playlistId'] ?? 0 );
		return $playlist_id ? $this->playlist_shortcode( array(
			'id' => $playlist_id,
			'autoplay' => empty( $attributes['autoplay'] ) ? 'no' : 'yes',
		) ) : '';
	}

	public function append_player_to_track_page( string $content ): string {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id || has_shortcode( $content, 'music' ) || has_block( 'mlm/music-player', $content ) ) {
			return $content;
		}
		$player = $this->single_shortcode( array( 'id' => $post_id, 'lyrics' => 'yes', 'autoplay' => 'no' ) );
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
			'lyrics_url' => array( '歌词文件 URL', 'url' ),
		);
		echo '<div class="mlm-fields">';
		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, '_mlm_' . $key, true );
			$media_actions = in_array( $key, array( 'audio_url', 'cover_url', 'lyrics_url' ), true ) ? '<button type="button" class="button mlm-media-button" data-target="mlm_' . esc_attr( $key ) . '" data-type="' . esc_attr( $key ) . '">媒体库</button>' : '';
			printf( '<p><label for="mlm_%1$s"><strong>%2$s</strong></label><span class="mlm-input-row"><input class="widefat" type="%3$s" id="mlm_%1$s" name="mlm[%1$s]" value="%4$s">%5$s</span></p>', esc_attr( $key ), esc_html( $field[0] ), esc_attr( $field[1] ), esc_attr( $value ), $media_actions );
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
		if ( $this->keep_track_media_on_delete ) { return; }
		$attachment_ids = array_filter( array_map( 'absint', array(
			get_post_meta( $post_id, '_mlm_audio_attachment_id', true ),
			get_post_meta( $post_id, '_mlm_cover_attachment_id', true ),
			get_post_meta( $post_id, '_mlm_lyrics_attachment_id', true ),
			get_post_thumbnail_id( $post_id ),
		) ) );
		foreach ( array_unique( $attachment_ids ) as $attachment_id ) {
			$used_elsewhere = get_posts( array(
				'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
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

	public function delete_track(): void {
		$post_id = absint( $_REQUEST['post_id'] ?? 0 );
		$mode = sanitize_key( wp_unslash( $_REQUEST['delete_mode'] ?? '' ) );
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( '歌曲记录不存在。', '', array( 'response' => 404 ) );
		}
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( '权限不足。', '', array( 'response' => 403 ) );
		}
		if ( ! in_array( $mode, array( 'record_only', 'record_and_files' ), true ) ) {
			wp_die( '删除方式无效。', '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'mlm_delete_track_' . $post_id . '_' . $mode );
		$this->keep_track_media_on_delete = 'record_only' === $mode;
		$deleted = wp_delete_post( $post_id, true );
		$this->keep_track_media_on_delete = false;
		if ( ! $deleted ) {
			wp_die( '歌曲记录删除失败。', '', array( 'response' => 500 ) );
		}
		$message = 'record_only' === $mode
			? '歌曲记录已永久删除，音频、封面和歌词文件均已保留。'
			: '歌曲记录已永久删除，其未被其他歌曲引用的媒体文件也已删除。';
		wp_safe_redirect( add_query_arg( array(
			'post_type' => self::POST_TYPE,
			'mlm_track_delete_message' => $message,
		), admin_url( 'edit.php' ) ) );
		exit;
	}

	private function track_delete_url( int $post_id, string $mode ): string {
		return wp_nonce_url(
			add_query_arg( array(
				'action' => 'mlm_delete_track',
				'post_id' => $post_id,
				'delete_mode' => $mode,
			), admin_url( 'admin-post.php' ) ),
			'mlm_delete_track_' . $post_id . '_' . $mode
		);
	}

	public function render_track_delete_actions(): void {
		global $post;
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || ! current_user_can( 'delete_post', $post->ID ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		echo '<div class="misc-pub-section mlm-track-delete-actions"><strong>永久删除歌曲</strong><p style="margin-bottom:6px"><a class="submitdelete" href="' . esc_url( $this->track_delete_url( $post->ID, 'record_only' ) ) . '" onclick="return window.confirm(\'只永久删除歌曲记录，音频、封面和歌词文件都会保留。确定继续吗？\');">只删除记录，保留文件</a></p><p style="margin-bottom:0"><a class="submitdelete" href="' . esc_url( $this->track_delete_url( $post->ID, 'record_and_files' ) ) . '" onclick="return window.confirm(\'永久删除歌曲记录，并删除未被其他歌曲引用的音频、封面和歌词文件？此操作无法撤销。\');">删除记录及独占文件</a></p></div>';
	}

	private function render_attachment_status( int $post_id ): void {
		$labels = array( 'cover' => '封面附件', 'audio' => '音频附件', 'lyrics' => '歌词附件' );
		echo '<div class="mlm-media-status"><strong>媒体库状态</strong><ul>';
		foreach ( $labels as $type => $label ) {
			$status = $this->media_file_status( $post_id, $type );
			if ( $status['exists'] ) {
				printf( '<li>%s：<span class="mlm-file-ok">%s</span></li>', esc_html( $label ), esc_html( $status['filename'] ) );
			} else {
				printf( '<li>%s：<strong class="mlm-file-missing" style="color:#b32d2e">缺文件</strong></li>', esc_html( $label ) );
			}
		}
		echo '</ul></div>';
	}

	private function media_file_status( int $post_id, string $type ): array {
		$attachment_id = absint( get_post_meta( $post_id, '_mlm_' . $type . '_attachment_id', true ) );
		if ( $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( $file && is_file( $file ) && is_readable( $file ) ) { return array( 'exists' => true, 'filename' => wp_basename( $file ) ); }
			return array( 'exists' => false, 'filename' => '' );
		}
		$url = esc_url_raw( (string) get_post_meta( $post_id, '_mlm_' . $type . '_url', true ) );
		if ( ! $url ) { return array( 'exists' => false, 'filename' => '' ); }
		$uploads = wp_upload_dir();
		if ( empty( $uploads['error'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
			$relative = ltrim( rawurldecode( substr( $url, strlen( $uploads['baseurl'] ) ) ), '/' );
			$file = trailingslashit( $uploads['basedir'] ) . wp_normalize_path( $relative );
			return array( 'exists' => is_file( $file ) && is_readable( $file ), 'filename' => wp_basename( $file ) );
		}
		$response = wp_remote_head( $url, array( 'timeout' => 3, 'redirection' => 2 ) );
		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		return array( 'exists' => $code >= 200 && $code < 400, 'filename' => wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
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

	public function normalize_lyrics_filetype( array $data, string $file, string $filename, $mimes = null, $real_mime = null ): array {
		if ( 'lrc' === strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			$data['ext'] = 'lrc';
			$data['type'] = 'text/plain';
			$data['proper_filename'] = false;
		}
		return $data;
	}

	public function maybe_link_lyrics_attachment( int $attachment_id ): int {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return 0;
		}
		$file_path = (string) get_attached_file( $attachment_id );
		if ( '' === $file_path ) {
			$file_path = (string) wp_parse_url( (string) get_post_field( 'guid', $attachment_id ), PHP_URL_PATH );
		}
		$file_name = sanitize_file_name( rawurldecode( wp_basename( $file_path ) ) );
		if ( 'lrc' !== strtolower( (string) pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
			return 0;
		}
		$attachment_url = esc_url_raw( (string) wp_get_attachment_url( $attachment_id ) );
		if ( '' === $attachment_url ) {
			return 0;
		}
		$lyrics_fingerprint = $this->lyrics_file_fingerprint( $file_path );
		if ( '' === $lyrics_fingerprint ) { return 0; }
		$track_ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$candidates = array();
		foreach ( $track_ids as $track_id ) {
			if ( $lyrics_fingerprint === $this->music_track_fingerprint( (int) $track_id ) ) { $candidates[] = (int) $track_id; }
		}
		if ( 1 !== count( $candidates ) ) { return 0; }
		$track_id = (int) $candidates[0];
		update_post_meta( $track_id, '_mlm_lyrics_attachment_id', $attachment_id );
		update_post_meta( $track_id, '_mlm_lyrics_url', $attachment_url );
		return 1;
	}

	/** Compare only the LRC filename stem with the music-library track title. */
	private function normalized_lyrics_title_key( string $value ): string {
		$value = rawurldecode( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$value = preg_replace( '/\.lrc$/iu', '', trim( $value ) );
		if ( class_exists( 'Normalizer' ) ) {
			$normalized = Normalizer::normalize( $value, Normalizer::FORM_KC );
			if ( false !== $normalized ) { $value = $normalized; }
		}
		$value = mb_strtolower( $value, 'UTF-8' );
		return (string) preg_replace( '/[\p{P}\p{S}\p{Z}\s_]+/u', '', $value );
	}

	private function lyrics_file_fingerprint( string $lyrics_path ): string {
		$content = is_file( $lyrics_path ) ? (string) file_get_contents( $lyrics_path, false, null, 0, 1048576 ) : '';
		$tags = array();
		foreach ( array( 'ti', 'ar', 'al' ) as $tag ) {
			if ( ! preg_match( '/^\s*\[' . $tag . '\s*:\s*(.*?)\]\s*$/miu', $content, $match ) ) { return ''; }
			$tags[] = $this->normalized_lyrics_title_key( $match[1] );
		}
		$duration = $this->lyrics_declared_duration( $content );
		if ( in_array( '', $tags, true ) || 0 >= $duration ) { return ''; }
		return hash( 'sha256', implode( '|', array_merge( $tags, array( (string) $duration ) ) ) );
	}

	private function music_track_fingerprint( int $track_id ): string {
		$metadata = wp_get_attachment_metadata( absint( get_post_meta( $track_id, '_mlm_audio_attachment_id', true ) ) );
		$duration = is_array( $metadata ) ? (int) round( (float) ( $metadata['length'] ?? 0 ) ) : 0;
		$parts = array(
			$this->normalized_lyrics_title_key( get_the_title( $track_id ) ),
			$this->normalized_lyrics_title_key( (string) get_post_meta( $track_id, '_mlm_artist', true ) ),
			$this->normalized_lyrics_title_key( (string) get_post_meta( $track_id, '_mlm_album', true ) ),
		);
		if ( in_array( '', $parts, true ) || 0 >= $duration ) { return ''; }
		return hash( 'sha256', implode( '|', array_merge( $parts, array( (string) $duration ) ) ) );
	}

	private function lyrics_declared_duration( string $content ): int {
		if ( ! preg_match( '/\[length\s*:\s*(\d+):([0-5]?\d(?:\.\d+)?)\]/iu', $content, $length ) ) { return 0; }
		return (int) round( ( (float) $length[1] * 60 ) + (float) $length[2] );
	}

	public function clear_deleted_lyrics_attachment( int $attachment_id ): void {
		$track_ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mlm_lyrics_attachment_id', 'meta_value' => $attachment_id ) );
		foreach ( $track_ids as $track_id ) {
			delete_post_meta( $track_id, '_mlm_lyrics_attachment_id' );
			delete_post_meta( $track_id, '_mlm_lyrics_url' );
		}
	}

	public function admin_notices(): void {
		if ( isset( $_GET['mlm_track_delete_message'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_track_delete_message'] ) ) ) . '</p></div>';
		}
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
			wp_enqueue_script( 'mlm-classic-editor', MLM_URL . 'assets/js/classic-editor-v0144.js', array( 'jquery' ), MLM_VERSION, true );
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
		wp_enqueue_script( 'mlm-admin', MLM_URL . 'assets/js/admin-v0149.js', array( 'jquery', 'wp-data', 'mlm-aplayer-admin' ), MLM_VERSION, true );
		wp_localize_script( 'mlm-admin', 'mlmAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mlm_search_music' ), 'postId' => get_the_ID() ) );
	}

	public function admin_menu(): void {
		remove_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'edit-tags.php?taxonomy=' . self::PLAYLIST_TAXONOMY . '&amp;post_type=' . self::POST_TYPE );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '音乐播放列表', '音乐播放列表', 'edit_posts', 'mlm-playlists', array( $this, 'render_playlists_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '音乐库设置', '设置', 'manage_options', 'mlm-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '批量导入媒体', '批量导入媒体', 'manage_options', 'mlm-bulk-media', array( $this, 'render_bulk_media_page' ) );
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, '批量转换文章短代码', '转换文章短代码', 'edit_posts', 'mlm-shortcode-migration', array( $this, 'render_shortcode_migration_page' ) );
	}

	public function render_shortcode_migration_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) { return; }
		global $wpdb;
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) ); $per_page = 30; $offset = ( $page - 1 ) * $per_page;
		$like = '%[hermit%';
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s", $like ) );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s ORDER BY ID DESC LIMIT %d OFFSET %d", $like, $per_page, $offset ) );
		echo '<div class="wrap"><h1>批量转换文章短代码</h1><p>把文章中的 Hermit 音乐短代码安全转换为 WP-Xmedia 单曲或播放列表。多首歌曲会自动创建歌单；只有全部旧歌曲 ID 都能映射的文章才会转换，首次转换前会完整备份原正文。自动播放设置会逐条继承原 Hermit 短代码。</p>';
		if ( isset( $_GET['mlm_migration_result'] ) ) { echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_migration_result'] ) ) ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_migrate_hermit_shortcodes">'; wp_nonce_field( 'mlm_migrate_hermit_shortcodes' );
		echo '<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" id="mlm-select-all-migrations"></td><th>文章</th><th>旧短代码摘要</th><th>映射情况</th><th>状态</th></tr></thead><tbody>';
		foreach ( $ids as $post_id ) {
			$post = get_post( (int) $post_id ); if ( ! $post ) { continue; }
			$analysis = $this->analyze_hermit_content( $post->post_content );
			$summary = implode( ' ', array_map( static function ( $item ) { return wp_html_excerpt( $item, 100, '…' ); }, $analysis['shortcodes'] ) );
			$backup = metadata_exists( 'post', $post->ID, '_mlm_shortcode_migration_backup' );
			$modes = array_map( static function ( array $plan ): string { return ( count( $plan['track_ids'] ) > 1 ? '将创建歌单（' . count( $plan['track_ids'] ) . '首）' : '单曲转换' ) . '，自动播放：' . ( $plan['autoplay'] ? '是' : '否' ); }, $analysis['plans'] );
			$details = array_merge( $modes, $analysis['issues'] );
			echo '<tr><th class="check-column"><input class="mlm-migration-checkbox" type="checkbox" name="post_ids[]" value="' . (int) $post->ID . '"></th><td><strong><a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ?: '(无标题)' ) . '</a></strong><br><small>#' . (int) $post->ID . '</small></td><td><code>' . esc_html( $summary ) . '</code></td><td>可映射 ' . (int) $analysis['mapped'] . '，缺失 ' . (int) $analysis['missing'] . '<br><small>' . esc_html( implode( '；', $details ) ) . '</small></td><td>' . ( $analysis['complete'] ? '<span style="color:#008a20">可转换</span>' : '<strong style="color:#b32d2e">映射不完整，将保留原文并跳过</strong>' ) . ( $backup ? '<br><small>已有原文备份</small>' : '' ) . '</td></tr>';
		}
		if ( ! $ids ) { echo '<tr><td colspan="5">没有找到包含 Hermit 短代码的文章。</td></tr>'; }
		echo '</tbody></table><p><button type="submit" class="button button-primary">转换选中文章</button></p></form>';
		$total_pages = max( 1, (int) ceil( $total / $per_page ) ); if ( $total_pages > 1 ) { echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-shortcode-migration', 'paged' => '%#%' ), admin_url( 'edit.php' ) ), 'format' => '', 'current' => $page, 'total' => $total_pages ) ) ) . '</div></div>'; }
		echo '<script>document.getElementById("mlm-select-all-migrations")?.addEventListener("change",function(){document.querySelectorAll(".mlm-migration-checkbox").forEach((box)=>box.checked=this.checked);});</script></div>';
	}

	public function migrate_hermit_shortcodes(): void {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mlm_migrate_hermit_shortcodes' );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['post_ids'] ?? array() ) ) ) ) );
		$converted = 0; $skipped = 0; $failed = 0;
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'post' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) { $failed++; continue; }
			$analysis = $this->analyze_hermit_content( $post->post_content );
			if ( ! $analysis['complete'] || ! $analysis['shortcodes'] ) { $skipped++; continue; }
			if ( ! metadata_exists( 'post', $post_id, '_mlm_shortcode_migration_backup' ) ) { add_post_meta( $post_id, '_mlm_shortcode_migration_backup', $post->post_content, true ); }
			$created_terms = array(); $touched_terms = array(); $playlist_ids = array(); $replacements = array(); $setup_failed = false;
			foreach ( $analysis['plans'] as $plan ) {
				if ( 1 === count( $plan['track_ids'] ) ) { $replacements[ $plan['token'] ] = '[music id="' . (int) $plan['track_ids'][0] . '" lyrics="yes" autoplay="' . ( $plan['autoplay'] ? 'yes' : 'no' ) . '"]'; continue; }
				$term_id = $this->find_migration_playlist( $post_id, $plan['signature'] );
				if ( ! $term_id ) {
					$name = $this->migration_playlist_name( $post, $plan['index'], count( $analysis['plans'] ) );
					$inserted = wp_insert_term( $name, self::PLAYLIST_TAXONOMY );
					if ( is_wp_error( $inserted ) ) { $setup_failed = true; break; }
					$term_id = (int) $inserted['term_id']; $created_terms[] = $term_id;
					update_term_meta( $term_id, '_mlm_migration_source_post', $post_id ); update_term_meta( $term_id, '_mlm_migration_shortcode_index', $plan['index'] ); update_term_meta( $term_id, '_mlm_migration_signature', $plan['signature'] );
				} else {
					$touched_terms[ $term_id ] = array( 'tracks' => array_map( 'intval', (array) get_objects_in_term( $term_id, self::PLAYLIST_TAXONOMY ) ), 'order' => get_term_meta( $term_id, '_mlm_track_order', true ) );
				}
				$this->set_playlist_tracks( $term_id, $plan['track_ids'] ); $playlist_ids[] = $term_id;
				$replacements[ $plan['token'] ] = '[music_playlist id="' . $term_id . '" autoplay="' . ( $plan['autoplay'] ? 'yes' : 'no' ) . '"]';
			}
			if ( $setup_failed ) { $this->rollback_migration_playlists( $created_terms, $touched_terms ); $failed++; continue; }
			$content = strtr( $analysis['converted_content'], $replacements );
			$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ), true );
			if ( is_wp_error( $result ) ) { $this->rollback_migration_playlists( $created_terms, $touched_terms ); $failed++; } else { update_post_meta( $post_id, '_mlm_migration_playlist_ids', array_values( array_unique( $playlist_ids ) ) ); $converted++; }
		}
		$message = sprintf( '处理完成：已转换 %d 篇，因映射不完整跳过 %d 篇，失败 %d 篇。', $converted, $skipped, $failed );
		wp_safe_redirect( add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-shortcode-migration', 'mlm_migration_result' => $message ), admin_url( 'edit.php' ) ) ); exit;
	}

	private function analyze_hermit_content( string $content ): array {
		$shortcodes = array(); $plans = array(); $issues = array(); $mapped = 0; $missing = 0; $complete = true; $index = 0;
		$converted = preg_replace_callback( '/\[hermit\b([^\]]*)\]([\s\S]*?)\[\/hermit\]/i', function ( array $match ) use ( &$shortcodes, &$plans, &$issues, &$mapped, &$missing, &$complete, &$index ) {
			$index++; $shortcodes[] = $match[0]; $legacy_ids = $this->extract_hermit_remote_ids( $match[2] );
			if ( ! $legacy_ids ) { $missing++; $complete = false; $issues[] = '第 ' . $index . ' 个短代码没有可识别的 remote#:ID'; return $match[0]; }
			$autoplay = preg_match( '/\bautoplay\s*=\s*["\']?([^"\'\s\]]+)/i', $match[1], $auto_match ) && $this->shortcode_bool( $auto_match[1] );
			$track_ids = array();
			foreach ( $legacy_ids as $legacy_id ) {
				$matches = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 2, 'fields' => 'ids', 'meta_key' => '_mlm_hermit_legacy_id', 'meta_value' => (string) $legacy_id ) );
				if ( 1 !== count( $matches ) ) { $missing++; $complete = false; $issues[] = '第 ' . $index . ' 个短代码：旧 ID ' . $legacy_id . ( count( $matches ) ? ' 存在多个音乐库映射' : ' 在音乐库中无映射' ); continue; }
				$mapped++; $track_ids[] = (int) $matches[0];
			}
			$token = '%%MLM_MIGRATION_' . $index . '_' . hash( 'sha256', implode( ',', $legacy_ids ) ) . '%%';
			if ( count( $track_ids ) === count( $legacy_ids ) ) { $plans[] = array( 'index' => $index, 'legacy_ids' => $legacy_ids, 'track_ids' => $track_ids, 'autoplay' => $autoplay, 'signature' => hash( 'sha256', $index . '|' . implode( ',', $legacy_ids ) ), 'token' => $token ); return $token; }
			return $match[0];
		}, $content );
		return array( 'shortcodes' => $shortcodes, 'plans' => $plans, 'issues' => $issues, 'mapped' => $mapped, 'missing' => $missing, 'complete' => $complete && ! empty( $shortcodes ), 'converted_content' => (string) $converted );
	}

	/** Extract every legacy track ID from Hermit's remote#:1,2,3 notation. */
	private function extract_hermit_remote_ids( string $shortcode_body ): array {
		$ids = array();
		foreach ( $this->match_all( '/remote#:\s*([0-9]+(?:\s*,\s*[0-9]+)*)/i', $shortcode_body ) as $match ) {
			foreach ( preg_split( '/\s*,\s*/', (string) $match[1] ) as $legacy_id ) {
				$legacy_id = absint( $legacy_id );
				if ( $legacy_id && ! in_array( $legacy_id, $ids, true ) ) { $ids[] = $legacy_id; }
			}
		}
		return $ids;
	}

	private function find_migration_playlist( int $post_id, string $signature ): int {
		$terms = get_terms( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'hide_empty' => false, 'number' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_mlm_migration_source_post', 'value' => $post_id ), array( 'key' => '_mlm_migration_signature', 'value' => $signature ) ) ) );
		return is_wp_error( $terms ) || ! $terms ? 0 : (int) $terms[0];
	}

	private function migration_playlist_name( WP_Post $post, int $index, int $total ): string {
		$base = trim( wp_strip_all_tags( get_the_title( $post ) ) ); if ( '' === $base ) { $base = '文章 ' . $post->ID; }
		$name = $total > 1 ? $base . ' - 歌单 ' . $index . '/' . $total : $base;
		if ( ! term_exists( $name, self::PLAYLIST_TAXONOMY ) ) { return $name; }
		$suffix = $post->ID . '-' . $index; $candidate = $name . ' - ' . $suffix; $counter = 2;
		while ( term_exists( $candidate, self::PLAYLIST_TAXONOMY ) ) { $candidate = $name . ' - ' . $suffix . '-' . $counter++; }
		return $candidate;
	}

	private function set_playlist_tracks( int $term_id, array $track_ids ): void {
		$track_ids = array_values( array_filter( array_map( 'absint', $track_ids ) ) );
		$current = array_map( 'intval', (array) get_objects_in_term( $term_id, self::PLAYLIST_TAXONOMY ) );
		foreach ( $current as $track_id ) { wp_remove_object_terms( $track_id, $term_id, self::PLAYLIST_TAXONOMY ); }
		foreach ( array_values( array_unique( $track_ids ) ) as $track_id ) { if ( self::POST_TYPE === get_post_type( $track_id ) ) { wp_set_object_terms( $track_id, array( $term_id ), self::PLAYLIST_TAXONOMY, true ); } }
		update_term_meta( $term_id, '_mlm_track_order', $track_ids );
	}

	private function rollback_migration_playlists( array $created_terms, array $touched_terms ): void {
		foreach ( $created_terms as $term_id ) { wp_delete_term( (int) $term_id, self::PLAYLIST_TAXONOMY ); }
		foreach ( $touched_terms as $term_id => $state ) { $this->set_playlist_tracks( (int) $term_id, (array) $state['tracks'] ); if ( '' === $state['order'] ) { delete_term_meta( (int) $term_id, '_mlm_track_order' ); } else { update_term_meta( (int) $term_id, '_mlm_track_order', $state['order'] ); } }
	}

	private function sort_playlist_posts( int $term_id, array $posts ): array {
		$order = array_values( array_filter( array_map( 'absint', (array) get_term_meta( $term_id, '_mlm_track_order', true ) ) ) );
		if ( ! $order ) { return $posts; }
		$map = array(); foreach ( $posts as $post ) { if ( $post instanceof WP_Post ) { $map[ $post->ID ] = $post; } }
		$sorted = array(); $seen = array(); foreach ( $order as $track_id ) { if ( isset( $map[ $track_id ] ) && ! isset( $seen[ $track_id ] ) ) { $sorted[] = $map[ $track_id ]; $seen[ $track_id ] = true; } }
		foreach ( $posts as $post ) { if ( $post instanceof WP_Post && ! isset( $seen[ $post->ID ] ) ) { $sorted[] = $post; } }
		return $sorted;
	}

	private function playlist_track_ids( int $term_id ): array {
		$members = array_values( array_map( 'intval', (array) get_objects_in_term( $term_id, self::PLAYLIST_TAXONOMY ) ) );
		$order = array_values( array_filter( array_map( 'absint', (array) get_term_meta( $term_id, '_mlm_track_order', true ) ) ) );
		if ( ! $order ) { return $members; }
		$allowed = array_fill_keys( $members, true ); $result = array(); $seen = array();
		foreach ( $order as $track_id ) { if ( isset( $allowed[ $track_id ] ) && ! isset( $seen[ $track_id ] ) ) { $result[] = $track_id; $seen[ $track_id ] = true; } }
		foreach ( $members as $track_id ) { if ( ! isset( $seen[ $track_id ] ) ) { $result[] = $track_id; } }
		return $result;
	}

	private function match_all( string $pattern, string $subject ): array {
		preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER ); return $matches;
	}

	public function render_bulk_media_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>批量导入媒体文件</h1><p>音频和封面文件只会加入 WordPress 媒体库，不自动关联歌曲；歌词文件会按文件名匹配歌曲，并在匹配成功后自动补齐歌词地址。</p><form id="mlm-bulk-media-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mlm_bulk_upload_assets">';
		if ( isset( $_GET['mlm_upload_result'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_upload_result'] ) ) ) . '</p></div>'; }
		wp_nonce_field( 'mlm_bulk_upload_assets' );
		echo '<table class="form-table"><tr><th>媒体类型</th><td><select name="asset_type"><option value="audio">音频文件</option><option value="cover">封面文件</option><option value="lyrics">歌词文件</option></select></td></tr><tr><th>选择多个文件</th><td><input type="file" name="mlm_asset_files[]" accept=".lrc,text/plain" multiple><p class="description">可一次选择多个文件，全部作为独立附件加入媒体库。</p></td></tr><tr><th>选择歌词文件夹</th><td><input type="file" name="mlm_asset_folder[]" accept=".lrc,text/plain" webkitdirectory directory multiple><p class="description">可选择包含多个歌词文件的文件夹；目录结构不用于关联歌曲。</p></td></tr></table>';
		submit_button( '上传并自动匹配' );
		echo '<div id="mlm-bulk-progress" style="display:none;max-width:760px;margin-top:18px"><div style="height:18px;background:#dcdcde;border-radius:3px;overflow:hidden"><div id="mlm-bulk-progress-bar" style="width:0;height:100%;background:#2271b1;transition:width .2s"></div></div><p id="mlm-bulk-progress-text">准备上传…</p></div></form>';
		echo '<script>(function(){const form=document.getElementById("mlm-bulk-media-form");if(!form)return;form.addEventListener("submit",async function(event){const inputs=[...form.querySelectorAll("input[type=file]")];const files=inputs.flatMap(input=>Array.from(input.files||[]));if(files.length<=10)return;event.preventDefault();const button=form.querySelector("button[type=submit],input[type=submit]");const box=document.getElementById("mlm-bulk-progress"),bar=document.getElementById("mlm-bulk-progress-bar"),text=document.getElementById("mlm-bulk-progress-text");button.disabled=true;box.style.display="block";let done=0,uploaded=0,matched=0,skipped=0,failed=0;try{for(let offset=0;offset<files.length;offset+=10){const batch=files.slice(offset,offset+10),data=new FormData();data.append("action","mlm_bulk_upload_assets_batch");data.append("mlm_ajax","1");data.append("_wpnonce",form.querySelector("input[name=_wpnonce]").value);data.append("asset_type",form.querySelector("select[name=asset_type]").value);batch.forEach(file=>data.append("mlm_asset_files[]",file,file.name));const response=await fetch(ajaxurl,{method:"POST",body:data,credentials:"same-origin"});const payload=await response.json();if(!payload.success)throw new Error(payload.data&&payload.data.message?payload.data.message:"分批上传失败");uploaded+=Number(payload.data.uploaded||0);matched+=Number(payload.data.matched||0);skipped+=Number(payload.data.skipped||0);failed+=Number(payload.data.failed||0);done+=batch.length;bar.style.width=Math.round(done/files.length*100)+"%";text.textContent=`总计 ${files.length} 个；已处理 ${done} 个，上传 ${uploaded} 个，匹配 ${matched} 条，跳过 ${skipped} 个，失败 ${failed} 个。`;}text.textContent+=" 全部处理完成。";}catch(error){text.textContent+=" 已暂停："+error.message+"。可重新选择文件继续，已上传文件不会重复写入。";}finally{button.disabled=false;}});})();</script></div>';
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
			$ordered = $this->playlist_track_ids( $playlist_id );
			foreach ( $selected as $track_id ) { if ( self::POST_TYPE === get_post_type( $track_id ) ) { wp_set_object_terms( $track_id, array( $playlist_id ), self::PLAYLIST_TAXONOMY, true ); if ( ! in_array( $track_id, $ordered, true ) ) { $ordered[] = $track_id; } } }
			update_term_meta( $playlist_id, '_mlm_track_order', $ordered );
			$this->redirect_playlist_admin( $playlist_id, $selected ? '所选歌曲已加入歌单。' : '没有选择要加入的歌曲。', true );
		}
		$current = array_map( 'intval', (array) get_objects_in_term( $playlist_id, self::PLAYLIST_TAXONOMY ) );
		$kept = array_fill_keys( $selected, true );
		foreach ( $current as $track_id ) { if ( ! isset( $kept[ $track_id ] ) ) { wp_remove_object_terms( $track_id, $playlist_id, self::PLAYLIST_TAXONOMY ); } }
		update_term_meta( $playlist_id, '_mlm_track_order', $selected );
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
			$query->posts = $this->sort_playlist_posts( (int) $term->term_id, $query->posts );
			$audio = array(); $unavailable = array();
			foreach ( $query->posts as $post ) {
				$url = $this->attachment_url( $post->ID, 'audio' );
				if ( ! $url || $this->is_obviously_invalid_audio_url( $url ) ) { $unavailable[] = $post; continue; }
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
			$member_ids_list = $this->playlist_track_ids( (int) $term->term_id );
			if ( $editing ) {
				echo '<section class="mlm-playlist-members"><h2>歌单中的歌曲</h2><p>取消勾选不再需要的歌曲，然后保存。</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_save_playlist_tracks"><input type="hidden" name="save_mode" value="members"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '">'; wp_nonce_field( 'mlm_save_playlist_tracks_' . $term->term_id ); echo '<div class="mlm-track-checklist">';
				foreach ( $query->posts as $track ) { $cover = $this->attachment_url( $track->ID, 'cover' ); echo '<label class="mlm-track-choice"><input type="checkbox" name="track_ids[]" value="' . (int) $track->ID . '" checked><span class="mlm-choice-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="">' : '<span class="dashicons dashicons-format-audio"></span>' ) . '</span><span><strong>' . esc_html( get_the_title( $track ) ) . '</strong><small>' . esc_html( (string) get_post_meta( $track->ID, '_mlm_artist', true ) ) . '</small></span></label>'; }
				echo '</div>'; submit_button( '保存歌单曲目', 'primary', 'submit', false ); echo '</form></section>';
				$page = max( 1, absint( $_GET['track_page'] ?? 1 ) );
				$available_query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 30, 'paged' => $page, 'orderby' => 'title', 'order' => 'ASC', 'post__not_in' => $member_ids_list, 'tax_query' => array( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'operator' => 'NOT EXISTS' ) ) ) );
				echo '<section class="mlm-playlist-members"><h2>从音乐库加入歌曲</h2><p>这里仅显示尚未加入任何歌单的歌曲，每页 30 首。</p>';
				if ( $available_query->posts ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mlm_save_playlist_tracks"><input type="hidden" name="save_mode" value="add"><input type="hidden" name="playlist_id" value="' . (int) $term->term_id . '">'; wp_nonce_field( 'mlm_save_playlist_tracks_' . $term->term_id ); echo '<div class="mlm-track-checklist">'; foreach ( $available_query->posts as $track ) { $cover = $this->attachment_url( $track->ID, 'cover' ); echo '<label class="mlm-track-choice"><input type="checkbox" name="track_ids[]" value="' . (int) $track->ID . '"><span class="mlm-choice-cover">' . ( $cover ? '<img src="' . esc_url( $cover ) . '" alt="">' : '<span class="dashicons dashicons-format-audio"></span>' ) . '</span><span><strong>' . esc_html( get_the_title( $track ) ) . '</strong><small>' . esc_html( (string) get_post_meta( $track->ID, '_mlm_artist', true ) ) . '</small></span></label>'; } echo '</div>'; submit_button( '将所选歌曲加入歌单', 'primary', 'submit', false ); echo '</form>'; } else { echo '<p>当前没有尚未加入任何歌单的歌曲。</p>'; }
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
			$ids = $this->playlist_track_ids( (int) $term->term_id ); $cover = '';
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
		$is_local_host_api = (bool) preg_match( '#^https?://(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?$#', $base_url );
		if ( ! wp_http_validate_url( $base_url ) && ! $is_local_docker_api && ! $is_local_host_api ) { $this->redirect_rule_import( '接口根地址无效。', false ); }
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

	private function lyrics_directory(): array {
		$uploads = wp_upload_dir();
		$uploads_dir = realpath( (string) $uploads['basedir'] );
		$lyrics_dir = (string) $uploads['basedir'] . DIRECTORY_SEPARATOR . 'wp-xmedia' . DIRECTORY_SEPARATOR . 'lyrics';
		if ( ! $uploads_dir || 0 !== strpos( wp_normalize_path( $lyrics_dir ) . '/', rtrim( wp_normalize_path( $uploads_dir ), '/' ) . '/' ) ) {
			return array();
		}
		return array(
			'dir'      => $lyrics_dir,
			'uploads'  => $uploads_dir,
			'baseurl'  => rtrim( (string) $uploads['baseurl'], '/' ) . '/wp-xmedia/lyrics',
		);
	}

	private function lyrics_organize_plan(): array {
		$context = $this->lyrics_directory();
		$summary = array( 'total' => 0, 'rename' => 0, 'unchanged' => 0, 'orphan' => 0, 'ambiguous' => 0, 'items' => array(), 'context' => $context );
		if ( ! $context ) { return $summary; }
		$tracks = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		$by_file = array();
		foreach ( $tracks as $track_id ) {
			$path = ''; $path_attachment_id = 0;
			$attachment_id = absint( get_post_meta( $track_id, '_mlm_lyrics_attachment_id', true ) );
			if ( $attachment_id ) {
				$attached = get_attached_file( $attachment_id );
				if ( $attached && file_exists( $attached ) ) { $path = (string) realpath( $attached ); $path_attachment_id = $attachment_id; }
			}
			if ( '' === $path ) {
				$url = (string) get_post_meta( $track_id, '_mlm_lyrics_url', true );
				$url_path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
				$uploads_path = rawurldecode( (string) wp_parse_url( (string) wp_upload_dir()['baseurl'], PHP_URL_PATH ) );
				if ( $url_path && $uploads_path && 0 === strpos( $url_path, rtrim( $uploads_path, '/' ) . '/' ) ) {
					$relative = ltrim( substr( $url_path, strlen( rtrim( $uploads_path, '/' ) ) ), '/' );
					$candidate = realpath( $context['uploads'] . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative ) );
					if ( $candidate ) { $path = $candidate; }
				}
			}
			if ( ! $path || 'lrc' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) { continue; }
			if ( 0 !== strpos( wp_normalize_path( $path ) . '/', rtrim( wp_normalize_path( $context['uploads'] ), '/' ) . '/' ) ) { continue; }
			$key = strtolower( wp_normalize_path( $path ) );
			$by_file[ $key ][] = array( 'track_id' => (int) $track_id, 'attachment_id' => $path_attachment_id, 'path' => $path );
		}
		$occupied = array();
		$target_files = is_dir( $context['dir'] ) ? glob( $context['dir'] . DIRECTORY_SEPARATOR . '*.lrc' ) : array();
		foreach ( (array) $target_files as $file ) { $occupied[ strtolower( basename( $file ) ) ] = true; }
		$planned = array();
		ksort( $by_file, SORT_NATURAL );
		foreach ( $by_file as $matches ) {
			$summary['total']++;
			$track_ids = array_values( array_unique( array_column( $matches, 'track_id' ) ) );
			$source_path = $matches[0]['path'];
			$source_relative = ltrim( substr( wp_normalize_path( $source_path ), strlen( rtrim( wp_normalize_path( $context['uploads'] ), '/' ) ) ), '/' );
			if ( 1 !== count( $track_ids ) ) {
				$summary['ambiguous']++;
				$summary['items'][] = array( 'source' => $source_relative, 'source_relative' => $source_relative, 'target' => '', 'track_id' => 0, 'attachment_id' => 0, 'status' => 'ambiguous' );
				continue;
			}
			$track_id = (int) $track_ids[0];
			$attachment_id = 0;
			foreach ( $matches as $match ) { if ( (int) $match['attachment_id'] ) { $attachment_id = (int) $match['attachment_id']; break; } }
			$stem = sanitize_file_name( wp_strip_all_tags( get_the_title( $track_id ) ) );
			if ( '' === $stem ) { $stem = 'music-' . $track_id; }
			$desired = $stem . '.lrc';
			$current = basename( $source_path );
			$already_target = 0 === strcasecmp( wp_normalize_path( dirname( $source_path ) ), wp_normalize_path( $context['dir'] ) );
			if ( $already_target && 0 === strcasecmp( $current, $desired ) ) {
				$summary['unchanged']++; $planned[ strtolower( $desired ) ] = true;
				$summary['items'][] = array( 'source' => $source_relative, 'source_relative' => $source_relative, 'target' => $current, 'track_id' => $track_id, 'attachment_id' => $attachment_id, 'status' => 'unchanged' );
				continue;
			}
			$number = 1; $target = $desired;
			while ( isset( $occupied[ strtolower( $target ) ] ) || isset( $planned[ strtolower( $target ) ] ) ) {
				$number++; $target = $stem . '-' . $number . '.lrc';
			}
			$planned[ strtolower( $target ) ] = true; $summary['rename']++;
			$summary['items'][] = array( 'source' => $source_relative, 'source_relative' => $source_relative, 'target' => $target, 'track_id' => $track_id, 'attachment_id' => $attachment_id, 'status' => 'rename' );
		}
		return $summary;
	}

	private function apply_lyrics_organize_plan( array $plan ): array {
		$changed = 0; $updated = 0; $failed = 0;
		if ( empty( $plan['context'] ) ) { return array( 'changed' => 0, 'updated' => 0, 'failed' => 0 ); }
		$context = $plan['context'];
		if ( ! is_dir( $context['dir'] ) && ! wp_mkdir_p( $context['dir'] ) ) { return array( 'changed' => 0, 'updated' => 0, 'failed' => count( $plan['items'] ) ); }
		$target_real = realpath( $context['dir'] );
		if ( ! $target_real || 0 !== strpos( wp_normalize_path( $target_real ) . '/', rtrim( wp_normalize_path( $context['uploads'] ), '/' ) . '/' ) ) { return array( 'changed' => 0, 'updated' => 0, 'failed' => count( $plan['items'] ) ); }
		foreach ( $plan['items'] as $item ) {
			if ( ! in_array( $item['status'], array( 'rename', 'unchanged' ), true ) ) { continue; }
			$source = realpath( $context['uploads'] . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $item['source_relative'] ) );
			$target = $target_real . DIRECTORY_SEPARATOR . $item['target'];
			if ( ! $source || 'lrc' !== strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ) || 0 !== strpos( wp_normalize_path( $source ) . '/', rtrim( wp_normalize_path( $context['uploads'] ), '/' ) . '/' ) ) { $failed++; continue; }
			if ( 'rename' === $item['status'] && ( file_exists( $target ) || ! rename( $source, $target ) ) ) { $failed++; continue; }
			$track_id = (int) $item['track_id'];
			$attachment_id = absint( $item['attachment_id'] );
			$url = esc_url_raw( $context['baseurl'] . '/' . rawurlencode( $item['target'] ) );
			update_post_meta( $track_id, '_mlm_lyrics_url', $url );
			update_post_meta( $track_id, '_mlm_expected_lyrics_filename', $item['target'] );
			if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
				global $wpdb;
				$relative = ltrim( str_replace( wp_normalize_path( $context['uploads'] ), '', wp_normalize_path( $target ) ), '/' );
				update_attached_file( $attachment_id, $target );
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $metadata ) ) { $metadata['file'] = $relative; wp_update_attachment_metadata( $attachment_id, $metadata ); }
				wp_update_post( array( 'ID' => $attachment_id, 'post_title' => pathinfo( $item['target'], PATHINFO_FILENAME ) ) );
				$wpdb->update( $wpdb->posts, array( 'guid' => $url ), array( 'ID' => $attachment_id ), array( '%s' ), array( '%d' ) );
				clean_post_cache( $attachment_id );
			}
			if ( 'rename' === $item['status'] ) { $changed++; }
			$updated++;
		}
		return array( 'changed' => $changed, 'updated' => $updated, 'failed' => $failed );
	}

	public function organize_lyrics(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mlm_organize_lyrics' );
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'preview' ) );
		$plan = $this->lyrics_organize_plan();
		$result = 'execute' === $mode ? $this->apply_lyrics_organize_plan( $plan ) : array( 'changed' => 0, 'updated' => 0, 'failed' => 0 );
		$changed = $result['changed']; $failed = $result['failed'];
		$message = 'execute' === $mode
			? sprintf( '歌词整理完成：重命名 %d 个，同步记录 %d 条，失败 %d 个，孤立 %d 个，关联不唯一 %d 个。', $changed, $result['updated'], $failed, $plan['orphan'], $plan['ambiguous'] )
			: sprintf( '预览完成：共 %d 个，计划重命名 %d 个，已规范 %d 个，孤立 %d 个，关联不唯一 %d 个。', $plan['total'], $plan['rename'], $plan['unchanged'], $plan['orphan'], $plan['ambiguous'] );
		$url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-settings', 'mlm_lyrics_message' => $message ), admin_url( 'edit.php' ) );
		wp_safe_redirect( $url ); exit;
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = $this->settings();
		echo '<div class="wrap"><h1>音乐库设置</h1>';
		if ( isset( $_GET['mlm_rule_message'] ) ) {
			$notice_class = 'success' === ( $_GET['mlm_rule_status'] ?? '' ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_rule_message'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['mlm_lyrics_message'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['mlm_lyrics_message'] ) ) ) . '</p></div>';
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
		echo '</form><hr><h2>导入 Hermit X 音乐数据</h2><p>上传整理后的 JSON 文件，插件会创建音乐记录，并把音频、封面地址的域名自动替换为当前 WordPress 站点域名。歌词地址暂不填写，上传并匹配歌词文件后再自动补齐；不会自动下载媒体文件。</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mlm_import_hermit_json">'; wp_nonce_field( 'mlm_import_hermit_json' ); echo '<input type="file" name="mlm_hermit_json" accept="application/json,.json" required> '; submit_button( '导入 Hermit X 数据', 'secondary', 'submit', false ); echo '</form>';
		$lyrics_plan = $this->lyrics_organize_plan();
		echo '<hr><h2>整理歌词文件夹</h2><p>依据歌曲的歌词附件或歌词 URL，从 uploads 各子目录定位已关联的 LRC，并移动到 <code>uploads/wp-xmedia/lyrics</code> 后按歌曲标题重命名；同名自动添加编号。未被记录关联的文件不会扫描，多个歌曲共享的文件不会移动。</p>';
		echo '<p><strong>当前预览：</strong>共 ' . (int) $lyrics_plan['total'] . ' 个；可重命名 ' . (int) $lyrics_plan['rename'] . ' 个；已规范 ' . (int) $lyrics_plan['unchanged'] . ' 个；孤立 ' . (int) $lyrics_plan['orphan'] . ' 个；关联不唯一 ' . (int) $lyrics_plan['ambiguous'] . ' 个。</p>';
		if ( $lyrics_plan['items'] ) { echo '<details><summary>查看文件明细</summary><table class="widefat striped" style="max-width:900px;margin-top:10px"><thead><tr><th>现文件名</th><th>整理后</th><th>状态</th></tr></thead><tbody>'; foreach ( array_slice( $lyrics_plan['items'], 0, 100 ) as $item ) { $labels = array( 'rename' => '待重命名', 'unchanged' => '无需修改', 'orphan' => '孤立文件，不处理', 'ambiguous' => '关联不唯一，不处理' ); echo '<tr><td>' . esc_html( $item['source'] ) . '</td><td>' . esc_html( $item['target'] ?: '—' ) . '</td><td>' . esc_html( $labels[ $item['status'] ] ?? $item['status'] ) . '</td></tr>'; } echo '</tbody></table></details>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px"><input type="hidden" name="action" value="mlm_organize_lyrics">'; wp_nonce_field( 'mlm_organize_lyrics' ); echo '<button class="button" name="mode" value="preview">重新预览</button> <button class="button button-primary" name="mode" value="execute" onclick="return window.confirm(\'确认按上方预览整理歌词文件？文件名和关联记录地址将同步更新，孤立文件不会改动。\');">确认执行整理</button></form>';
		echo '<hr><h2>播放列表</h2><p>可在“音乐库 → 音乐播放列表”中自定义列表，并给歌曲勾选所属列表。插入整张列表使用：<code>[music_playlist id=&quot;播放列表ID&quot; autoplay=&quot;no&quot;]</code> 或 <code>[music_playlist name=&quot;播放列表名称&quot; autoplay=&quot;no&quot;]</code>；需要自动播放时改为 <code>autoplay=&quot;yes&quot;</code>。</p><hr><h2>扩展接口</h2><p><code>mlm_remote_asset_url</code>、<code>mlm_max_remote_asset_size</code>、<code>mlm_asset_imported</code>、<code>mlm_track_saved</code></p></div>';
	}

	private function localize_import_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
			return '';
		}
		$localized = home_url( '/' . ltrim( (string) $parts['path'], '/' ) );
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$localized .= '?' . $parts['query'];
		}
		return esc_url_raw( $localized );
	}

	public function import_hermit_json(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mlm_import_hermit_json' );
		$file = $_FILES['mlm_hermit_json'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? 1 ) ) { wp_die( '请选择有效的 JSON 文件。' ); }
		$data = json_decode( (string) file_get_contents( $file['tmp_name'] ), true );
		if ( ! is_array( $data ) ) { wp_die( 'JSON 文件格式无效。' ); }
		$created = 0; $skipped = 0; $failed = 0;
		foreach ( $data as $item ) {
			$source = sanitize_text_field( (string) ( $item['source_id'] ?? '' ) );
			if ( ! $source ) { $failed++; continue; }
			if ( $this->find_existing_hermit_record( $item ) ) { $skipped++; continue; }
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) ); if ( ! $title ) { $failed++; continue; }
			$post_id = wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => $title ), true );
			if ( is_wp_error( $post_id ) ) { $failed++; continue; }
			update_post_meta( $post_id, '_mlm_hermit_source_id', $source );
			update_post_meta( $post_id, '_mlm_hermit_legacy_id', absint( $item['legacy_id'] ?? 0 ) );
			update_post_meta( $post_id, '_mlm_artist', sanitize_text_field( (string) ( $item['artist'] ?? '' ) ) );
			update_post_meta( $post_id, '_mlm_album', sanitize_text_field( (string) ( $item['album'] ?? '' ) ) );
			update_post_meta( $post_id, '_mlm_lyrics', sanitize_textarea_field( (string) ( $item['lyrics'] ?? '' ) ) );
			update_post_meta( $post_id, '_mlm_expected_lyrics_filename', sanitize_file_name( $title . '.lrc' ) );
			$audio_url  = $this->localize_import_url( (string) ( $item['audio_url'] ?? '' ) );
			$cover_url  = $this->localize_import_url( (string) ( $item['cover_url'] ?? '' ) );
			update_post_meta( $post_id, '_mlm_source_url', $audio_url );
			update_post_meta( $post_id, '_mlm_audio_url', $audio_url );
			update_post_meta( $post_id, '_mlm_cover_url', $cover_url );
			delete_post_meta( $post_id, '_mlm_lyrics_url' );
			$audio_path = (string) wp_parse_url( (string) ( $item['audio_url'] ?? '' ), PHP_URL_PATH );
			if ( $audio_path ) { update_post_meta( $post_id, '_mlm_expected_audio_filename', sanitize_file_name( wp_basename( $audio_path ) ) ); }
			$cover_path = (string) wp_parse_url( (string) ( $item['cover_url'] ?? '' ), PHP_URL_PATH );
			if ( $cover_path ) { update_post_meta( $post_id, '_mlm_expected_cover_filename', sanitize_file_name( wp_basename( $cover_path ) ) ); }
			$created++;
		}
		$url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-settings', 'mlm_rule_status' => 'success', 'mlm_rule_message' => sprintf( 'Hermit X 导入完成：新增 %d 条，跳过 %d 条，失败 %d 条。', $created, $skipped, $failed ) ), admin_url( 'edit.php' ) ); wp_safe_redirect( $url ); exit;
	}

	public function ajax_import_hermit_item(): void {
		check_ajax_referer( 'mlm_import_hermit_json', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => '权限不足。' ), 403 ); }
		$item = json_decode( (string) wp_unslash( $_POST['item'] ?? '' ), true );
		if ( ! is_array( $item ) ) { wp_send_json_error( array( 'message' => '记录格式无效。' ), 400 ); }
		$source = sanitize_text_field( (string) ( $item['source_id'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
		if ( ! $source || ! $title ) { wp_send_json_error( array( 'message' => '缺少歌曲 ID 或名称。' ), 400 ); }
		$existing_id = $this->find_existing_hermit_record( $item );
		$is_existing = (bool) $existing_id;
		$post_id = $is_existing ? $existing_id : wp_insert_post( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => $title ), true );
		if ( is_wp_error( $post_id ) ) { wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 ); }
		update_post_meta( $post_id, '_mlm_hermit_source_id', $source );
		update_post_meta( $post_id, '_mlm_hermit_legacy_id', absint( $item['legacy_id'] ?? 0 ) );
		update_post_meta( $post_id, '_mlm_artist', sanitize_text_field( (string) ( $item['artist'] ?? '' ) ) );
		update_post_meta( $post_id, '_mlm_album', sanitize_text_field( (string) ( $item['album'] ?? '' ) ) );
		update_post_meta( $post_id, '_mlm_lyrics', sanitize_textarea_field( (string) ( $item['lyrics'] ?? '' ) ) );
		update_post_meta( $post_id, '_mlm_expected_lyrics_filename', sanitize_file_name( $title . '.lrc' ) );
		$audio_url  = $this->localize_import_url( (string) ( $item['audio_url'] ?? '' ) );
		$cover_url  = $this->localize_import_url( (string) ( $item['cover_url'] ?? '' ) );
		update_post_meta( $post_id, '_mlm_source_url', $audio_url );
		update_post_meta( $post_id, '_mlm_audio_url', $audio_url );
		update_post_meta( $post_id, '_mlm_cover_url', $cover_url );
		delete_post_meta( $post_id, '_mlm_lyrics_url' );
		$audio_path = (string) wp_parse_url( (string) ( $item['audio_url'] ?? '' ), PHP_URL_PATH );
		if ( $audio_path ) { update_post_meta( $post_id, '_mlm_expected_audio_filename', sanitize_file_name( wp_basename( $audio_path ) ) ); }
		$cover_path = (string) wp_parse_url( (string) ( $item['cover_url'] ?? '' ), PHP_URL_PATH );
		if ( $cover_path ) { update_post_meta( $post_id, '_mlm_expected_cover_filename', sanitize_file_name( wp_basename( $cover_path ) ) ); }
		wp_send_json_success( array( 'status' => $is_existing ? 'repaired' : 'created', 'title' => $title, 'post_id' => $post_id, 'asset_errors' => array() ) );
	}

	public function ajax_filter_hermit_records(): void {
		check_ajax_referer( 'mlm_import_hermit_json', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => '权限不足。' ), 403 ); }
		$records = json_decode( (string) wp_unslash( $_POST['records'] ?? '' ), true );
		if ( ! is_array( $records ) ) { wp_send_json_error( array( 'message' => '记录格式无效。' ), 400 ); }
		if ( count( $records ) > 10000 ) { wp_send_json_error( array( 'message' => '单次最多预览 10000 条记录。' ), 413 ); }
		$sources = array(); $legacy_ids = array(); $songs = array();
		foreach ( get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $post ) {
			$source = (string) get_post_meta( $post->ID, '_mlm_hermit_source_id', true ); if ( '' !== $source ) { $sources[ $source ] = true; }
			$legacy_id = absint( get_post_meta( $post->ID, '_mlm_hermit_legacy_id', true ) ); if ( $legacy_id ) { $legacy_ids[ $legacy_id ] = true; }
			$key = $this->normalized_import_key( get_the_title( $post ), (string) get_post_meta( $post->ID, '_mlm_artist', true ), (string) get_post_meta( $post->ID, '_mlm_album', true ) ); if ( '' !== $key ) { $songs[ $key ] = true; }
		}
		$available = array(); $filtered = 0;
		foreach ( $records as $index => $item ) {
			$source = is_array( $item ) ? sanitize_text_field( (string) ( $item['source_id'] ?? '' ) ) : '';
			$legacy_id = is_array( $item ) ? absint( $item['legacy_id'] ?? 0 ) : 0;
			$key = is_array( $item ) ? $this->normalized_import_key( (string) ( $item['title'] ?? '' ), (string) ( $item['artist'] ?? '' ), (string) ( $item['album'] ?? '' ) ) : '';
			$is_existing = ! is_array( $item )
				|| ( $source ? isset( $sources[ $source ] ) : ( $legacy_id ? isset( $legacy_ids[ $legacy_id ] ) : ( $key && isset( $songs[ $key ] ) ) ) );
			if ( $is_existing ) { $filtered++; continue; }
			$available[] = (int) $index;
		}
		wp_send_json_success( array( 'available_indexes' => $available, 'filtered' => $filtered, 'total' => count( $records ) ) );
	}

	private function find_existing_hermit_record( array $item ): int {
		$source = sanitize_text_field( (string) ( $item['source_id'] ?? '' ) );
		if ( $source ) { $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_mlm_hermit_source_id', 'meta_value' => $source ) ); return $ids ? (int) $ids[0] : 0; }
		$legacy_id = absint( $item['legacy_id'] ?? 0 );
		if ( $legacy_id ) { $ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_mlm_hermit_legacy_id', 'meta_value' => (string) $legacy_id ) ); return $ids ? (int) $ids[0] : 0; }
		$key = $this->normalized_import_key( (string) ( $item['title'] ?? '' ), (string) ( $item['artist'] ?? '' ), (string) ( $item['album'] ?? '' ) );
		if ( '' === $key ) { return 0; }
		$posts = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1 ) );
		foreach ( $posts as $post ) { if ( $key === $this->normalized_import_key( get_the_title( $post ), (string) get_post_meta( $post->ID, '_mlm_artist', true ), (string) get_post_meta( $post->ID, '_mlm_album', true ) ) ) { return (int) $post->ID; } }
		return 0;
	}

	private function normalized_import_key( string $title, string $artist, string $album ): string {
		return $this->normalized_song_key( $title, $artist ) . '|' . (string) preg_replace( '/[\p{P}\p{Z}\s]+/u', '', mb_strtolower( trim( sanitize_text_field( $album ) ) ) );
	}

	private function normalized_song_key( string $title, string $artist ): string {
		$normalize = static function ( string $value ): string { return (string) preg_replace( '/[\p{P}\p{Z}\s]+/u', '', mb_strtolower( trim( sanitize_text_field( $value ) ) ) ); };
		$title = $normalize( $title ); $artist = $normalize( $artist );
		return '' === $title ? '' : $title . '|' . $artist;
	}

	private function attachment_id_by_filename( string $filename ): int {
		global $wpdb;
		$filename = wp_basename( sanitize_file_name( $filename ) );
		if ( '' === $filename ) { return 0; }
		$like = '%' . $wpdb->esc_like( '/' . $filename );
		$sql = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND p.post_status IN ( 'inherit', 'private' ) AND pm.meta_key = '_wp_attached_file' AND ( pm.meta_value = %s OR pm.meta_value LIKE %s ) ORDER BY p.ID DESC LIMIT 1",
			$filename,
			$like
		);
		return (int) $wpdb->get_var( $sql );
	}

	private function attachment_filename_exists( string $filename ): bool {
		return 0 < $this->attachment_id_by_filename( $filename );
	}

	public function bulk_upload_assets(): void {
		$is_ajax = ! empty( $_POST['mlm_ajax'] );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '权限不足。', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mlm_bulk_upload_assets' );
		$type = sanitize_key( $_POST['asset_type'] ?? '' );
		if ( ! in_array( $type, array( 'audio', 'cover', 'lyrics' ), true ) ) { wp_die( '媒体类型无效。' ); }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$uploaded = 0; $failed = 0; $selected = 0; $matched = 0; $skipped = 0; $errors = array(); $seen_names = array();
		foreach ( array( 'mlm_asset_files', 'mlm_asset_folder' ) as $field ) {
			$files = $_FILES[ $field ] ?? array();
			if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) { continue; }
			foreach ( $files['name'] as $index => $name ) {
				$name = (string) $name;
				if ( '' === trim( $name ) ) { continue; }
				$normalized = mb_strtolower( wp_basename( sanitize_file_name( $name ) ) );
				$existing_attachment_id = $this->attachment_id_by_filename( $normalized );
				if ( isset( $seen_names[ $normalized ] ) || $existing_attachment_id ) {
					if ( 'lyrics' === $type && $existing_attachment_id ) { $matched += $this->maybe_link_lyrics_attachment( $existing_attachment_id ); }
					$skipped++;
					$errors[] = (string) $name . '：同名文件已存在，跳过。';
					continue;
				}
				$selected++;
				$seen_names[ $normalized ] = true;
			if ( UPLOAD_ERR_OK !== (int) ( $files['error'][ $index ] ?? 1 ) ) { $failed++; $errors[] = (string) $name . '：浏览器上传错误代码 ' . (int) ( $files['error'][ $index ] ?? 1 ); continue; }
			$_FILES['mlm_single_asset'] = array( 'name' => $name, 'type' => $files['type'][ $index ] ?? '', 'tmp_name' => $files['tmp_name'][ $index ], 'error' => $files['error'][ $index ], 'size' => $files['size'][ $index ] ?? 0 );
			$attachment_id = media_handle_upload( 'mlm_single_asset', 0, array( 'post_title' => pathinfo( sanitize_file_name( (string) $name ), PATHINFO_FILENAME ) ), array( 'test_form' => false ) );
			unset( $_FILES['mlm_single_asset'] );
			if ( is_wp_error( $attachment_id ) ) { $failed++; $errors[] = (string) $name . '：' . $attachment_id->get_error_message(); continue; }
			update_post_meta( $attachment_id, '_mlm_media_type', $type );
			if ( 'lyrics' === $type ) { $matched += $this->maybe_link_lyrics_attachment( $attachment_id ); }
			$uploaded++;
		}
		}
		if ( ! $selected && ! $skipped ) {
			if ( $is_ajax ) { wp_send_json_error( array( 'message' => '请选择需要上传的文件或歌词文件夹。' ), 400 ); }
			wp_die( '请选择需要上传的文件或歌词文件夹。' );
		}
		$result = sprintf( '上传完成：已加入媒体库 %d 个，跳过 %d 个重名，失败 %d 个，歌词自动匹配 %d 条。', $uploaded, $skipped, $failed, $matched );
		if ( $errors ) { $result .= ' 失败详情：' . implode( '；', array_slice( $errors, 0, 5 ) ); }
		if ( $is_ajax ) { wp_send_json_success( array( 'uploaded' => $uploaded, 'skipped' => $skipped, 'failed' => $failed, 'matched' => $matched, 'message' => $result ) ); }
		$url = add_query_arg( array( 'post_type' => self::POST_TYPE, 'page' => 'mlm-bulk-media', 'mlm_upload_result' => $result ), admin_url( 'edit.php' ) ); wp_safe_redirect( $url ); exit;
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
				'source' => sanitize_text_field( $item['source'] ?? 'qq' ),
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
				'source' => 'qq',
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
			'source_url' => esc_url_raw( $item['source_url'] ?? '' ),
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
		wp_register_style( 'mlm-player', MLM_URL . 'assets/css/player-v01418.css', array( 'mlm-aplayer' ), MLM_VERSION );
		wp_register_style( 'mlm-player-layout-fix', MLM_URL . 'assets/css/player-layout-fix-v0146.css', array( 'mlm-player' ), MLM_VERSION );
		wp_register_script( 'mlm-aplayer', MLM_URL . 'assets/vendor/aplayer/APlayer.min.js', array(), '1.10.1', true );
		wp_register_script( 'mlm-player', MLM_URL . 'assets/js/player-v01422-token.js', array( 'mlm-aplayer' ), MLM_VERSION, true );
	}

	private function enqueue_front_assets(): void {
		wp_enqueue_style( 'mlm-player' );
		wp_enqueue_style( 'mlm-player-layout-fix' );
		wp_enqueue_script( 'mlm-player' );
		wp_localize_script( 'mlm-player', 'mlmPlayerData', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}

	public function single_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0, 'lyrics' => 'yes', 'autoplay' => 'no' ), $atts, 'music' );
		$post = get_post( absint( $atts['id'] ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { return ''; }
		$this->enqueue_front_assets();
		return $this->maybe_defer_player( $this->render_player( $post, $this->shortcode_bool( $atts['lyrics'] ), $this->shortcode_bool( $atts['autoplay'] ) ) );
	}

	public function list_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'limit' => 10, 'artist' => '', 'autoplay' => 'no' ), $atts, 'music_list' );
		$args = array( 'post_type' => self::POST_TYPE, 'posts_per_page' => min( 50, max( 1, absint( $atts['limit'] ) ) ), 'post_status' => 'publish' );
		if ( $atts['artist'] ) { $args['meta_query'] = array( array( 'key' => '_mlm_artist', 'value' => sanitize_text_field( $atts['artist'] ), 'compare' => 'LIKE' ) ); }
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) { return '<p>暂无音乐。</p>'; }
		$this->enqueue_front_assets();
		$html = '<div class="mlm-list">';
		$autoplay = $this->shortcode_bool( $atts['autoplay'] );
		foreach ( $query->posts as $post ) { $html .= $this->render_player( $post, false, $autoplay ); $autoplay = false; }
		return $this->maybe_defer_player( $html . '</div>' );
	}

	public function playlist_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0, 'name' => '', 'autoplay' => 'no' ), $atts, 'music_playlist' );
		$term = absint( $atts['id'] ) ? get_term( absint( $atts['id'] ), self::PLAYLIST_TAXONOMY ) : get_term_by( 'name', sanitize_text_field( $atts['name'] ), self::PLAYLIST_TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) { return ''; }
		$query = new WP_Query( array( 'post_type' => self::POST_TYPE, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => self::PLAYLIST_TAXONOMY, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) );
		$query->posts = $this->sort_playlist_posts( (int) $term->term_id, $query->posts );
		if ( ! $query->have_posts() ) { return '<p>该播放列表暂无已发布歌曲。</p>'; }
		$track_ids = array();
		foreach ( $query->posts as $post ) {
			$url = $this->attachment_url( $post->ID, 'audio' );
			if ( ! $url || $this->is_obviously_invalid_audio_url( $url ) ) { continue; }
			$track_ids[] = (int) $post->ID;
		}
		if ( ! $track_ids ) { return '<p>该播放列表暂无可播放音频。</p>'; }
		$this->enqueue_front_assets();
		return $this->maybe_defer_player( '<section class="mlm-playlist"><h3>' . esc_html( $term->name ) . '</h3><article class="mlm-player mlm-playlist-player"><div class="mlm-aplayer" data-mlm-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-mlm-autoplay="' . ( $this->shortcode_bool( $atts['autoplay'] ) ? '1' : '0' ) . '" data-mlm-token="' . esc_attr( $this->player_data_token( $track_ids, true ) ) . '"></div></article></section>' );
	}

	private function render_player( WP_Post $post, bool $show_lyrics, bool $autoplay = false ): string {
		$audio = $this->attachment_url( $post->ID, 'audio' );
		$html = '<article class="mlm-player" data-mlm-runtime="v2-20260829">';
		$html .= '<div class="mlm-info">';
		if ( $audio && ! $this->is_obviously_invalid_audio_url( $audio ) ) {
			$html .= '<div class="mlm-aplayer" data-mlm-runtime="v3-token" data-mlm-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-mlm-autoplay="' . ( $autoplay ? '1' : '0' ) . '" data-mlm-token="' . esc_attr( $this->player_data_token( array( $post->ID ), $show_lyrics ) ) . '"></div>';
		}
		elseif ( $audio ) { $html .= '<p class="mlm-unavailable">音频地址无效：当前 URL 指向的不是音频文件</p>'; }
		else { $html .= '<p class="mlm-unavailable">暂无可播放音频</p>'; }
		return $html . '</div></article>';
	}

	private function player_data_token( array $track_ids, bool $show_lyrics ): string {
		$payload = wp_json_encode( array( 'ids' => array_values( array_unique( array_map( 'absint', $track_ids ) ) ), 'lyrics' => $show_lyrics ? 1 : 0 ) );
		$encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
		return $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
	}

	public function ajax_player_data(): void {
		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) { wp_send_json_error( array( 'message' => '播放器令牌无效。' ), 403 ); }
		$encoded = strtr( $parts[0], '-_', '+/' );
		$encoded .= str_repeat( '=', ( 4 - strlen( $encoded ) % 4 ) % 4 );
		$payload = json_decode( (string) base64_decode( $encoded, true ), true );
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $payload['ids'] ?? array() ) ) ) ) ), 0, 100 );
		if ( ! $ids ) { wp_send_json_error( array( 'message' => '播放器没有可用歌曲。' ), 400 ); }
		$audio = array(); $show_lyrics = ! empty( $payload['lyrics'] );
		foreach ( $ids as $id ) {
			$post = get_post( $id ); if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { continue; }
			$url = $this->attachment_url( $id, 'audio' ); if ( ! $url || $this->is_obviously_invalid_audio_url( $url ) ) { continue; }
			$audio[] = array( 'name' => wp_strip_all_tags( get_the_title( $post ) ), 'artist' => wp_strip_all_tags( (string) get_post_meta( $id, '_mlm_artist', true ) ), 'url' => esc_url_raw( $url ), 'cover' => esc_url_raw( $this->attachment_url( $id, 'cover' ) ), 'lrc' => $show_lyrics ? sanitize_textarea_field( (string) get_post_meta( $id, '_mlm_lyrics', true ) ) : '' );
		}
		wp_send_json_success( array( 'audio' => $audio ) );
	}

	private function shortcode_bool( $value ): bool {
		return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on' ), true );
	}

	private function is_obviously_invalid_audio_url( string $url ): bool {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		return in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'lrc', 'txt', 'json', 'html', 'htm', 'pdf' ), true );
	}

	private function attachment_url( int $post_id, string $type ): string {
		return (string) get_post_meta( $post_id, '_mlm_' . $type . '_url', true );
	}

	public function columns( array $columns ): array {
		return array(
			'cb' => $columns['cb'] ?? '<input type="checkbox">', 'mlm_cover' => '封面', 'title' => '歌曲名称',
			'mlm_artist' => '作者', 'mlm_album' => '专辑名称', 'mlm_category' => '分类', 'mlm_reference' => '文章引用', 'mlm_actions' => '操作',
		);
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'mlm_cover' === $column ) {
			$cover_url = $this->attachment_url( $post_id, 'cover' );
			if ( $cover_url ) { printf( '<img class="mlm-list-cover" src="%s" alt="">', esc_url( $cover_url ) ); }
		}
		if ( 'mlm_artist' === $column ) { echo esc_html( get_post_meta( $post_id, '_mlm_artist', true ) ); }
		if ( 'mlm_album' === $column ) { echo esc_html( get_post_meta( $post_id, '_mlm_album', true ) ); }
		if ( 'mlm_category' === $column ) {
			$terms = get_the_terms( $post_id, self::TAXONOMY );
			if ( $terms && ! is_wp_error( $terms ) ) { echo esc_html( implode( '、', wp_list_pluck( $terms, 'name' ) ) ); }
		}
		if ( 'mlm_reference' === $column ) {
			$counts = $this->track_reference_counts();
			$count  = (int) ( $counts[ $post_id ] ?? 0 );
			echo $count ? '<strong>已引用</strong><br><span class="description">' . $count . ' 篇文章/页面</span>' : '<span class="description">未引用</span>';
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
			if ( current_user_can( 'delete_post', $post_id ) ) {
				printf( ' | <a class="submitdelete" href="%s" onclick="return window.confirm(\'只永久删除歌曲记录，音频、封面和歌词文件都会保留。确定继续吗？\');">只删除记录</a>', esc_url( $this->track_delete_url( $post_id, 'record_only' ) ) );
				printf( ' | <a class="submitdelete" href="%s" onclick="return window.confirm(\'永久删除歌曲记录，并删除未被其他歌曲引用的音频、封面和歌词文件？此操作无法撤销。\');">删除记录及独占文件</a>', esc_url( $this->track_delete_url( $post_id, 'record_and_files' ) ) );
			}
		}
	}

	public function track_reference_views( array $views ): array {
		$counts      = $this->track_reference_counts();
		$referenced  = count( array_filter( $counts ) );
		$unreferenced = count( $counts ) - $referenced;
		$current     = sanitize_key( $_GET['mlm_reference_status'] ?? '' );
		$base_url    = add_query_arg( 'post_type', self::POST_TYPE, admin_url( 'edit.php' ) );
		$views['mlm_referenced'] = '<a href="' . esc_url( add_query_arg( 'mlm_reference_status', 'referenced', $base_url ) ) . '"' . ( 'referenced' === $current ? ' class="current" aria-current="page"' : '' ) . '>已被文章引用 <span class="count">（' . $referenced . '）</span></a>';
		$views['mlm_unreferenced'] = '<a href="' . esc_url( add_query_arg( 'mlm_reference_status', 'unreferenced', $base_url ) ) . '"' . ( 'unreferenced' === $current ? ' class="current" aria-current="page"' : '' ) . '>未被文章引用 <span class="count">（' . $unreferenced . '）</span></a>';
		return $views;
	}

	public function track_reference_dropdown( string $post_type, string $which ): void {
		if ( self::POST_TYPE !== $post_type || 'top' !== $which ) { return; }
		$counts       = $this->track_reference_counts();
		$referenced   = count( array_filter( $counts ) );
		$unreferenced = count( $counts ) - $referenced;
		$current      = sanitize_key( $_GET['mlm_reference_status'] ?? '' );
		echo '<label class="screen-reader-text" for="mlm-reference-status">按文章引用筛选</label><select id="mlm-reference-status" name="mlm_reference_status"><option value="">全部引用状态</option><option value="referenced" ' . selected( $current, 'referenced', false ) . '>已被文章引用（' . $referenced . '）</option><option value="unreferenced" ' . selected( $current, 'unreferenced', false ) . '>未被文章引用（' . $unreferenced . '）</option></select>';
	}

	public function filter_tracks_by_reference( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) { return; }
		$status = sanitize_key( $_GET['mlm_reference_status'] ?? '' );
		if ( ! in_array( $status, array( 'referenced', 'unreferenced' ), true ) ) { return; }
		$referenced_ids = array_keys( array_filter( $this->track_reference_counts() ) );
		if ( 'referenced' === $status ) {
			$query->set( 'post__in', $referenced_ids ?: array( 0 ) );
		} else {
			$query->set( 'post__not_in', array_values( array_unique( array_merge( (array) $query->get( 'post__not_in' ), $referenced_ids ) ) ) );
		}
	}

	private function track_reference_counts(): array {
		if ( is_array( $this->track_reference_cache ) ) { return $this->track_reference_cache; }
		$track_ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$references = array();
		foreach ( $track_ids as $track_id ) { $references[ (int) $track_id ] = array(); }
		$content_posts = get_posts( array( 'post_type' => array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( self::POST_TYPE, 'attachment' ) ) ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'suppress_filters' => true ) );
		foreach ( $content_posts as $content_post ) {
			$ids = $this->track_ids_referenced_in_content( (string) $content_post->post_content );
			foreach ( $ids as $track_id ) { if ( isset( $references[ $track_id ] ) ) { $references[ $track_id ][ $content_post->ID ] = true; } }
		}
		$this->track_reference_cache = array_map( 'count', $references );
		return $this->track_reference_cache;
	}

	private function track_ids_referenced_in_content( string $content ): array {
		$ids = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'music', 'music_playlist', 'music_list' ) ) . '/s', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( $match[3] ) ?: array();
				if ( 'music' === $match[2] && ! empty( $atts['id'] ) ) { $ids[] = absint( $atts['id'] ); }
				elseif ( 'music_playlist' === $match[2] ) {
					$term = ! empty( $atts['id'] ) ? get_term( absint( $atts['id'] ), self::PLAYLIST_TAXONOMY ) : ( ! empty( $atts['name'] ) ? get_term_by( 'name', sanitize_text_field( $atts['name'] ), self::PLAYLIST_TAXONOMY ) : false );
					if ( $term && ! is_wp_error( $term ) ) { $ids = array_merge( $ids, array_map( 'intval', (array) get_objects_in_term( $term->term_id, self::PLAYLIST_TAXONOMY ) ) ); }
				} elseif ( 'music_list' === $match[2] ) {
					$args = array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => min( 50, max( 1, absint( $atts['limit'] ?? 10 ) ) ), 'fields' => 'ids' );
					if ( ! empty( $atts['artist'] ) ) { $args['meta_query'] = array( array( 'key' => '_mlm_artist', 'value' => sanitize_text_field( $atts['artist'] ), 'compare' => 'LIKE' ) ); }
					$ids = array_merge( $ids, get_posts( $args ) );
				}
			}
		}
		$this->collect_block_track_ids( parse_blocks( $content ), $ids );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private function collect_block_track_ids( array $blocks, array &$ids ): void {
		foreach ( $blocks as $block ) {
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = (array) ( $block['attrs'] ?? array() );
			if ( 'mlm/music-player' === $name && ! empty( $attrs['trackId'] ) ) { $ids[] = absint( $attrs['trackId'] ); }
			if ( 'mlm/music-playlist' === $name && ! empty( $attrs['playlistId'] ) ) {
				$ids = array_merge( $ids, array_map( 'intval', (array) get_objects_in_term( absint( $attrs['playlistId'] ), self::PLAYLIST_TAXONOMY ) ) );
			}
			if ( ! empty( $block['innerBlocks'] ) ) { $this->collect_block_track_ids( $block['innerBlocks'], $ids ); }
		}
	}

	public function row_actions( array $actions, WP_Post $post ): array {
		return self::POST_TYPE === $post->post_type ? array() : $actions;
	}

	public function hide_track_post_states( array $states, WP_Post $post ): array {
		return self::POST_TYPE === $post->post_type ? array() : $states;
	}
}
