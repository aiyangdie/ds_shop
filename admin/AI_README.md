# AI 助手说明

站点名、助手称呼来自配置（`sitename` / `ai_assistant_name`），不写死品牌。

| 页面 | 路径 |
|------|------|
| 后台对话 | `admin/ai.php` |
| 客服中心 | `admin/ai_cs.php` |
| 操作日志 | `admin/ai_log.php` |
| 模型配置 | `admin/ai_set.php` |
| 分站对话 | `user/ai.php` |
| 前台浮窗 | 模板脚部 `includes/ai_widget_inc.php` |

自定义提示词可用变量：`{sitename}` `{assistant_name}`。

更完整的能力说明与部署注意见根目录 [README.md](../README.md)「本仓库增强更新说明」。
