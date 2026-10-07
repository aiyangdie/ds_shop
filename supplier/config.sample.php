<?php
/**
 * 资源站配置样例 — 复制为 config.php 后修改
 */
return [
	'user' => 'supplier',
	'pass' => '改成强密码',
	'allow_remote' => false, // 上线改为 true
	'name' => '我的资源站',
	'version' => '1.0',

	// 可售 AI 文案服务（不填则 ai_write 会拒绝接单）
	'ai_api_base' => 'https://api.deepseek.com/v1',
	'ai_api_key' => '',
	'ai_model' => 'deepseek-chat',
	'use_shop_ai' => false,
];
