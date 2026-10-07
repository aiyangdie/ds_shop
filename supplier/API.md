# 资源站 API 规范（daishua / 同系统协议）

版本：**1.0**  
兼容插件：`includes/plugins/third_daishua.php`（商城对接类型选「同系统对接」）  
参考实现：本目录 `api.php`（可直接部署为独立资源站）

本规范描述：**分站/商城（客户端）** 如何调用 **资源站/货源站（服务端）**。  
任何人只要按本规范实现接口，即可被本商城（及兼容彩虹同系统对接的站点）接入。

---

## 1. 角色与流程

```
用户下单 → 商城扣款 → third_daishua HTTP 调用资源站 → 资源站发卡/接单 → 回传订单号/卡密
价格监控 → cron pricejk → goodslist → 回写本站成本价/库存/上下架
```

| 角色 | 说明 |
|------|------|
| 商城 | 配置对接站点：域名、账号、密码；商品 `is_curl=2`，`goods_id`=资源站 `tid` |
| 资源站 | 对外提供 `/api.php?act=...`，校验分站账号，维护商品、卡密、订单 |

---

## 2. 通信约定

| 项 | 要求 |
|----|------|
| Base URL | `http(s)://{host}/api.php`（商城配置里只填 `host`，不要带协议和路径） |
| 方法 | 业务参数用 **POST**（`application/x-www-form-urlencoded`）；`act` 用 **GET** |
| 编码 | UTF-8 |
| 响应 | `Content-Type: application/json; charset=UTF-8` |
| 鉴权 | 每个接口 POST：`user` + `pass`（资源站为对接方分配的账号密码） |
| 成功 | `code === 0`（下单成功时也可能直接带 `orderid`，见 `pay`） |
| 失败 | `code != 0`，并带 `message`（字符串） |

统一失败示例：

```json
{"code": -1, "message": "用户名或密码不正确"}
```

---

## 3. 接口一览

| act | 用途 | 商城何时调用 |
|-----|------|----------------|
| `classlist` | 分类列表 | 批量对接 / 选分类拉商品 |
| `goodslist` | 全部商品简表 | **价格监控**、连通测试 |
| `goodslistbycid` | 按分类商品详情列表 | 批量对接 |
| `goodsdetails` | 单商品详情 | 同步商品信息 |
| `pay` | 下单（发卡或接单） | **用户付款成功后** |
| `search` / `query` | 查订单状态 | 订单查询 / 对接补查 |

---

## 4. 接口详情

### 4.1 分类列表 `act=classlist`

**POST：** `user` `pass`

**成功：**

```json
{
  "code": 0,
  "msg": "succ",
  "count": 2,
  "data": [
    {"cid": 1, "name": "热门商品", "active": 1, "sort": 1}
  ]
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| cid | int | 分类 ID |
| name | string | 分类名 |
| active | int | 1 启用 |
| sort | int | 排序，越小越前 |

---

### 4.2 全部商品简表 `act=goodslist`

**POST：** `user` `pass`

用于价格监控，字段宜精简。

```json
{
  "code": 0,
  "data": [
    {
      "tid": 1001,
      "id": 1001,
      "cid": 1,
      "name": "网盘VIP月卡",
      "shopimg": "",
      "close": 0,
      "price": "12.00",
      "isfaka": 1,
      "stock": 20
    }
  ]
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| tid | int | 是 | 资源站商品 ID（商城 `goods_id`） |
| id | int | 建议 | 兼容字段，等于 `tid` |
| cid | int | 是 | 分类 |
| name | string | 是 | 名称 |
| price | string/number | 是 | 成本价（商城按加价模板计算售价） |
| close | int | 是 | 1=禁止下单/下架 |
| shopimg | string | 否 | 图片 URL 或相对路径 |
| isfaka | int | 否 | 1=卡密商品 |
| stock | int/null | 否 | 卡密库存；非卡密可 `null` |

---

### 4.3 按分类商品 `act=goodslistbycid`

**POST：** `user` `pass` `cid`（0 或省略表示全部）

```json
{
  "code": 0,
  "msg": "succ",
  "count": 1,
  "data": [
    {
      "tid": 1001,
      "cid": 1,
      "sort": 10,
      "name": "网盘VIP月卡",
      "value": 1,
      "price": "12.00",
      "input": "收货邮箱",
      "inputs": "",
      "desc": "商品说明",
      "alert": "下单提示",
      "shopimg": "",
      "validate": 0,
      "valiserv": "",
      "repeat": 1,
      "multi": 1,
      "close": 0,
      "prices": "",
      "min": 1,
      "max": 5,
      "sales": 0,
      "isfaka": 1,
      "stock": 20
    }
  ]
}
```

| 字段 | 说明 |
|------|------|
| value | 数量倍率（通常 1） |
| input | 第一个下单输入框标题 |
| inputs | 更多输入框，多个可用 `\|` 分隔 |
| multi | 1=允许购买多份 |
| min / max | 份数范围 |
| prices | 可选，多级价格扩展（可空字符串） |

---

### 4.4 商品详情 `act=goodsdetails`

**POST：** `user` `pass` `tid`

成功：`{"code":0,"data":{...}}`，`data` 字段同 4.3 单条。  
失败：`{"code":-1,"message":"商品不存在"}`

库存为 0 的卡密商品建议返回 `close=1`。

---

### 4.5 下单 `act=pay`

**POST：**

| 参数 | 说明 |
|------|------|
| user / pass | 鉴权 |
| tid | 商品 ID |
| num | 购买份数，默认 1 |
| input1 | 必填，第一下单信息 |
| input2… | 可选，对应 `inputs` |

**卡密商品成功：**

```json
{
  "code": 0,
  "orderid": "1728000000123",
  "faka": true,
  "kmdata": "CARD-001 密码\nCARD-002 密码"
}
```

**非卡密（人工/异步）成功：**

```json
{
  "code": 0,
  "orderid": "1728000000456"
}
```

| 字段 | 说明 |
|------|------|
| orderid | **必填**，资源站订单号（商城写入 `djorder`） |
| faka | 为 true 时立即发卡 |
| kmdata | 字符串（推荐 `\n` 分行）或数组；每行可为「卡密」或「卡密 密码」 |

失败：库存不足、维护中等，返回 `message`。

---

### 4.6 查单 `act=search`（或 `query`）

**POST：** `user` `pass`，订单号可用 POST/GET 的 `id`

```json
{
  "code": 0,
  "status": 1,
  "data": {
    "orderid": "1728000000123",
    "tid": 1001,
    "num": 1,
    "status": 1
  }
}
```

| status | 含义（与商城约定） |
|--------|-------------------|
| 0 | 待处理 |
| 1 | 已完成 |
| 2 | 处理中 |
| 3 | 异常 |
| 4 | 已退单 |

---

## 5. 商城侧配置清单

1. 后台 → **对接站点管理**：类型选「同系统对接 / daishua」  
2. **网站域名**：只填主机（如 `api.example.com` 或 `127.0.0.1:8081`）  
3. 协议：HTTP / HTTPS  
4. 登录账号 / 密码：资源站分配  
5. 商品：对接模式、对接站点、货源商品 ID = 资源站 `tid`  
6. 加价模板：`prid > 0` 的商品才会参与价格监控改价  

本地演示：主站 `8080` + 资源站 `8081`（**必须分端口**，PHP 内置服务器同端口会死锁）。

---

## 6. 自建资源站最低要求

实现 `goodslist` + `goodsdetails` + `pay` + `search` 即可完成「同步价格 / 下单 / 查单」。  
`classlist`、`goodslistbycid` 用于后台批量对接，强烈建议一并实现。

安全建议：

- 为每个对接分站独立账号，可限 IP  
- HTTPS 上线；勿把演示默认密码用于公网  
- 卡密发放须原子扣库存，防超卖  
- 记录 `pay` 审计日志  

本仓库参考实现：

```
supplier/
  API.md           ← 本规范
  README.md        ← 搭建说明
  config.sample.php
  api.php          ← 可运行参考实现
  data/            ← JSON 演示数据（可换成 MySQL）
```

复制 `supplier/` 到任意 PHP 主机，按 `README.md` 配置后，即为独立资源站。

---

## 7. 可售服务（本参考实现）

协议本身不产生收入；**卖的是 `pay` 之后引擎交付的结果**。  
本仓库 `lib/Services.php` 已挂好以下服务（商品 `service` 字段）：

| tid | service | 卖点 | 交付 |
|-----|---------|------|------|
| 1001 | `site_check` | 网站体检 | 报告文本（kmdata） |
| 1002 | `text_tool` | 文本/JSON 整理 | 整理结果（kmdata） |
| 1003 | `ai_write` | AI 文案 | 成品文案（需 AI Key） |
| 1004 | `ai_credit_pack` | 额度兑换码 | 可核销码（kmdata） |
| 1005 | `ticket` | 人工代办 | 工单号（异步） |

### 7.1 服务目录 `act=servicelist`

**POST：** `user` `pass` → 返回服务说明字典。

### 7.2 兑换码核销 `act=redeem`

**POST：** `user` `pass` `code` `use`(次数，默认1)

```json
{"code":0,"message":"核销成功","data":{"code":"AI-xxx","used":1,"left":9,"quota":10}}
```

适合：你把额度卖给下游，下游业务每次调用时扣次。

### 7.3 如何加一个新的可卖服务

1. 在 `lib/Services.php` 增加 `svc_你的服务`，返回 `ok/faka/kmdata/status`  
2. 在 `catalog()` 登记说明  
3. `data/goods.json` 增加商品并设置 `"service":"你的服务"`  
4. 商城对接该 `tid` 加价销售  

---

## 8. 兼容性说明

- 协议名历史称呼：彩虹「同系统对接」、插件名 `third_daishua`  
- 未知 `act` 建议：`{"code":-1,"message":"未知接口 act"}`  
- `act=ping` 示例：

```json
{"code":0,"protocol":"daishua","version":"1.0","name":"演示资源站","services":["site_check","text_tool","ai_write","ai_credit_pack","ticket"]}
```
