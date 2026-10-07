<?php
/**
 * 资源站可售服务引擎
 * pay 时按商品 service 字段调用，交付真实结果（不是空壳卡密）
 */
class SupplierServices
{
	private $dataDir;
	private $cfg;

	public function __construct($dataDir, array $cfg)
	{
		$this->dataDir = rtrim($dataDir, '/\\');
		$this->cfg = $cfg;
	}

	/** 服务目录（给后台/文档展示） */
	public static function catalog()
	{
		return [
			'site_check' => [
				'title' => '网站体检报告',
				'value' => '输入网址，返回可达性、响应、HTTPS、标题等结构化报告',
				'need_ai' => false,
			],
			'text_tool' => [
				'title' => '文本/JSON 整理',
				'value' => '格式化 JSON、去多余空白、统计字数——下单即交付结果',
				'need_ai' => false,
			],
			'ai_write' => [
				'title' => 'AI 文案即时交付',
				'value' => '按主题生成可用文案，结果直接作为发货内容（需配置 AI Key）',
				'need_ai' => true,
			],
			'ai_credit_pack' => [
				'title' => 'AI 次数兑换码',
				'value' => '生成可核销的额度码，对接站用 act=redeem 扣次',
				'need_ai' => false,
			],
			'ticket' => [
				'title' => '人工工单',
				'value' => '接收需求，返回工单号，后台处理（非即时卡密）',
				'need_ai' => false,
			],
		];
	}

	/**
	 * @return array [ok=>bool, faka=>bool, kmdata=>string|null, status=>int, message=>string, meta=>array]
	 */
	public function deliver(array $tool, $num, $input1, $input2, $orderid)
	{
		$service = isset($tool['service']) ? $tool['service'] : '';
		if ($service === '' || $service === 'faka') {
			return ['ok' => false, 'message' => '该商品未绑定可执行服务'];
		}
		$method = 'svc_' . $service;
		if (!method_exists($this, $method)) {
			return ['ok' => false, 'message' => '未知服务: ' . $service];
		}
		return $this->$method($tool, max(1, intval($num)), trim((string)$input1), trim((string)$input2), $orderid);
	}

	/** 网站体检：真实请求目标站 */
	private function svc_site_check($tool, $num, $input1, $input2, $orderid)
	{
		$url = $input1;
		if (!preg_match('#^https?://#i', $url)) {
			$url = 'https://' . $url;
		}
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return ['ok' => false, 'message' => '请输入合法网址'];
		}
		$lines = [];
		for ($i = 0; $i < $num; $i++) {
			$report = $this->probeUrl($url);
			$lines[] = $this->formatReport($report, $orderid);
		}
		return [
			'ok' => true,
			'faka' => true,
			'kmdata' => implode("\n\n----\n\n", $lines),
			'status' => 1,
			'message' => 'ok',
			'meta' => ['service' => 'site_check'],
		];
	}

	private function probeUrl($url)
	{
		$t0 = microtime(true);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_CONNECTTIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_USERAGENT => 'SupplierSiteCheck/1.0',
			CURLOPT_HEADER => true,
		]);
		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		$err = curl_error($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		$ms = round((microtime(true) - $t0) * 1000);
		$title = '';
		$server = '';
		if (is_string($raw) && $raw !== '') {
			$parts = explode("\r\n\r\n", $raw, 2);
			$headers = isset($parts[0]) ? $parts[0] : '';
			$body = isset($parts[1]) ? $parts[1] : '';
			if (preg_match('/^Server:\s*(.+)$/mi', $headers, $m)) $server = trim($m[1]);
			if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
				$title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
				$title = mb_substr($title, 0, 80);
			}
		}
		return [
			'url' => $url,
			'ok' => ($errno === 0 && intval($info['http_code']) > 0),
			'http_code' => intval(isset($info['http_code']) ? $info['http_code'] : 0),
			'time_ms' => $ms,
			'ssl' => (stripos($url, 'https://') === 0),
			'final_url' => isset($info['url']) ? $info['url'] : $url,
			'title' => $title,
			'server' => $server,
			'error' => $errno ? $err : '',
			'size' => intval(isset($info['size_download']) ? $info['size_download'] : 0),
		];
	}

	private function formatReport(array $r, $orderid)
	{
		$score = 0;
		if ($r['ok'] && $r['http_code'] >= 200 && $r['http_code'] < 400) $score += 40;
		if ($r['ssl']) $score += 20;
		if ($r['time_ms'] > 0 && $r['time_ms'] < 1500) $score += 20;
		elseif ($r['time_ms'] < 3000) $score += 10;
		if ($r['title'] !== '') $score += 20;
		$lines = [
			'【网站体检报告】订单 ' . $orderid,
			'目标：' . $r['url'],
			'可达：' . ($r['ok'] ? '是' : '否') . ($r['error'] ? '（' . $r['error'] . '）' : ''),
			'HTTP：' . $r['http_code'],
			'耗时：' . $r['time_ms'] . ' ms',
			'HTTPS：' . ($r['ssl'] ? '是' : '否'),
			'标题：' . ($r['title'] !== '' ? $r['title'] : '（未识别）'),
			'Server：' . ($r['server'] !== '' ? $r['server'] : '（未知）'),
			'终址：' . $r['final_url'],
			'体积：' . $r['size'] . ' bytes',
			'综合分：' . $score . '/100',
			'建议：' . ($score >= 80 ? '基础健康良好' : ($score >= 50 ? '可访问，建议优化速度/HTTPS/标题' : '存在访问或配置问题，请排查')),
		];
		return implode("\n", $lines);
	}

	/** 文本/JSON 整理 */
	private function svc_text_tool($tool, $num, $input1, $input2, $orderid)
	{
		$text = $input2 !== '' ? $input2 : $input1;
		if ($text === '') {
			return ['ok' => false, 'message' => '请提交要整理的文本或 JSON'];
		}
		$trimmed = trim($text);
		$decoded = json_decode($trimmed, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			$out = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			$kind = 'JSON 已格式化';
		} else {
			$out = preg_replace("/[ \t]+/u", ' ', $trimmed);
			$out = preg_replace("/\n{3,}/u", "\n\n", $out);
			$kind = '文本已清理空白';
		}
		$chars = mb_strlen($trimmed);
		$km = "【文本整理】订单 {$orderid}\n类型：{$kind}\n原文字数：{$chars}\n\n" . $out;
		return [
			'ok' => true,
			'faka' => true,
			'kmdata' => $km,
			'status' => 1,
			'message' => 'ok',
			'meta' => ['service' => 'text_tool'],
		];
	}

	/** AI 文案：调用配置的模型，结果直接发货 */
	private function svc_ai_write($tool, $num, $input1, $input2, $orderid)
	{
		$topic = $input1;
		$extra = $input2;
		if (mb_strlen($topic) < 2) {
			return ['ok' => false, 'message' => '请填写文案主题（至少2字）'];
		}
		$ai = $this->aiConfig();
		if ($ai['base'] === '' || $ai['key'] === '') {
			return ['ok' => false, 'message' => '资源站未配置 AI（supplier/config.php 填写 ai_api_base / ai_api_key），无法交付文案服务'];
		}
		$parts = [];
		for ($i = 0; $i < $num; $i++) {
			$prompt = "请根据主题写一篇可直接使用的中文营销/说明文案，结构清晰，300-600字。\n主题：" . $topic;
			if ($extra !== '') $prompt .= "\n补充要求：" . $extra;
			try {
				$content = $this->chatCompletions($ai, $prompt);
			} catch (Exception $e) {
				return ['ok' => false, 'message' => 'AI 交付失败：' . $e->getMessage()];
			}
			$parts[] = "【AI文案】订单 {$orderid}" . ($num > 1 ? " #" . ($i + 1) : '') . "\n主题：{$topic}\n\n" . $content;
		}
		return [
			'ok' => true,
			'faka' => true,
			'kmdata' => implode("\n\n====\n\n", $parts),
			'status' => 1,
			'message' => 'ok',
			'meta' => ['service' => 'ai_write'],
		];
	}

	/** 生成 AI 次数兑换码包 */
	private function svc_ai_credit_pack($tool, $num, $input1, $input2, $orderid)
	{
		$perPack = isset($tool['credit_quota']) ? max(1, intval($tool['credit_quota'])) : 10;
		$file = $this->dataDir . '/credits.json';
		$credits = $this->readJson($file, []);
		$codes = [];
		for ($i = 0; $i < $num; $i++) {
			$code = 'AI-' . strtoupper(substr(md5(uniqid((string)$orderid, true) . $i), 0, 12));
			$credits[] = [
				'code' => $code,
				'quota' => $perPack,
				'left' => $perPack,
				'orderid' => (string)$orderid,
				'buyer' => $input1,
				'created' => date('Y-m-d H:i:s'),
				'status' => 1,
			];
			$codes[] = $code . ' 额度' . $perPack . '次';
		}
		$this->writeJson($file, $credits);
		$km = "【AI次数兑换码】订单 {$orderid}\n核销：POST /api.php?act=redeem  code=兑换码\n收货标识：{$input1}\n\n" . implode("\n", $codes);
		return [
			'ok' => true,
			'faka' => true,
			'kmdata' => $km,
			'status' => 1,
			'message' => 'ok',
			'meta' => ['service' => 'ai_credit_pack', 'codes' => $codes],
		];
	}

	/** 人工工单 */
	private function svc_ticket($tool, $num, $input1, $input2, $orderid)
	{
		$file = $this->dataDir . '/tickets.json';
		$tickets = $this->readJson($file, []);
		$ticketNo = 'TK' . $orderid;
		$tickets[] = [
			'ticket' => $ticketNo,
			'orderid' => (string)$orderid,
			'contact' => $input1,
			'need' => $input2,
			'status' => 0,
			'addtime' => date('Y-m-d H:i:s'),
		];
		$this->writeJson($file, $tickets);
		return [
			'ok' => true,
			'faka' => false,
			'kmdata' => null,
			'status' => 0,
			'message' => 'ok',
			'meta' => ['service' => 'ticket', 'ticket' => $ticketNo],
			// 给对接方一个可读回执（部分商城会把 message 当提示）
			'receipt' => '工单已创建：' . $ticketNo . '，将联系 ' . $input1,
		];
	}

	/** 核销兑换码 */
	public function redeem($code, $use = 1)
	{
		$code = trim($code);
		$use = max(1, intval($use));
		if ($code === '') {
			return ['code' => -1, 'message' => '缺少兑换码'];
		}
		$file = $this->dataDir . '/credits.json';
		$credits = $this->readJson($file, []);
		foreach ($credits as &$row) {
			if (strcasecmp($row['code'], $code) !== 0) continue;
			if (intval($row['status']) !== 1 || intval($row['left']) <= 0) {
				return ['code' => -1, 'message' => '兑换码已用尽或失效'];
			}
			if (intval($row['left']) < $use) {
				return ['code' => -1, 'message' => '剩余次数不足，当前剩余 ' . $row['left']];
			}
			$row['left'] = intval($row['left']) - $use;
			if ($row['left'] <= 0) $row['status'] = 0;
			$this->writeJson($file, $credits);
			return [
				'code' => 0,
				'message' => '核销成功',
				'data' => [
					'code' => $row['code'],
					'used' => $use,
					'left' => $row['left'],
					'quota' => $row['quota'],
				],
			];
		}
		unset($row);
		return ['code' => -1, 'message' => '兑换码不存在'];
	}

	private function aiConfig()
	{
		$base = isset($this->cfg['ai_api_base']) ? trim($this->cfg['ai_api_base']) : '';
		$key = isset($this->cfg['ai_api_key']) ? trim($this->cfg['ai_api_key']) : '';
		$model = isset($this->cfg['ai_model']) ? trim($this->cfg['ai_model']) : 'deepseek-chat';
		// 可选：从主站配置文件旁路读取（同机部署时）
		if (($base === '' || $key === '') && !empty($this->cfg['use_shop_ai'])) {
			$shop = $this->loadShopAi();
			if ($shop) {
				if ($base === '') $base = $shop['base'];
				if ($key === '') $key = $shop['key'];
				if (!empty($shop['model'])) $model = $shop['model'];
			}
		}
		return ['base' => rtrim($base, '/'), 'key' => $key, 'model' => $model];
	}

	private function loadShopAi()
	{
		$root = dirname($this->dataDir);
		// dataDir = supplier/data → parent supplier → shop root
		$shopRoot = dirname($root);
		$cfgFile = $shopRoot . '/config.php';
		if (!is_file($cfgFile)) return null;
		$dbconfig = null;
		include $cfgFile;
		if (!isset($dbconfig) || !is_array($dbconfig)) return null;
		try {
			$dsn = 'mysql:host=' . $dbconfig['host'] . ';port=' . (isset($dbconfig['port']) ? $dbconfig['port'] : 3306) . ';dbname=' . $dbconfig['dbname'] . ';charset=utf8mb4';
			$pdo = new PDO($dsn, $dbconfig['user'], $dbconfig['pwd'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
			$qz = isset($dbconfig['dbqz']) ? $dbconfig['dbqz'] : 'shua';
			$rows = $pdo->query("SELECT k,v FROM `{$qz}_config` WHERE k IN ('ai_api_base','ai_api_key','ai_model')")->fetchAll(PDO::FETCH_KEY_PAIR);
			if (!$rows) return null;
			return [
				'base' => isset($rows['ai_api_base']) ? $rows['ai_api_base'] : '',
				'key' => isset($rows['ai_api_key']) ? $rows['ai_api_key'] : '',
				'model' => isset($rows['ai_model']) ? $rows['ai_model'] : '',
			];
		} catch (Exception $e) {
			return null;
		}
	}

	private function chatCompletions(array $ai, $prompt)
	{
		$url = $ai['base'] . '/chat/completions';
		$body = json_encode([
			'model' => $ai['model'],
			'messages' => [
				['role' => 'system', 'content' => '你是专业文案助手，只输出成品文案，不要解释过程。'],
				['role' => 'user', 'content' => $prompt],
			],
			'temperature' => 0.7,
		], JSON_UNESCAPED_UNICODE);
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $ai['key'],
			],
		]);
		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		$err = curl_error($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		curl_close($ch);
		if ($errno) throw new Exception($err);
		$data = json_decode($raw, true);
		if (!is_array($data)) throw new Exception('模型返回非 JSON HTTP ' . $code);
		if ($code >= 400) {
			$msg = isset($data['error']['message']) ? $data['error']['message'] : 'HTTP ' . $code;
			throw new Exception($msg);
		}
		$text = isset($data['choices'][0]['message']['content']) ? trim($data['choices'][0]['message']['content']) : '';
		if ($text === '') throw new Exception('模型未返回内容');
		return $text;
	}

	private function readJson($file, $default)
	{
		if (!is_file($file)) return $default;
		$data = json_decode(file_get_contents($file), true);
		return is_array($data) ? $data : $default;
	}

	private function writeJson($file, $data)
	{
		return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
	}
}
