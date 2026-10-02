<?php
namespace lib\Ai;

/**
 * 多轮工具调用 Agent：让模型直接操作商城，而不是只聊天
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

    public function __construct(Client $client, Tools $tools, array $opts = array())
    {
        $this->client = $client;
        $this->tools = $tools;
        $this->model = isset($opts['model']) ? $opts['model'] : 'gpt-4o-mini';
        $this->temperature = isset($opts['temperature']) ? floatval($opts['temperature']) : 0.2;
        $this->maxTokens = isset($opts['max_tokens']) ? intval($opts['max_tokens']) : 4096;
        $this->maxRounds = isset($opts['max_rounds']) ? intval($opts['max_rounds']) : 8;
        $this->systemPrompt = isset($opts['system_prompt']) && $opts['system_prompt'] !== ''
            ? $opts['system_prompt']
            : $this->defaultSystemPrompt();
    }

    private function defaultSystemPrompt()
    {
        return "你是彩虹自助下单商城的运营助手，可以直接调用工具操作系统，用户不必再进后台或分站页面。\n\n"
            . "你能做的事：\n"
            . "经营查看：今日昨日概况、搜索订单、看订单详情。\n"
            . "订单处理：改状态（未处理/已完成/处理中/异常/已退款/删除）、未处理或异常订单退款、必要时重新对接下单。\n"
            . "商品与分类：搜索改价上下架、新建商品、管理分类。\n"
            . "分站与配置：查看分站、启停、余额调整、读写站点常用配置、查看对接货源并拉品。\n\n"
            . "回复规范（很重要）：\n"
            . "1. 只用简洁通顺的中文，不要用 Markdown，不要出现星号、井号、反引号、横线标题。\n"
            . "2. 不要用表情符号或装饰性图标。\n"
            . "3. 分点时用「1. 2. 3.」或另起一行，不要用星号列表。\n"
            . "4. 订单状态请说中文：未处理、已完成、处理中、异常、已退款；不要只报数字。\n"
            . "5. 先查再改。退款、充值、扣款、批量改状态、删除订单，必须用户明确确认后才执行，工具参数 confirm 设为 true。\n"
            . "6. 不编造订单号、商品ID、金额；一切以工具返回为准。\n"
            . "7. 做完后用一两段话说明结果，必要时附关键数字，避免大段堆砌。";
    }

    /**
     * @param array $messages OpenAI messages（可含历史），不含 system
     * @return array {reply, messages, tool_trace, usage}
     */
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
                $result = $this->tools->execute($fname, $args);
                $trace[] = array(
                    'id' => $id,
                    'name' => $fname,
                    'arguments' => $args,
                    'result' => $result,
                );
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
