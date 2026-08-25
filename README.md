# WP-Xmedia

WP-Xmedia is a WordPress music library plugin for managing tracks, importing authorized audio, cover artwork, and lyrics into the WordPress Media Library, and embedding music in posts and pages.

## Features

- Dedicated, simplified track editor in WordPress admin
- Remote music metadata search through a separately deployed music search API
- Import a JSON API rule in the WordPress admin to configure the API URL and endpoint templates
- Import audio, cover images, and LRC lyrics with official WordPress media APIs
- Visual Gutenberg track and playlist selectors
- Shortcodes for tracks, recent music, and playlists
- Bundled APlayer 1.10.1 assets with no front-end CDN dependency
- Responsive players with cover artwork and synchronized lyrics

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- A separately deployed compatible music search API for remote search features

The companion API source is maintained separately at [music-search-api](https://github.com/cypcpycy/music-search-api).

## Installation

1. Download `dist/WP-Xmedia-0.13.0.zip` from this repository.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP, install it, and activate the plugin.
4. Open **Music Library > Settings** and either import the companion API JSON rule or enter the remote API base URL manually.

The ready-to-import rule is maintained in the companion API repository at `integrations/wordpress/wp-xmedia-api-rule.json`. It contains endpoint mappings only and never contains account cookies, tokens, or passwords.

## Updates

WP-Xmedia uses public GitHub Releases as its WordPress update source. WordPress displays the standard plugin update row, version details, immediate update action, and automatic-update control without requiring a token.

## Shortcodes

```text
[music id="123"]
[music_list limit="10"]
[music_playlist id="45"]
[music_playlist name="Playlist name"]
```

## Development

The installable plugin is stored in `music-library-manager/`. Do not commit account cookies, API tokens, downloaded music, or WordPress upload data.

## Copyright and acceptable use

Only import, play, or publish media that you own, are licensed to use, or are otherwise authorized to use. This plugin must not be used to bypass DRM, subscriptions, authentication, access controls, or third-party platform terms.

## License

GPL-2.0-or-later. APlayer is distributed under its own MIT license in `music-library-manager/assets/vendor/aplayer/LICENSE`.
