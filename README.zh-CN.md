# WP-Xmedia

**中文说明 | [English](README.md)**

WP-Xmedia 是一款 WordPress 音乐库插件，用于管理歌曲、导入经过授权的音频、专辑封面和歌词，并通过短代码将音乐插入文章和页面。

## 功能

- WordPress 后台专用的简洁歌曲编辑界面
- 通过独立部署的音乐搜索 API 搜索远程音乐资料
- 在 WordPress 设置中导入 JSON API 规则，配置 API 地址和端点模板
- 使用 WordPress 官方媒体接口导入音频、封面图片和 LRC 歌词
- Gutenberg 可视化歌曲和播放列表选择器
- 支持单曲、最近音乐和播放列表短代码
- 内置 APlayer 1.10.1，无需依赖前端 CDN
- 响应式播放器、封面显示和同步歌词
- 经典编辑器中的可视化音乐占位卡片
- 重复音频文件检测，可选择复用媒体库中的已有文件

## 环境要求

- WordPress 6.2 或更高版本
- PHP 7.4 或更高版本
- 独立部署且兼容的音乐搜索 API（远程搜索功能需要）

配套 API 源码维护在单独的仓库中：[music-search-api](https://github.com/cypcpycy/music-search-api)。

## 安装

1. 从 [GitHub Releases](https://github.com/cypcpycy/WP-Xmedia/releases/latest) 下载最新的 `WP-Xmedia` ZIP 安装包。
2. 在 WordPress 后台打开“插件 → 安装插件 → 上传插件”。
3. 上传 ZIP 文件，完成安装并启用插件。
4. 打开“音乐库 → 设置”，导入兼容的 API JSON 规则文件。

## 0.14.2 版本说明

- 优化音乐播放列表编辑界面：歌单名称可直接编辑
- 支持分别管理歌单已有歌曲和待加入歌曲
- 音乐库歌曲分页显示，每页 30 首
- 支持勾选移除歌曲和批量加入歌曲，分页不会误删其他曲目
- 删除歌单时可选择仅删除歌单，或同时永久删除歌曲记录及其媒体文件
- 重复音频文件提示窗口置于音乐导入窗口最上层，可直接操作
- 可选择后续重复歌曲统一复用已有文件或统一跳过
- 经典编辑器支持从音乐库选择歌曲/歌单并插入短代码
- 经典编辑器将短代码显示为可视化音乐卡片，代码模式仍保留原始短代码

可直接导入的规则文件维护在配套 API 仓库的 `integrations/wordpress/wp-xmedia-api-rule.json`。该文件只包含端点映射，不包含账号 Cookie、Token 或密码。

## 在线更新

WP-Xmedia 使用公开的 GitHub Releases 作为 WordPress 更新源。WordPress 会按照普通插件的方式显示版本信息、更新提示、立即更新按钮和自动更新选项，无需填写 Token。

## 短代码

```text
[music id="123"]
[music_list limit="10"]
[music_playlist id="45"]
[music_playlist name="歌单名称"]
```

## 开发说明

可安装插件位于 `music-library-manager/`。请勿提交账号 Cookie、API Token、下载的音乐文件或 WordPress 上传目录数据。

## 版权与合规使用

只能导入、播放或发布你拥有版权、获得授权或依法可以使用的媒体。本插件不得用于绕过 DRM、订阅、登录验证、访问控制或第三方平台服务条款。

## 许可证

GPL-2.0-or-later。APlayer 使用其独立的 MIT 许可证，许可证文件位于 `music-library-manager/assets/vendor/aplayer/LICENSE`。
