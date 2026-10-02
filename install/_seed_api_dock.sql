-- 注意：Windows 下用 PowerShell 管道导入容易把中文变成 ????
-- 请改用：浏览器访问 /install/seed_api_dock.php 或 php install/seed_api_dock.php（经 HTTP）
-- 本文件仅作结构参考；正式写入请用 seed_api_dock.php

SET NAMES utf8mb4;

-- 清空旧演示假货，改用 API 对接商品
TRUNCATE TABLE `shua_faka`;
TRUNCATE TABLE `shua_tools`;
TRUNCATE TABLE `shua_class`;
TRUNCATE TABLE `shua_shequ`;

INSERT INTO `shua_class` (`cid`,`zid`,`sort`,`name`,`active`) VALUES
(1,1,1,'热门API商品',1),
(2,1,2,'额度与工具',1),
(3,1,3,'人工服务',1);

-- 对接站：同系统协议，指向本地 supplier 货源
INSERT INTO `shua_shequ`
(`id`,`url`,`username`,`password`,`paypwd`,`paytype`,`type`,`result`,`status`,`remark`,`protocol`,`monitor`)
VALUES
(1, '127.0.0.1:8081', 'supplier', 'supplier123', NULL, 0, 'daishua', 1, 1, '本地货源API@8081（上线改真实域名；勿与主站同端口）', 0, 0);

-- is_curl=2 表示走社区/插件对接；shequ=1；goods_id=货源商品tid
INSERT INTO `shua_tools`
(`tid`,`zid`,`cid`,`sort`,`name`,`value`,`price`,`prid`,`cost`,`cost2`,`input`,`inputs`,`desc`,`alert`,`min`,`max`,`is_curl`,`repeat`,`multi`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`close`,`active`,`sales`,`stock`,`addtime`)
VALUES
(1,1,1,1,'网盘VIP月卡（API对接）',1,15.00,1,12.00,12.50,'收货邮箱',NULL,
 '下单后系统会真实请求货源 API 自动发货。','支付成功后在订单详情查看卡密。',1,5,2,1,1,1,1001,0,NULL,0,1,0,NULL,NOW()),
(2,1,1,2,'短视频去水印额度包（API对接）',1,4.50,1,3.50,3.80,'手机号',NULL,
 '对接货源API发放额度卡密。','请填写正确手机号。',1,20,2,1,1,1,1002,0,NULL,0,1,0,NULL,NOW()),
(3,1,2,1,'AI写作次数包100次（API对接）',1,12.90,1,9.90,10.50,'收货邮箱',NULL,
 '货源API返回兑换码。','兑换码请自行保存。',1,10,2,1,1,1,1003,0,NULL,0,1,0,NULL,NOW()),
(4,1,2,2,'短信通知接口额度（API对接）',1,8.00,1,6.00,6.50,'商户标识',NULL,
 'API额度卡密由货源返回。','按卡密说明充值。',1,10,2,1,1,1,1004,0,NULL,0,1,0,NULL,NOW()),
(5,1,3,1,'人工代办加急（API接单）',1,18.00,1,15.00,16.00,'联系微信','需求说明',
 '下单后请求货源API生成工单号。','客服将联系你处理。',1,1,2,0,0,1,1005,0,NULL,0,1,0,NULL,NOW());

UPDATE `shua_config` SET `v`='彩虹API对接商城' WHERE `k`='sitename';
UPDATE `shua_config` SET `v`='API对接,自动发货,货源直连' WHERE `k`='keywords';
UPDATE `shua_config` SET `v`='本站商品通过 HTTP API 对接货源，下单后真实请求供应商接口完成发货。' WHERE `k`='description';
UPDATE `shua_config` SET `v`='<p><b>本站已启用真实API对接</b></p><p>商品下单后会请求货源接口发货，不是本地假卡密。</p><p>后台可在「货源API对接」查看与测试。</p>' WHERE `k`='anounce';

-- 开启余额支付方便本地测试（type 相关配置）
INSERT INTO `shua_config`(`k`,`v`) VALUES ('alipay_api','0') ON DUPLICATE KEY UPDATE `v`='0';
INSERT INTO `shua_config`(`k`,`v`) VALUES ('qqpay_api','0') ON DUPLICATE KEY UPDATE `v`='0';
INSERT INTO `shua_config`(`k`,`v`) VALUES ('wxpay_api','0') ON DUPLICATE KEY UPDATE `v`='0';

DELETE FROM `shua_cache` WHERE `k`='config';
DELETE FROM `shua_cache` WHERE `k`='ThirdPluginsList';
