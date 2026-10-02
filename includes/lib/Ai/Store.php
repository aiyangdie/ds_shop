<?php
namespace lib\Ai;

/**
 * AI 会话 / 消息 / 操作审计日志存储
 */
class Store
{
    /** @var \lib\PdoHelper */
    private $DB;
    private $ready = false;

    public function __construct($DB)
    {
        $this->DB = $DB;
    }

    public function ensureSchema()
    {
        if ($this->ready) return true;
        $this->DB->exec("CREATE TABLE IF NOT EXISTS pre_ai_session (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(120) NOT NULL DEFAULT '新对话',
            model VARCHAR(64) DEFAULT NULL,
            msg_count INT UNSIGNED NOT NULL DEFAULT 0,
            tool_count INT UNSIGNED NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            addtime DATETIME DEFAULT NULL,
            updatetime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_updatetime (updatetime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->DB->exec("CREATE TABLE IF NOT EXISTS pre_ai_message (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL,
            content MEDIUMTEXT,
            tool_trace MEDIUMTEXT,
            usage_json VARCHAR(255) DEFAULT NULL,
            addtime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_session (session_id),
            KEY idx_addtime (addtime)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->DB->exec("CREATE TABLE IF NOT EXISTS pre_ai_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL DEFAULT 0,
            message_id INT UNSIGNED NOT NULL DEFAULT 0,
            tool_name VARCHAR(64) NOT NULL,
            tool_label VARCHAR(64) DEFAULT NULL,
            arguments MEDIUMTEXT,
            result MEDIUMTEXT,
            ok TINYINT NOT NULL DEFAULT 0,
            error_msg VARCHAR(500) DEFAULT NULL,
            operator VARCHAR(64) DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            model VARCHAR(64) DEFAULT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            request_id VARCHAR(64) DEFAULT NULL,
            addtime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_session (session_id),
            KEY idx_tool (tool_name),
            KEY idx_addtime (addtime),
            KEY idx_ok (ok)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->ready = true;
        return true;
    }

    public function createSession($title, $model = '')
    {
        $now = date('Y-m-d H:i:s');
        $title = mb_substr(trim($title) !== '' ? trim($title) : '新对话', 0, 100);
        $this->DB->exec("INSERT INTO pre_ai_session (title, model, msg_count, tool_count, status, addtime, updatetime) VALUES (:t,:m,0,0,1,:a,:u)", array(
            ':t' => $title,
            ':m' => $model,
            ':a' => $now,
            ':u' => $now,
        ));
        return intval($this->DB->lastInsertId());
    }

    public function listSessions($limit = 40)
    {
        $limit = max(1, min(100, intval($limit)));
        return $this->DB->getAll("SELECT id,title,model,msg_count,tool_count,status,addtime,updatetime FROM pre_ai_session WHERE status=1 ORDER BY updatetime DESC LIMIT $limit");
    }

    public function getSession($id)
    {
        $id = intval($id);
        return $this->DB->getRow("SELECT * FROM pre_ai_session WHERE id='$id' LIMIT 1");
    }

    public function touchSession($id, $extra = array())
    {
        $id = intval($id);
        $sets = array("updatetime='" . date('Y-m-d H:i:s') . "'");
        if (isset($extra['title'])) {
            $sets[] = "title='" . addslashes(mb_substr($extra['title'], 0, 100)) . "'";
        }
        if (isset($extra['model'])) {
            $sets[] = "model='" . addslashes($extra['model']) . "'";
        }
        if (isset($extra['inc_msg'])) {
            $sets[] = "msg_count=msg_count+" . intval($extra['inc_msg']);
        }
        if (isset($extra['inc_tool'])) {
            $sets[] = "tool_count=tool_count+" . intval($extra['inc_tool']);
        }
        return $this->DB->exec("UPDATE pre_ai_session SET " . implode(',', $sets) . " WHERE id='$id'");
    }

    public function renameSession($id, $title)
    {
        $id = intval($id);
        $title = mb_substr(trim($title), 0, 100);
        if ($title === '') return false;
        return $this->DB->exec("UPDATE pre_ai_session SET title='" . addslashes($title) . "', updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
    }

    public function deleteSession($id)
    {
        $id = intval($id);
        // 软删会话，日志保留
        $this->DB->exec("UPDATE pre_ai_session SET status=0, updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
        return true;
    }

    public function addMessage($sessionId, $role, $content, $toolTrace = null, $usage = null)
    {
        $now = date('Y-m-d H:i:s');
        $trace = $toolTrace === null ? null : json_encode($toolTrace, JSON_UNESCAPED_UNICODE);
        $usageJson = $usage === null ? null : json_encode($usage, JSON_UNESCAPED_UNICODE);
        $this->DB->exec("INSERT INTO pre_ai_message (session_id, role, content, tool_trace, usage_json, addtime) VALUES (:sid,:role,:c,:t,:u,:a)", array(
            ':sid' => intval($sessionId),
            ':role' => $role,
            ':c' => strval($content),
            ':t' => $trace,
            ':u' => $usageJson,
            ':a' => $now,
        ));
        return intval($this->DB->lastInsertId());
    }

    public function listMessages($sessionId, $limit = 80)
    {
        $sessionId = intval($sessionId);
        $limit = max(1, min(200, intval($limit)));
        $rows = $this->DB->getAll("SELECT id,session_id,role,content,tool_trace,usage_json,addtime FROM pre_ai_message WHERE session_id='$sessionId' ORDER BY id ASC LIMIT $limit");
        foreach ($rows as &$r) {
            if (!empty($r['tool_trace'])) {
                $decoded = json_decode($r['tool_trace'], true);
                $r['tool_trace'] = is_array($decoded) ? $decoded : array();
            } else {
                $r['tool_trace'] = array();
            }
            if (!empty($r['usage_json'])) {
                $r['usage'] = json_decode($r['usage_json'], true);
            }
            unset($r['usage_json']);
        }
        return $rows;
    }

    public function historyForModel($sessionId, $limit = 20)
    {
        $sessionId = intval($sessionId);
        $limit = max(2, min(40, intval($limit)));
        // 取最近 limit 条 user/assistant
        $rows = $this->DB->getAll("SELECT role,content FROM pre_ai_message WHERE session_id='$sessionId' AND role IN ('user','assistant') ORDER BY id DESC LIMIT $limit");
        $rows = array_reverse($rows);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array('role' => $r['role'], 'content' => strval($r['content']));
        }
        return $out;
    }

    public function pruneMessages($sessionId, $keep = 80)
    {
        $sessionId = intval($sessionId);
        $keep = max(20, min(200, intval($keep)));
        $cnt = intval($this->DB->getColumn("SELECT COUNT(*) FROM pre_ai_message WHERE session_id='$sessionId'"));
        if ($cnt <= $keep) return 0;
        $cut = $cnt - $keep;
        // 只删最旧消息，不删日志
        $this->DB->exec("DELETE FROM pre_ai_message WHERE session_id='$sessionId' ORDER BY id ASC LIMIT $cut");
        return $cut;
    }

    public function pruneSessions($keep = 40)
    {
        $keep = max(10, min(100, intval($keep)));
        $ids = $this->DB->getAll("SELECT id FROM pre_ai_session WHERE status=1 ORDER BY updatetime DESC");
        if (count($ids) <= $keep) return 0;
        $n = 0;
        foreach ($ids as $i => $row) {
            if ($i < $keep) continue;
            $this->deleteSession(intval($row['id']));
            $n++;
        }
        return $n;
    }

    public function addLog(array $row)
    {
        $now = date('Y-m-d H:i:s');
        $ok = !empty($row['ok']) ? 1 : 0;
        $args = isset($row['arguments']) ? json_encode($row['arguments'], JSON_UNESCAPED_UNICODE) : '{}';
        $result = isset($row['result']) ? json_encode($row['result'], JSON_UNESCAPED_UNICODE) : '{}';
        // 截断超大结果，避免撑爆库；完整也可看文件备份
        if (strlen($result) > 60000) {
            $result = substr($result, 0, 60000) . '...(truncated)';
        }
        if (strlen($args) > 20000) {
            $args = substr($args, 0, 20000) . '...(truncated)';
        }
        $err = '';
        if (!$ok && isset($row['result']['error'])) $err = mb_substr(strval($row['result']['error']), 0, 480);
        elseif (!$ok && isset($row['result']['msg'])) $err = mb_substr(strval($row['result']['msg']), 0, 480);

        $this->DB->exec("INSERT INTO pre_ai_log (session_id,message_id,tool_name,tool_label,arguments,result,ok,error_msg,operator,ip,model,duration_ms,request_id,addtime) VALUES (:sid,:mid,:tn,:tl,:a,:r,:ok,:e,:op,:ip,:m,:d,:rid,:t)", array(
            ':sid' => intval(isset($row['session_id']) ? $row['session_id'] : 0),
            ':mid' => intval(isset($row['message_id']) ? $row['message_id'] : 0),
            ':tn' => strval(isset($row['tool_name']) ? $row['tool_name'] : ''),
            ':tl' => strval(isset($row['tool_label']) ? $row['tool_label'] : ''),
            ':a' => $args,
            ':r' => $result,
            ':ok' => $ok,
            ':e' => $err,
            ':op' => strval(isset($row['operator']) ? $row['operator'] : 'admin'),
            ':ip' => strval(isset($row['ip']) ? $row['ip'] : ''),
            ':m' => strval(isset($row['model']) ? $row['model'] : ''),
            ':d' => intval(isset($row['duration_ms']) ? $row['duration_ms'] : 0),
            ':rid' => strval(isset($row['request_id']) ? $row['request_id'] : ''),
            ':t' => $now,
        ));
        return intval($this->DB->lastInsertId());
    }

    public function listLogs($opts = array())
    {
        $limit = isset($opts['limit']) ? min(100, max(1, intval($opts['limit']))) : 50;
        $where = '1=1';
        if (!empty($opts['session_id'])) $where .= ' AND session_id=' . intval($opts['session_id']);
        if (!empty($opts['tool_name'])) $where .= " AND tool_name='" . addslashes($opts['tool_name']) . "'";
        if (isset($opts['ok']) && $opts['ok'] !== '' && $opts['ok'] !== null) $where .= ' AND ok=' . intval($opts['ok']);
        if (!empty($opts['keyword'])) {
            $kw = addslashes($opts['keyword']);
            $where .= " AND (arguments LIKE '%$kw%' OR result LIKE '%$kw%' OR error_msg LIKE '%$kw%' OR tool_label LIKE '%$kw%')";
        }
        return $this->DB->getAll("SELECT id,session_id,message_id,tool_name,tool_label,arguments,result,ok,error_msg,operator,ip,model,duration_ms,request_id,addtime FROM pre_ai_log WHERE $where ORDER BY id DESC LIMIT $limit");
    }

    public function getLog($id)
    {
        $id = intval($id);
        $row = $this->DB->getRow("SELECT * FROM pre_ai_log WHERE id='$id' LIMIT 1");
        if ($row) {
            $row['arguments_parsed'] = json_decode($row['arguments'], true);
            $row['result_parsed'] = json_decode($row['result'], true);
        }
        return $row;
    }

    public static function toolLabel($name)
    {
        $map = array(
            'dashboard_stats' => '经营概况',
            'search_orders' => '搜索订单',
            'get_order' => '订单详情',
            'set_order_status' => '修改订单状态',
            'batch_set_order_status' => '批量改订单状态',
            'refund_order' => '订单退款',
            'redo_dock_order' => '重新对接',
            'list_goods' => '商品列表',
            'get_goods' => '商品详情',
            'update_goods' => '更新商品',
            'create_goods' => '新建商品',
            'set_goods_shelf' => '商品上下架',
            'list_classes' => '分类列表',
            'save_class' => '保存分类',
            'list_sites' => '分站列表',
            'set_site' => '更新分站',
            'site_recharge' => '分站余额',
            'get_config' => '读取配置',
            'update_config' => '更新配置',
            'list_shequ' => '对接站点',
            'supplier_pull_goods' => '拉取货源商品',
            'list_pay_orders' => '支付订单',
            'list_workorders' => '工单列表',
            'set_workorder_status' => '处理工单',
            'list_faka' => '发卡库存',
            'list_kms' => '卡密列表',
            'list_articles' => '文章列表',
            'list_tixian' => '提现列表',
            'set_tixian_status' => '处理提现',
            'list_messages' => '站内通知',
            'save_shequ' => '保存对接站点',
            'list_price_rules' => '加价模板',
            'capability_catalog' => '能力清单',
        );
        return isset($map[$name]) ? $map[$name] : $name;
    }
}
