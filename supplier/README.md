# 资源站套件（可自建、可对外提供）

本目录是一套**独立资源站**参考实现，协议见 [API.md](./API.md)。

别人可以用两种方式参与：

1. **当对接方**：在自己的商城里填你的资源站域名 + 账号，拉品下单  
2. **当资源方**：复制本目录到服务器，按规范提供 API，给多家商城供货  

商城侧插件：`includes/plugins/third_daishua.php`（对接类型：同系统对接）。

**重要：** `ping` 只证明协议通了；顾客买的是服务交付（体检报告、文案、兑换码等）。引擎在 `lib/Services.php`。

---

## 目录

| 文件 | 说明 |
|------|------|
| `API.md` | **协议规范**（给对接方 / 自建方读） |
| `api.php` | 可运行参考实现 |
| `lib/Services.php` | **可售服务引擎**（下单真实交付） |
| `config.sample.php` | 配置样例 |
| `config.php` | 实际配置（账号、远程、AI Key） |
| `data/goods.json` | 可售 SKU（含 `service` 字段） |
| `data/credits.json` | 兑换码库存（核销后更新） |
| `start-supplier.bat`（仓库根目录） | 本机 8081 启动 |

---

## 本地联调（本仓库）

1. 主站：`start-local.bat` → http://127.0.0.1:8080  
2. 资源站：`start-supplier.bat` → http://127.0.0.1:8081  
3. 探测：`GET http://127.0.0.1:8081/api.php?act=ping`  
4. 演示账号：`supplier` / `supplier123`（见 `config.php`）

商城对接站点：`url=127.0.0.1:8081`，类型 `daishua`，协议 HTTP。  
后台：`admin/api_dock.php` 测试连通；`admin/supplier_api.php` 查看协议摘要。

**禁止**资源站与主站共用同一 PHP 内置服务器端口，会自调用死锁。

---

## 给别人搭建资源站（上线）

1. 把整个 `supplier/` 目录上传到任意支持 PHP 7.4+ 的主机（可单独虚拟主机）  
2. 复制 `config.sample.php` → `config.php`  
3. 修改：
   - `user` / `pass`：强密码  
   - `allow_remote`：`true`（允许商城服务器访问）  
   - `name`：你的资源站名称  
4. Web 根目录指向含 `api.php` 的目录，确保外网可访问：  
   `https://你的域名/api.php?act=ping`  
5. 把域名、账号、密码发给对接商城；对方在「对接站点」填域名（不要带 `http://`）  
6. 维护 `data/goods.json`、`data/cards.json`，或改 `api.php` 接你自己的数据库  

生产环境建议：HTTPS、独立账号、IP 白名单、数据库存卡密、定期备份。

---

## 对接方（商城）怎么用你的站

1. 后台 → 对接站点管理 → 添加「同系统对接」  
2. 域名 / 账号 / 密码按资源站提供的填写  
3. 批量对接商品，或手动建商品并填 `goods_id` = 资源站 `tid`  
4. 价格监控：`cron.php?do=pricejk&key=监控密钥`  

详细字段与报文：[API.md](./API.md)
