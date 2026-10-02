# AI 运营助手

用自然语言直接操作本站（订单 / 商品 / 分类 / 分站 / 配置 / 货源），无需点后台页面或进用户分站。

## 后台入口

1. **模型配置** `admin/ai_set.php` — 选预设（DeepSeek / OpenAI / 通义 / Kimi / 智谱 / 硅基流动 / 自定义），填 API Key，开启 AI。
2. **对话操作** `admin/ai.php` — 登录后台后直接对话下指令。

## 外部 Agent 接口

`POST /ai_api.php`  
Header: `Authorization: Bearer {ai_api_token}`（在模型配置页生成）

```json
{"message": "列出最近未处理订单", "history": []}
```

也可：

- `?act=tools` 查看可用工具
- `{"act":"tool","name":"dashboard_stats","arguments":{}}` 直接调工具

## 已接工具

经营概况、搜/改订单、退款、商品 CRUD/上下架、分类、分站启停与充值、白名单配置读写、对接站点与货源拉品。
