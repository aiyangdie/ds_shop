# 本地 App 工厂（存储与下载）

## APK 怎么存（已定）

| 策略 | 说明 |
|------|------|
| `auto`（默认） | 跟随「AI模型配置 → 附件存储」：local / cos / oss / qiniu |
| `local` | 强制 `assets/uploads/apps/` |
| `cloud` | 强制用附件那套云配置上传 |

库表 `pre_apps` **只存 URL + 大小 + 驱动名**，不把 APK 塞进 MySQL。  
云上传失败会自动回退本地，不阻断交付。

## 下载页 `/?mod=app&id=`

三种状态：

1. **生成中**：进度条、提交/更新时间、每 5 秒自动刷新  
2. **成功**：下载按钮、二维码（扫下载页）、提交/完成/耗时/大小/存储、安装说明  
3. **失败**：错误原因 + 返回  

## 后台

- APP生成配置：可改「APK 存储位置」  
- 「查看本地队列」：提交/完成时间、耗时、进度、大小、存储、下载页链接  

## Worker

```bat
tools\appbuild\run_worker.bat
```

打完包后调用 `Storage\Manager::uploadAppApk` 发布。
