# 货源 API（彩虹同系统协议）

独立货源服务，供本站 `third_daishua` 插件通过 HTTP 真实对接。

## 本地启动

1. 主站：`start-local.bat` → `http://127.0.0.1:8080`
2. 货源：`start-supplier.bat` → `http://127.0.0.1:8081`（必须单独端口，否则 PHP 内置服务器会死锁）

账号：`supplier` / `supplier123`

## 对接配置

`pre_shequ.url` = `127.0.0.1:8081`，类型 `daishua`。  
商品 `is_curl=2`，`shequ=1`，`goods_id` = 货源 tid（1001–1005）。

导入：`install/_seed_api_dock.sql`  
后台测试：`/admin/api_dock.php`

## 上线真实货源

把对接站点 URL 改成对方域名（不要带 `http://`），账号密码改成对方提供的分站账号即可。日志见 `data/api.log`。
