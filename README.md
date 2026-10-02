# 彩虹自助下单系统（ds_shop）

> PHP 版本：**必须 7.4**（核心含混淆代码，PHP 8.x 不可用）  
> 本地推荐解释器：`C:\tools\php74\php.exe`

____
* 去除所有授权验证
* 支持自定义说说接口
* 去除后台广告与更新
* 可自定义易支付接口
____

---

## 本仓库增强更新说明（2026-10）

在原版彩虹商城基础上，本仓库已落地以下能力。部署到新环境时请一并阅读。

### 1. AI 运营助手 + 前台客服浮窗

| 能力 | 说明 |
|------|------|
| 多模型配置 | 后台 `admin/ai_set.php`，支持自定义 Base URL / Key / 模型名 |
| 工具调用 | `includes/lib/Ai/`：订单、商品、分站、余额、工单、货源、卡密等读写工具 |
| 作用域 | admin / user / shop 三类权限隔离，改 AI 时务必核对危险写操作 |
| 后台对话 | `admin/ai.php`，会话历史、重命名/删除、操作日志 |
| 分站对话 | `user/ai.php`，仅能操作本站数据 |
| 前台浮窗 | `assets/js/ai-widget.js` + `assets/css/ai-widget.css`，PC 右下角卡片、手机全屏 |
| 人工客服 | 微信式会话（同窗气泡、图片、短轮询），后台 `admin/ai_cs.php` |
| 附件存储 | 本地 / 腾讯云 COS / 阿里云 OSS / 七牛，可与 APK 存储复用 |

相关文件：`ajax_ai.php`、`user/ajax_ai.php`、`includes/ai_widget_inc.php`、`includes/lib/Ai/*`、`includes/lib/Storage/*`

### 2. 本地 APP 工厂（安卓 WebView 壳）

| 项 | 说明 |
|------|------|
| 模式 | `appcreate_mode=local` 本地打包；`remote` 走第三方打包接口 |
| 壳工程 | `tools/appshell/`（包名/图标/启动图/网址可差异化） |
| Worker | `tools/appbuild/worker.php` + `run_worker.bat`（计划任务 `DsShopAppBuildWorker`） |
| 管理中心 | `admin/app_list.php`：上架 / 下架 / 删除 / 重试 / 批量 / 编辑链接 |
| 生成配置 | `admin/appCreate.php` |
| 下载页 | `/?mod=app&id=` → `template/default/app.php`（生成中 / 成功 / 失败 / 下架） |
| APK 存储 | `auto` 跟随附件存储；可强制 `local` 或 `cloud`；失败回退本站目录 |
| 表结构 | `install/update_appfactory.sql`；运行时 `AppFactory` 也可动态补字段 |

**本地构建环境建议（避开中文路径）：**

- JDK 17、Android SDK → 如 `C:\Android\Sdk`
- Gradle → `C:\Gradle`，工作目录 → `C:\appbuild`
- 本机路径写在 `tools/appbuild/env.local.php`（已 gitignore，勿提交）

### 3. 货源 API 对接（彩虹同系统）

| 项 | 说明 |
|------|------|
| 本地货源 | `supplier/api.php`，独立端口 **8081** |
| 主站插件 | `includes/plugins/third_daishua.php`（`is_curl=2`） |
| 启动 | 主站 `start-local.bat`（8080）+ `start-supplier.bat`（8081） |
| 注意 | **禁止**货源 URL 与主站同端口，PHP 内置服务器会自调用死锁 |
| 演示账号 | `supplier` / `supplier123` |
| 后台测试 | `admin/api_dock.php` |
| 说明文档 | `supplier/README.md` |

订单关注字段：`status`、`djzt`、`djorder`（对接成功/失败/发卡等）。

### 4. 商品 / 分类图片

| 项 | 说明 |
|------|------|
| 分类图 | `shua_class.shopimg` → `assets/uploads/class/` |
| 商品图 | `shua_tools.shopimg` → `assets/uploads/products/` |
| 后台管理 | 商品列表缩略图 +「图片」按钮 → `admin/shopimage.php`（上传或填 URL） |
| 前台展示 | 进分类自动选中首个商品并显示商品图；缩略图条可切换 |

### 5. 其它修复与体验

- 代理设置明文页 `admin/proxy.php`（绕过损坏的旧设置页）
- 分站订单详情权限、收款码上传校验、注册 AJAX 错误提示
- 验证码字体回退（`includes/ValidateCode.class.php`）
- 模拟支付 `ajax.php?act=testpay`：**仅适合本地演示，上公网前务必加环境限制**

---

## 目录结构（简要）

```
admin/          管理员后台（含 AI、APP、货源测试）
user/           分站及用户（含 AI）
assets/         静态资源与 uploads
includes/       核心库、插件、AI、Storage、AppFactory
other/          支付目录
install/        安装与升级 SQL
template/       前台模板（下载页 default/app.php）
supplier/       本地演示货源（8081）
tools/appshell  安卓壳模板
tools/appbuild  本地打包 Worker
config.php      数据库配置（不入库，需自行创建）
ajax.php        前台业务 AJAX
ajax_ai.php     AI / 客服 AJAX
api.php         开放 API
cron.php        定时任务
```

---

## 安装教程

1. 上传源码到空间或服务器，确保目录可读写。  
2. 浏览器访问 `域名/install/index.php`，按步骤安装。  
3. 复制并填写 `config.php`（库名、账号、表前缀一般为 `shua_`）。  
4. 如需 APP 工厂字段，执行 `install/update_appfactory.sql`（或依赖运行时自动补齐）。  
5. 启用 AI：后台「AI模型配置」填写 Key 并开启。  
6. 本地联调货源：同时启动 8080 与 8081，社区 URL 填 `127.0.0.1:8081`。

### 本地快速启动（Windows）

```bat
start-local.bat
start-supplier.bat
tools\appbuild\run_worker.bat
```

- 主站：http://127.0.0.1:8080/  
- 货源：http://127.0.0.1:8081/  

---

## 远程仓库

- GitHub：https://github.com/aiyangdie/ds_shop  
- Gitee：https://gitee.com/giteeaike/ds_shop  

> `config.php`、本机 SDK 路径、Gradle 安装包等敏感/大文件已忽略，不会进入仓库。

---

## 路径地址详情

> admin（管理员后台）  
> user（分站及用户）  
> assets（资源文件）  
> includes（应用程序核心文件）  
> other（支付目录）  
> install（程序安装）  
> template（模板）  
> config.php（数据库配置文件）  
> doc.php（对接文档）  
> supplier（本地货源演示）  
> tools（APP 壳与打包）  

---

*特别强调*
> 1、此版本为正式免费版本，其他的均属于二次开发。  
> 2、在发布前，已和原作者联系并同意此次发布。

__使用协议__
* 如果在使用过程中，出现违法违纪的情况下均与本人无关，全由使用者承担。
* 在使用中出现问题，例如：BUG、数据泄露及丢失、入侵等安全问题，全由使用者承担。
* 此系统仅供个人学习、研究之用，请勿用于商业用途。
* 不提供任何技术支持。
* 在您下载源码后视为您已经了解使用协议并知晓法律协议。
