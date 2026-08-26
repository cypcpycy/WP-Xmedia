<?php
/**
 * Plugin Name: 音乐资料库管理器
 * Description: 管理歌曲、搜索公开音乐元数据，并通过短代码插入播放器。
 * Version: 0.14.2
 * Author: Music Library Manager
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: music-library-manager
 * Update URI: https://github.com/cypcpycy/WP-Xmedia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLM_VERSION', '0.14.2' );
define( 'MLM_FILE', __FILE__ );
define( 'MLM_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLM_URL', plugin_dir_url( __FILE__ ) );

require_once MLM_DIR . 'includes/class-mlm-plugin.php';
require_once MLM_DIR . 'includes/class-mlm-github-updater.php';

register_activation_hook( __FILE__, array( 'MLM_Plugin', 'activate' ) );
MLM_Plugin::instance();
new MLM_GitHub_Updater();
