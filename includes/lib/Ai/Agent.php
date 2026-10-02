<?php
namespace lib\Ai;

/**
 * 多轮工具调用 Agent：让模型直接操作商城，并支持详细审计回调
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

    public function __construct(Client $client, Tools $tools, array $opts = array())
    {
        $this->client = $client;
        $this->tools = $tools;
        $this->model = isset($opts['model']) ? $opts['model'] : 'gpt-4o-mini';
        $this->temperature = isset($opts['temperature']) ? floatval($opts['temperature']) : 0.2;
        $this->maxTokens = isset($opts['max_tokens']) ? intval($opts['max_tokens']) : 4096;
        $this->maxRounds = isset($opts['max_rounds']) ? intval($opts['max_rounds']) : 8;
        $this->onTool = isset($opts['on_tool']) && is_callable($opts['on_tool']) ? $opts['on_tool'] : null;
        $this->systemPrompt = isset($opts['system_prompt']) && $opts['system_prompt'] !== ''
            ? $opts['system_prompt']
            : $this->defaultSystemPrompt();
    }

    private function defaultSystemPrompt()
    {
        return "你是彩虹自助下单商城的运营助手，可以直接调用工具操作系统，用户不必再进后台或分站页面。\n"
            . "所有工具调用都会被系统完整记录日志，请谨慎执行写操作。\n\n"
            . "能力范围：经营概况；订单搜索/详情/改状态/批量处理/退款/重新对接；商品与分类；分站与余额；站点配置；对接货源；支付订单；工单；发卡与卡密；文章；提现；站内通知；加价模板。不确定有哪些工具时可先调用 capability_catalog。\n\n"
            . "回复规范：\n"
            . "1. 只用简洁通顺的中文，不要用 Markdown，不要出现星号、井号、反引号。\n"
            . "2. 不要用表情符号。\n"
            . "3. 分点用 1. 2. 3.。\n"
            . "4. 订单状态说中文：未处理、已完成、处理中、异常、已退款。\n"
            . "5. 先查再改。退款、充值、扣款、批量改状态、删除、重新对接、处理提现，必须用户明确确认，工具参数 confirm=true。\n"
            . "6. 不编造数据；以工具返回为准。\n"
            . "7. 结果用一两段话说明，关键数字即可。";
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

        for ($round = 0; $round < $this->maxRounds; $round++) {
            $payload = array(
                'model' => $this->model,
                'messages' => $full,
                'tools' => $toolDefs,
                'tool_choice' => 'auto',
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            );
            $resp = $this->client->chat($payload);
            if (isset($resp['usage']) && is_array($resp['usage'])) {
                foreach (array('prompt_tokens', 'completion_tokens', 'total_tokens') as $uk) {
                    if (isset($resp['usage'][$uk])) {
                        $usage[$uk] += intval($resp['usage'][$uk]);
                    }
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
                if ($this->onTool) {
                    call_user_func($this->onTool, $item);
                }
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
