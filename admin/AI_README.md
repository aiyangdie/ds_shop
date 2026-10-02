# AI运营助手

- 对话：`admin/ai.php`
- 日志：`admin/ai_log.php`
- 配置：`admin/ai_set.php`

会话/消息有数量上限；工具调用写入 `pre_ai_log`，删对话不删日志。首次访问自动建表，或执行 `install/ai_tables.sql`。
