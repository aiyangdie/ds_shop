<?php
namespace lib\Ai;

/**
 * 多轮工具调用 Agent（按 role: admin|user|shop 使用不同提示词）
 */
class Agent
{
    private $client;
    private $tools;
    private $model;
    private $temperature;
    private $maxTokens;
    private $systemPrompt;
    private $maxRounds;
    private $onTool;
    private $site;
    private $role;

    public function __construct(Client $client, Tools $tools, array $opts = array())
    {
        $this->client = $client;
        $this->tools = $tools;
        $this->model = isset($opts['model']) ? $opts['model'] : 'gpt-4o-mini';
        $this->temperature = isset($opts['temperature']) ? floatval($opts['temperature']) : 0.2;
        $this->maxTokens = isset($opts['max_tokens']) ? intval($opts['max_tokens']) : 4096;
        $this->maxRounds = isset($opts['max_rounds']) ? intval($opts['max_rounds']) : 8;
        $this->onTool = isset($opts['on_tool']) && is_callable($opts['on_tool']) ? $opts['on_tool'] : null;
        $this->site = isset($opts['site']) && is_array($opts['site']) ? $opts['site'] : array();
        $this->role = isset($opts['role']) ? strtolower(trim($opts['role'])) : 'admin';
        if (!in_array($this->role, array('admin', 'user', 'shop'), true)) {
            $this->role = 'admin';
        }
        $custom = isset($opts['system_prompt']) ? trim($opts['system_prompt']) : '';
        $this->systemPrompt = $this->buildPrompt($custom);
    }

    private function siteName()
    {
        $n = isset($this->site['sitename']) ? trim($this->site['sitename']) : '';
        return $n !== '' ? $n : '本站';
    }

    private function assistantName()
    {
        $n = isset($this->site['assistant_name']) ? trim($this->site['assistant_name']) : '';
        return $n !== '' ? $n : '助手';
    }

    private function applyVars($text)
    {
        return str_replace(
            array('{sitename}', '{assistant_name}'),
            array($this->siteName(), $this->assistantName()),
            $text
        );
    }

    private function buildPrompt($custom)
    {
        if ($custom !== '') {
            return $this->applyVars($custom);
        }
        if ($this->role === 'shop') {
            return $this->promptShop();
        }
        if ($this->role === 'user') {
            return $this->promptUser();
        }
        return $this->promptAdmin();
    }

    private function promptAdmin()
    {
        $site = $this->siteName();
        $as = $this->assistantName();
        return "你是通用自助商城框架的{$as}，当前站点显示名为「{$site}」。用户可随时改名，改完后请用新站名和新助手称呼，禁止沿用任何写死的旧品牌名。\n"
            . "目标：尽量用工具替用户完成设置与日常运营，减少他们打开后台页面。\n"
            . "工具调用会记详细日志，写操作要谨慎。\n\n"
            . "初始化与改品牌：\n"
            . "- 用户说改站名、起网名、换助手称呼、改公告/简介/客服/底部/弹窗/查询提示/前台模板时，用 setup_site（confirm=true），或 update_config。\n"
            . "- 不确定现有配置时先 get_config；换模板前可 list_templates。\n"
            . "- 系统设置能力用 config_catalog 查看：网站信息、分站、公告、邮箱提醒、支付开关、模板、快捷登录、验证IP、代理、计划任务参数、签到推广抽奖等大多可改。\n"
            . "- 密钥类（支付商户密钥、邮箱密码、验证码key、代理密码、cronkey、AI Key）不能通过工具写入，因操作日志会明文记录；请告知用户去后台对应页面填写。\n"
            . "- Logo/背景图片上传不能走对话，请用户后台上传。\n"
            . "- 数据清理用 clean_data，必须 confirm=true，并先说明不可恢复。\n"
            . "- 用户只给了站名时，可主动问是否一并设置简介、客服QQ、首页公告、助手称呼。\n"
            . "- 完善后用一两句话确认当前站名与关键设置，并提示可以开始查订单、管商品。\n\n"
            . "日常能力：经营概况；订单查改/导出/批量/退款/重对接；商品增删改/复制/移动/库存/批量改名/挂加价模板；分类增删；分站余额续期/单独加价/删除；普通用户；余额流水；推广商品与链接记录；对接货源批量同步；对接日志；支付单；工单回复可发邮件；客服中心转人工工单(list_ai_cs/get_ai_cs/reply_ai_cs)；发卡与兑换/加款卡密；文章；站内通知；加价模板；抽奖奖品；提现；排行；防红短链；前台模板；数据清理。可用 capability_catalog 查看全部工具。\n\n"
            . "回复规范：\n"
            . "1. 简洁中文，不用 Markdown、星号、井号、反引号、表情。\n"
            . "2. 分点用 1. 2. 3.。\n"
            . "3. 订单状态说中文：未处理、已完成、处理中、异常、已退款。\n"
            . "4. 先查再改。退款、充值、扣款、批量改状态、删除商品/分类/卡密、重对接、处理提现、回复工单、改站点设置、清理数据等须用户确认，confirm=true。\n"
            . "5. 不编造数据；以工具返回为准。\n"
            . "6. 自称用当前助手称呼，提到本站时用当前 sitename。\n"
            . "7. 发卡卡密用 add_faka；兑换卡/加款卡用 generate_kms（type=1/0），别混用。";
    }

    private function promptUser()
    {
        $site = $this->siteName();
        $as = $this->assistantName();
        $power = isset($this->site['power']) ? intval($this->site['power']) : 0;
        $extra = $power > 0
            ? "你还可协助分站主查看本站商品分类、修改本站显示信息（站名/标题/关键词/简介/客服QQ），写操作需 confirm=true。不能改主站支付密钥、AI Key、全局系统设置。"
            : "你主要帮助普通用户查自己的订单、余额、工单。";
        return "你是「{$site}」用户中心的{$as}。{$extra}\n"
            . "只能操作当前登录用户自己的数据，禁止猜测他人订单。\n"
            . "回复规范：简洁中文；不用 Markdown/星号/表情；分点用 1. 2. 3.；订单状态用中文；不编造；写操作先确认再 confirm=true。\n"
            . "复杂纠纷可引导用户去工单或联系客服；售后可解释订单状态并建议提交工单。AI 不能退款，不要假装已处理退款。";
    }

    private function promptShop()
    {
        $site = $this->siteName();
        $as = $this->assistantName();
        $kf = isset($this->site['kfqq']) && $this->site['kfqq'] !== '' ? ('客服QQ：' . $this->site['kfqq']) : '需要人工时引导用户打开转人工表单';
        $ctx = '';
        if (!empty($this->site['context_tid'])) {
            $ctx = "用户当前可能正在浏览商品 tid=" . intval($this->site['context_tid']) . "，优先用 shop_explain_goods / shop_get_goods 解释该商品。\n";
        }
        return "你是「{$site}」前台智能客服{$as}，帮助访客了解商品、填写下单信息、挑选商品、查询订单与售后自助。\n"
            . $ctx
            . "能力：shop_list_classes、shop_search_goods、shop_get_goods、shop_recommend_goods、shop_explain_goods、shop_site_faq、shop_query_order、shop_aftersale_help、shop_transfer_human（仅引导，不建单）。\n"
            . "禁止：代用户下单、收款、退款、改价、读取或索要支付密码/卡密密钥/后台密钥；禁止在对话里假装已提交人工工单。\n"
            . "推荐：按场景选 mode=similar/hot/price/keyword；列出 tid、名称、价格、理由，提示用户点链接自行下单。\n"
            . "解释商品：卖什么、怎么填、注意事项、是否发卡、库存。\n"
            . "查单：优先要订单号或支付单号。用户只有下单账号时，必须再要金额或下单日期，再调 shop_query_order。\n"
            . "售后：先 shop_query_order / shop_aftersale_help；解决不了时用 shop_transfer_human 引导用户点击浮窗「转人工」自行提交，不要声称你已建单。{$kf}。\n"
            . "回复规范：简洁中文；不用 Markdown/星号/表情；分点用 1. 2. 3.；不编造商品信息，以工具返回为准。";
    }

    public function run(array $messages)
    {
        $full = array_merge(
            array(array('role' => 'system', 'content' => $this->systemPrompt)),
            $messages
        );
        $toolDefs = $this->tools->definitions();
        $trace = array();
        $usage = array('prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0);

        // shop/user 无工具时仍可对话
        for ($round = 0; $round < $this->maxRounds; $round++) {
            $payload = array(
                'model' => $this->model,
                'messages' => $full,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            );
            if ($toolDefs && count($toolDefs) > 0) {
                $payload['tools'] = $toolDefs;
                $payload['tool_choice'] = 'auto';
            }
            $resp = $this->client->chat($payload);
            if (isset($resp['usage']) && is_array($resp['usage'])) {
                foreach (array('prompt_tokens', 'completion_tokens', 'total_tokens') as $uk) {
                    if (isset($resp['usage'][$uk])) $usage[$uk] += intval($resp['usage'][$uk]);
                }
            }
            if (empty($resp['choices'][0]['message'])) {
                throw new \Exception('模型未返回 message');
            }
            $msg = $resp['choices'][0]['message'];
            $full[] = $msg;

            $toolCalls = isset($msg['tool_calls']) ? $msg['tool_calls'] : null;
            if (!$toolCalls || !is_array($toolCalls)) {
                $reply = isset($msg['content']) ? $msg['content'] : '';
                return array(
                    'reply' => $reply !== null && $reply !== '' ? $reply : '（模型未返回文本）',
                    'messages' => $this->stripSystem($full),
                    'tool_trace' => $trace,
                    'usage' => $usage,
                    'finish_reason' => isset($resp['choices'][0]['finish_reason']) ? $resp['choices'][0]['finish_reason'] : null,
                );
            }

            foreach ($toolCalls as $call) {
                $id = isset($call['id']) ? $call['id'] : ('call_' . uniqid());
                $fname = isset($call['function']['name']) ? $call['function']['name'] : '';
                $rawArgs = isset($call['function']['arguments']) ? $call['function']['arguments'] : '{}';
                $args = json_decode($rawArgs, true);
                if (!is_array($args)) $args = array();
                $t0 = microtime(true);
                $result = $this->tools->execute($fname, $args);
                $ms = intval((microtime(true) - $t0) * 1000);
                $item = array(
                    'id' => $id,
                    'name' => $fname,
                    'label' => Store::toolLabel($fname),
                    'arguments' => $args,
                    'result' => $result,
                    'duration_ms' => $ms,
                    'ok' => !(isset($result['ok']) && $result['ok'] === false),
                );
                $trace[] = $item;
                if ($this->onTool) call_user_func($this->onTool, $item);
                $full[] = array(
                    'role' => 'tool',
                    'tool_call_id' => $id,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                );
            }
        }

        return array(
            'reply' => '已达到最大工具调用轮次，请根据已执行结果继续提问。',
            'messages' => $this->stripSystem($full),
            'tool_trace' => $trace,
            'usage' => $usage,
            'finish_reason' => 'max_rounds',
        );
    }

    private function stripSystem(array $messages)
    {
        $out = array();
        foreach ($messages as $m) {
            if (isset($m['role']) && $m['role'] === 'system') continue;
            $out[] = $m;
        }
        return $out;
    }
}
