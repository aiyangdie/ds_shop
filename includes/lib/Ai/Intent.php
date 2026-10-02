<?php
namespace lib\Ai;

/**
 * 客服会话轻量意图分类（规则优先，可扩展模型）
 */
class Intent
{
    /**
     * @return string ask_goods|query_order|aftersale|refund_complaint|chitchat|need_human
     */
    public static function classify($text)
    {
        $t = mb_strtolower(trim(strval($text)));
        if ($t === '') return 'chitchat';

        if (preg_match('/退款|退货|投诉|举报|骗子|诈骗|报警|律师|工商|差评|怒|气死|骗钱|虚假|欺诈/', $t)) {
            return 'refund_complaint';
        }
        if (preg_match('/人工|客服|转人工|真人|打不通|找人/', $t)) {
            return 'need_human';
        }
        if (preg_match('/订单|查单|到货|发货|卡密|没收到|查询|单号|订单号/', $t)) {
            return 'query_order';
        }
        if (preg_match('/售后|换货|不能用|失效|过期|登录不上|账号|密码错|补发|重发/', $t)) {
            return 'aftersale';
        }
        if (preg_match('/多少钱|价格|推荐|有没有|怎么买|下单|商品|套餐|便宜|性价比|介绍/', $t)) {
            return 'ask_goods';
        }
        return 'chitchat';
    }

    /** 是否应先让 AI 回答 */
    public static function shouldAiReply($intent, $ticket)
    {
        if (!$ticket) return false;
        if (intval($ticket['status']) === 2) return false;
        if (!empty($ticket['ai_paused']) || !empty($ticket['staff_joined'])) return false;
        if ($intent === 'refund_complaint' || $intent === 'need_human') return false;
        return in_array($intent, array('ask_goods', 'query_order', 'aftersale', 'chitchat'), true);
    }

    /** 是否应系统提示转人工等待 */
    public static function shouldEscalate($intent)
    {
        return $intent === 'refund_complaint' || $intent === 'need_human';
    }
}
