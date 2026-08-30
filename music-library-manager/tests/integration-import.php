<?php
// Run only with: wp eval-file wp-content/plugins/music-library-manager/tests/integration-import.php
$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin ? $admin->ID : 1 );

$_POST['mlm_nonce'] = wp_create_nonce( 'mlm_save_track' );
$_POST['mlm'] = array(
	'artist'        => 'WordPress 测试作者',
	'album'         => '媒体库导入测试',
	'audio_url'     => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
	'cover_url'     => 'https://s.w.org/about/images/logos/wordpress-logo-stacked-rgb.png',
	'lyrics'        => "测试歌词第一行\n测试歌词第二行",
	'lyrics_url'    => '',
	'source_url'    => 'https://wordpress.org/',
	'import_assets' => 1,
);

$track_id = wp_insert_post(
	array(
		'post_type'   => 'mlm_track',
		'post_status' => 'publish',
		'post_title'  => '媒体自动导入测试歌曲',
	)
);

$audio_id = (int) get_post_meta( $track_id, '_mlm_audio_attachment_id', true );
$cover_id = (int) get_post_meta( $track_id, '_mlm_cover_attachment_id', true );

echo wp_json_encode(
	array(
		'track_id'         => $track_id,
		'audio_id'         => $audio_id,
		'cover_id'         => $cover_id,
		'audio_local'      => 0 === strpos( (string) wp_get_attachment_url( $audio_id ), home_url() ),
		'cover_local'      => 0 === strpos( (string) wp_get_attachment_url( $cover_id ), home_url() ),
		'featured_image'   => (int) get_post_thumbnail_id( $track_id ),
		'audio_mime'       => get_post_mime_type( $audio_id ),
		'cover_mime'       => get_post_mime_type( $cover_id ),
	),
	JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
