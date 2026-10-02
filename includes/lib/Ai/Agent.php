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
        return "你是本站「彩虹自助下单」商城的运营 AI 助手。你可以调用工具直接操作系统，无需用户去后台或分站页面操作。\n"
            . "原则：\n"
            . "1. 先查再改；不确定时用工具确认数据。\n"
            . "2. 退款、余额充值/扣款等资金操作必须得到用户明确意图，并在工具里传 confirm=true。\n"
            . "3. 用简洁中文回复，说明你做了什么、结果如何。\n"
            . "4. 不要编造订单号、商品ID或金额；以工具返回为准。\n"
            . "5. 订单状态：0未处理 1已完成 2处理中 3异常 4已退款。";
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
