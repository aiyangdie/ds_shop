# 自建安卓壳 AppShell

## 环境要求（打包机）

- JDK 17（推荐）
- Android SDK（API 34）
- 环境变量 `ANDROID_HOME` 或后台配置 `appcreate_sdk_dir`
- 可选：安装 Gradle，或使用 Android Studio 自带

## 本地验证（阶段0）

```bash
php tools/appbuild/worker.php --demo
```

会在 `tools/appbuild/jobs/demo1|demo2/project` 生成两套不同包名/主题的工程。

有 SDK 时：

```bash
cd tools/appbuild/jobs/demo1/project
# 写入 local.properties: sdk.dir=...
gradle assembleRelease
```

## 正式队列

```bash
# crontab 每分钟
php /path/to/site/tools/appbuild/worker.php --once

# 重试某个任务
# 先在后台点重试，或 SQL 把 build_status 置 0，再：
php tools/appbuild/worker.php --id=123
```

## 站点配置

后台「APP在线生成」：

- 生成模式 = **本地工厂 (local)**
- 包名前缀，如 `com.yourbrand.app`
- 可选 SDK 目录
