# 第三方组件与版权待确认

本文件列出开源整理时发现的、需要发布者核对授权的依赖。未完成核对前，不要删除原文件中的版权注释，也不要把整个仓库声明为单一许可证。

## 业务主体

- 彩虹自助下单 / 彩虹代刷衍生 PHP 程序（历史商业/免费分发包）
- 本仓库对混淆核心的可读恢复与本地增强（AI 助手、APP 工厂、演示货源等）

授权状态：**待确认**。请不要在未取得书面许可时用于商业分发。

## 常见前端/编辑器资源（均需按原许可证再分发）

- Bootstrap / Bootswatch 类后台皮肤（`assets/`、`template/`）
- jQuery 及 `jquery.dragsort`
- Layer.js
- KindEditor（`assets/kindeditor/`）
- Font Awesome / Glyphicons
- 各模板目录下的第三方 CSS/JS

## PHP 库

- PHPMailer（`includes/` 或 vendor 目录中如存在）
- 支付 SDK（支付宝/微信/QQ 相关，`includes/`、`other/`）
- 对象存储 SDK（腾讯云 COS / 阿里云 OSS / 七牛，`includes/lib/Storage/`）

## 工具与壳工程

- `tools/appshell/` 安卓 WebView 壳所引用的 Android/Gradle 依赖
- `tools/deobfuscate/` 仅用于源码恢复与测试，不应作为生产依赖

## 处理原则

1. 保留第三方文件内已有版权头。
2. 无法确认的资源不要改成「本项目自制」。
3. 若某组件不允许再分发，应在正式公开发布前替换或从发行包中剔除。
