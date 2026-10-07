# 彩虹自助下单系统（ds_shop）

基于彩虹商城二次整理的 **PHP 自助下单 / 发卡 / 分站** 系统。本仓库在可用开店能力之上，可选接入 AI 助手、资源站协议货源、本地安卓 APP 打包等模块。

| 项 | 说明 |
|----|------|
| 远程仓库 | [GitHub](https://github.com/aiyangdie/ds_shop) · [Gitee](https://gitee.com/giteeaike/ds_shop) |
| 运行环境 | **PHP 7.4（主要验证）**；业务核心已换为**可读源码**（`includes/core.func.php`、`includes/ajax.func.php` 等），不再依赖 VM 混淆 |
| 数据库 | MySQL / MariaDB（表前缀默认 `shua_`） |
| 本地 PHP | 推荐 `C:\tools\php74\php.exe` |
| 默认模式 | **只开主站即可卖货**；AI / 货源 / APP 均为可选 |

请勿把真实 `config.php` 提交进仓库。
____
* 去除所有授权验证  
* 支持自定义说说接口  
* 去除后台广告与更新  
* 可自定义易支付接口  
____

---

## 目录

1. [功能总览](#功能总览)
2. [部署档位](#部署档位)
3. [环境要求](#环境要求)
4. [安装教程（档位 A）](#安装教程档位-a)
5. [本地 Windows 快速启动](#本地-windows-快速启动)
6. [后台与前台入口](#后台与前台入口)
7. [核心业务流程](#核心业务流程)
8. [支付配置](#支付配置)
9. [分站说明](#分站说明)
10. [可选模块](#可选模块)
11. [计划任务](#计划任务)
12. [目录结构](#目录结构)
13. [常见问题](#常见问题)
14. [安全建议](#安全建议)
15. [远程仓库与忽略文件](#远程仓库与忽略文件)
16. [使用协议](#使用协议)
17. [已知限制](#已知限制)
18. [许可证状态（待确认）](#许可证状态待确认)

---

## 功能总览

### 开店必备（默认可用）

| 模块 | 能力 |
|------|------|
| 前台商城 | 多套模板（store / default / faka / argon 等），分类、下单、购物车、查单 |
| 发卡 | 卡密库存、自动发货、查单提取卡密 |
| 人工 / 对接 | 待处理订单、对接状态、补单与结果回写 |
| 支付 | 易支付等可配通道；本地有 `testpay` 模拟（仅演示） |
| 会员 | 注册 / 登录 / 余额 / 签到 / 推广（视配置） |
| 分站 | 开通分站、域名绑定、加价模板、提现与明细 |
| 后台 | 订单、商品、分类、卡密、分站、用户、工单、公告、支付、模板等 |

### 本仓库增强（2026-10，按需开启）

| 模块 | 一句话 |
|------|--------|
| AI 运营助手 | 后台 / 分站对话 + 工具调用；前台客服浮窗；人工客服会话 |
| 资源站协议 | 可对接他人货源，也可自建 `supplier/` 对外供货 |
| APP 工厂 | 本地 WebView 壳打包 APK（或走远程打包） |
| 商品图 | 分类图 / 商品图上传与前台展示 |
| 其它 | 代理设置页、域名说明、图标设置、若干权限与体验修复 |

---

## 部署档位

**默认只开主站。** 不必一次配齐所有模块。

| 档位 | 要跑什么 | 适用 |
|------|----------|------|
| **A 开店** | PHP 7.4 + MySQL + 源码安装 | 自营商品 / 发卡 / 收款 |
| **B 开店 + 货源** | A + 单独部署 `supplier/`（或本地再开 8081） | 对接 / 卖 API 服务 |
| **C 本地出包** | 另装 JDK 17 + Android SDK + Gradle | 给分站打 APK |

公网用 Nginx / Apache 指到站点根目录即可，不必用 `start-*.bat`（那只是本机 PHP 内置服务器）。

---

## 环境要求

### 必需

- PHP **7.4**（扩展建议：`mysqli` / `pdo_mysql`、`curl`、`gd`、`mbstring`、`openssl`、`json`）
- MySQL 5.6+ 或 MariaDB
- 站点目录可写（尤其 `assets/uploads/`、缓存与日志目录）

### 可选

| 场景 | 额外依赖 |
|------|----------|
| 本地双进程联调 | Windows + 两个 bat（主站 8080、货源 8081） |
| AI | 可用的 OpenAI 兼容接口 Key |
| 云存储附件 / APK | 腾讯云 COS / 阿里云 OSS / 七牛（或继续用本地） |
| 本地 APP 打包 | JDK 17、Android SDK、Gradle；路径避免中文 |

---

## 安装教程（档位 A）

1. **上传源码**到空间或服务器，确保目录可读写。  
2. 浏览器访问 `https://你的域名/install/index.php`，按向导安装。  
3. 按安装结果填写根目录 **`config.php`**（库名、账号密码、表前缀一般为 `shua_`）。  
   - 仓库**不包含**真实 `config.php`（已 gitignore），需自行创建。  
4. 删除或限制访问 `install/`（生产环境务必处理）。  
5. 登录后台，**立即修改默认管理员密码**。  
6. 配置「网站信息」「支付接口」「首页模板」，上架商品后即可开售。

到这里就已经能卖货。以下均为可选：

- **档位 B：** 部署 `supplier/`，对接 URL 填资源站地址。详见 [`supplier/README.md`](./supplier/README.md) 与 [`supplier/API.md`](./supplier/API.md)。  
- **AI：** 后台 → AI 助手 → 模型配置，填写 Base URL / Key / 模型名并开启。  
- **APP 工厂：** 需要时执行 `install/update_appfactory.sql`（运行时也可能自动补字段），再配本机 JDK/SDK。  

### 伪静态 / 入口

- 主入口：`index.php`（部分环境配合 `router.php`）  
- 后台：`/admin/`  
- 用户 / 分站：`/user/`  
- 对接文档页：`/doc.php`  

Nginx / Apache 按常规 PHP 站点配置即可；若使用短链，参考后台「伪静态」相关说明与服务器规则。

---

## 本地 Windows 快速启动

### 只开店（8080）

```bat
start-local.bat
```

浏览器打开：http://127.0.0.1:8080/

### 联调货源（再开一个窗口）

```bat
start-supplier.bat
```

- 货源：http://127.0.0.1:8081/  
- 探测：`GET http://127.0.0.1:8081/api.php?act=ping`  
- **禁止**货源 URL 与主站同端口，PHP 内置服务器会自调用死锁。

### 本地打 APK（可选）

```bat
tools\appbuild\run_worker.bat
```

路径建议写在 `tools/appbuild/env.local.php`（已忽略，勿提交）。

---

## 后台与前台入口

| 入口 | 路径 | 说明 |
|------|------|------|
| 管理后台 | `/admin/` | 订单、商品、发卡、分站、对接、系统设置等 |
| 会员 / 分站中心 | `/user/` | 登录后按权限显示会员或分站功能 |
| 前台商城 | `/` | 模板由后台「首页模板」决定 |
| 查单 | `/?mod=query` | 订单列表 / 提取卡密 |
| 购物车 | `/?mod=cart` | 视配置开关 |
| 对接文档 | `/doc.php` | 对外 API / 对接说明 |
| 资源站协议说明 | 后台 → 对接设置 → 资源站协议 | `admin/supplier_api.php` |

### 后台侧栏分组（常用）

- **订单管理** — 搜索、状态、对接状态、补单  
- **商品管理** — 分类、商品、加价模板、兑换卡密  
- **发卡管理** — 库存、添加卡密、发信模板  
- **分站管理** — 分站列表、用户、收支、提现、工单、通知  
- **对接设置** — 资源站协议、货源 API、监控、日志、克隆、批量对接  
- **系统设置** — 站点 / 分站 / 公告 / 邮箱 / 支付 / 模板 / 图标 / Logo / 清理等  
- **AI 助手** — 对话、客服中心、操作日志、模型配置（可选）  
- **其它组件** — 签到、推广、抽奖、防红、APP 生成（可选）  

---

## 核心业务流程

```text
浏览商品 → 填写下单信息 → 创建支付单 → 收银台
    → 支付成功回调 → 履约（发卡 / 人工 / 对接上游）
    → 查单页查看状态 / 提取卡密
```

| 商品类型（概念） | 表现 |
|------------------|------|
| 发卡 | 支付后写卡密到订单，查单可「提取卡密」 |
| 人工 | 后台待处理 → 处理中 → 完成，可填结果 |
| 对接（daishua 等） | 提交上游，关注 `status` / `djzt` / `djorder` |

订单状态常见含义：待处理、处理中、已完成、异常、已退单（具体以后台列表为准）。

---

## 支付配置

1. 后台 → **系统设置 → 支付接口配置**。  
2. 填写易支付网关、商户 ID、密钥等。  
3. 按需开关支付宝 / 微信 / QQ。  
4. 本地演示可用 `ajax.php?act=testpay` 模拟支付：  
   - **仅适合本机**  
   - **上公网前务必关闭或加环境限制**，否则存在资金风险。

支付异步通知目录一般在 `other/`，部署后请用真实下单测通「支付成功 → 订单完成 / 发卡」。

---

## 分站说明

| 项 | 说明 |
|----|------|
| 开关与价格 | 后台 → 分站相关配置（开通价、升级、域名后缀等） |
| 域名说明 | `admin/domain_guide.php` |
| 站长后台 | `/user/`（分站权限） |
| 绑定域名 | 如 `user/binddomain.php` 等入口（视模板与菜单） |
| 加价 | 加价模板作用于分站售价 |
| 提成 / 提现 | 收支明细、余额提现审核 |

分站与主站共用一套程序，靠域名 / 站点 ID 区分数据与价格。

---

## 可选模块

### 1. AI 运营助手 + 前台客服浮窗

| 能力 | 说明 |
|------|------|
| 多模型配置 | `admin/ai_set.php`：Base URL / Key / 模型名 |
| 工具调用 | `includes/lib/Ai/`：订单、商品、分站、余额、工单、货源、卡密等 |
| 作用域 | admin / user / shop 权限隔离；改工具时务必核对危险写操作 |
| 后台对话 | `admin/ai.php`：历史、重命名/删除、操作日志 |
| 分站对话 | `user/ai.php`：仅本站数据 |
| 前台浮窗 | `assets/js/ai-widget.js` + `assets/css/ai-widget.css` |
| 人工客服 | 微信式会话，后台 `admin/ai_cs.php` |
| 附件存储 | 本地 / COS / OSS / 七牛，可与 APK 存储复用 |

相关：`ajax_ai.php`、`user/ajax_ai.php`、`includes/ai_widget_inc.php`、`includes/lib/Ai/*`、`includes/lib/Storage/*`

### 2. 本地 APP 工厂（安卓 WebView 壳）

| 项 | 说明 |
|----|------|
| 模式 | `appcreate_mode=local` 本地打包；`remote` 第三方接口 |
| 壳工程 | `tools/appshell/`（包名 / 图标 / 启动图 / 网址） |
| Worker | `tools/appbuild/worker.php` + `run_worker.bat` |
| 管理 | `admin/app_list.php`、`admin/appCreate.php` |
| 下载页 | `/?mod=app&id=` → `template/default/app.php` |
| 表结构 | `install/update_appfactory.sql` |

**路径建议（避开中文）：** JDK 17、Android SDK 如 `C:\Android\Sdk`，Gradle 如 `C:\Gradle`，工作目录如 `C:\appbuild`。

### 3. 资源站协议（可对接 / 可自建）

| 项 | 说明 |
|----|------|
| 协议 | [`supplier/API.md`](./supplier/API.md)（daishua / 同系统协议） |
| 可售服务示例 | `supplier/lib/Services.php` |
| 参考实现 | `supplier/api.php` + 自建 `supplier/config.php`（可从 `config.sample.php` 复制） |
| 商城插件 | `includes/plugins/third_daishua.php`（`is_curl=2`） |
| 后台 | 资源站协议 `admin/supplier_api.php`；连通测试 `admin/api_dock.php` |
| 演示账号 | `supplier` / `supplier123`（**上线务必改密**，并按需 `allow_remote=true`） |

本地联调步骤见上文「本地 Windows 快速启动」。更完整说明见 [`supplier/README.md`](./supplier/README.md)。

### 4. 商品 / 分类图片

| 项 | 说明 |
|----|------|
| 分类图 | `shua_class.shopimg` → `assets/uploads/class/` |
| 商品图 | `shua_tools.shopimg` → `assets/uploads/products/` |
| 后台 | 商品列表缩略图 +「图片」→ `admin/shopimage.php` |
| 前台 | 进分类可选中商品并展示图；缩略图可切换 |

### 5. 其它修复与体验

- 代理设置明文页：`admin/proxy.php`  
- 图标设置：`admin/icon_set.php`  
- 分站域名说明：`admin/domain_guide.php`  
- 分站订单详情权限、收款码上传校验、注册 AJAX 错误提示  
- 验证码字体回退：`includes/ValidateCode.class.php`  

---

## 计划任务

定时逻辑入口：`cron.php`（具体参数与密钥以你站点后台「计划任务」说明为准）。

常见用途：

- 订单状态同步 / 对接补单  
- 价格监控、清理任务  
- APP 打包 Worker（本地另有 `tools/appbuild` 计划任务）  

生产环境请用系统 crontab 或面板计划任务访问，勿依赖人工刷新。

---

## 目录结构

```text
admin/           管理员后台（订单、商品、对接、AI、APP 等）
user/            会员与分站中心
assets/          静态资源、uploads、前台/后台 JS CSS
includes/        核心库（可读 core/ajax）、插件、AI、Storage、AppFactory
other/           支付回调与相关页面
install/         安装向导与升级 SQL
template/        前台模板（store / default / faka / …）
supplier/        资源站套件（API.md + 可独立部署实现）
deobfuscated/    去混淆对照与恢复材料（非运行时）
tools/deobfuscate  去混淆 / 回归工具
tools/appshell   安卓壳模板
tools/appbuild   本地打包 Worker
config.php       数据库配置（不入库，需自行创建）
index.php        前台入口
ajax.php         前台业务 AJAX
ajax_ai.php      AI / 客服 AJAX
api.php          开放 API
cron.php         定时任务入口
doc.php          对接文档页
router.php       本地路由辅助（内置服务器等）
start-local.bat  本机主站 8080
start-supplier.bat 本机货源 8081
```

---

## 常见问题

| 问题 | 处理 |
|------|------|
| PHP 8 打不开 / 白屏 | 优先用 **PHP 7.4**（发布验证环境）；可读核心已去除 VM 混淆，PHP 8 仍未做完整门禁 |
| 安装后空白 | 查 `config.php`、数据库、关闭展示错误后看日志 |
| 支付成功不发卡 | 查异步通知是否达服务器、商品类型与库存、订单 `djzt` |
| 对接一直失败 | 主站与货源是否分端口；`ping` 是否通；账号密码与协议是否匹配 |
| 分站域名打不开 | DNS / 面板绑定、后台域名后缀与保留域、伪静态 |
| AI 无响应 | Key、Base URL、模型名、服务器出网；看 AI 操作日志 |
| 本地 APK 失败 | JDK/SDK/Gradle 路径、中文路径、Worker 是否在跑 |
| 模拟支付被刷 | 公网关闭 `testpay` 或加环境判断 |

---

## 安全建议

1. 修改默认管理员与货源演示账号密码。  
2. 生产关闭或严格限制 `testpay`。  
3. `config.php`、云存储密钥、AI Key **不要提交到 Git**。  
4. 限制 `install/`、备份文件、日志的 Web 访问。  
5. 开启 HTTPS；后台勿暴露弱口令到公网。  
6. AI 工具含写操作，按角色最小权限配置。  

---

## 远程仓库与忽略文件

- GitHub：https://github.com/aiyangdie/ds_shop  
- Gitee：https://gitee.com/giteeaike/ds_shop  

已忽略（不会进入仓库）的典型内容：

- `config.php`、`.env`  
- `install/install.lock`  
- 本机 SDK / Gradle 大包、`tools/appbuild/env.local.php`  
- 本地调试产物、部分 uploads 与日志  

克隆后请自行准备数据库配置，再按[安装教程](#安装教程档位-a)操作。

---

## 使用协议

*特别强调*

> 1、此版本为正式免费版本，其他的均属于二次开发。  
> 2、在发布前，已和原作者联系并同意此次发布。  

**使用协议**

- 如果在使用过程中出现违法违纪情况，均与本人无关，全由使用者承担。  
- 使用中出现 BUG、数据泄露及丢失、入侵等安全问题，全由使用者承担。  
- 此系统仅供个人学习、研究之用，请勿用于商业用途。  
- 不提供任何技术支持。  
- 在您下载源码后视为您已经了解使用协议并知晓法律协议。  

---

## 已知限制

- PHP 8.x 未作为发布门禁验证；以 PHP 7.4 为主。  
- 真实支付、短信、邮件、第三方社区下单未做破坏性联调；自动测试使用替身。  
- `tools/deobfuscate/`、`deobfuscated/` 为恢复与对照材料，**不是**运行时依赖；线上以 `includes/` 下可读核心为准。  
- 部分前台模板与第三方编辑器（KindEditor 等）版权/授权状态需使用者自行核对。  

## 许可证状态（待确认）

本仓库**不能**被理解为「全部代码已采用某一种 OSI 许可证」。

- 主体业务代码来源于历史「彩虹自助下单 / 彩虹代刷」衍生版本，上游授权范围需发布者自行核对。README 中「已和原作者联系」属于历史声明，开源整理过程**未重新取证**。  
- 第三方资源（Bootstrap、Layer、jQuery、KindEditor、PHPMailer、字体、模板皮肤等）请保留其原有版权声明。  
- 在许可证确认前，建议仅作学习研究，不要声称本仓库整体为 MIT/Apache/GPL。  

详见仓库根目录 [`THIRD_PARTY_NOTICES.md`](./THIRD_PARTY_NOTICES.md)。