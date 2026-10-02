<?php
namespace lib\Ai;

/**
 * AI 会话 / 消息 / 操作审计日志存储（按 channel + owner 隔离）
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
            channel VARCHAR(16) NOT NULL DEFAULT 'admin',
            owner_id VARCHAR(64) NOT NULL DEFAULT '0',
            context_json TEXT DEFAULT NULL,
            msg_count INT UNSIGNED NOT NULL DEFAULT 0,
            tool_count INT UNSIGNED NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            addtime DATETIME DEFAULT NULL,
            updatetime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_updatetime (updatetime),
            KEY idx_channel_owner (channel, owner_id)
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

        $this->ensureColumn('pre_ai_session', 'channel', "channel VARCHAR(16) NOT NULL DEFAULT 'admin'");
        $this->ensureColumn('pre_ai_session', 'owner_id', "owner_id VARCHAR(64) NOT NULL DEFAULT '0'");
        $this->ensureColumn('pre_ai_session', 'context_json', 'context_json TEXT DEFAULT NULL');

        $this->DB->exec("CREATE TABLE IF NOT EXISTS pre_ai_cs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL DEFAULT 0,
            channel VARCHAR(16) NOT NULL DEFAULT 'shop',
            contact VARCHAR(120) NOT NULL DEFAULT '',
            order_no VARCHAR(64) NOT NULL DEFAULT '',
            problem VARCHAR(500) NOT NULL DEFAULT '',
            summary MEDIUMTEXT,
            status TINYINT NOT NULL DEFAULT 0,
            reply MEDIUMTEXT,
            operator VARCHAR(64) DEFAULT NULL,
            guest_id VARCHAR(64) DEFAULT NULL,
            zid INT UNSIGNED NOT NULL DEFAULT 0,
            workorder_id INT UNSIGNED NOT NULL DEFAULT 0,
            staff_joined TINYINT NOT NULL DEFAULT 0,
            ai_paused TINYINT NOT NULL DEFAULT 0,
            last_msg_id INT UNSIGNED NOT NULL DEFAULT 0,
            ip VARCHAR(64) DEFAULT NULL,
            addtime DATETIME DEFAULT NULL,
            updatetime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_addtime (addtime),
            KEY idx_guest (guest_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->DB->exec("CREATE TABLE IF NOT EXISTS pre_ai_cs_msg (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cs_id INT UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL DEFAULT 'user',
            msg_type VARCHAR(16) NOT NULL DEFAULT 'text',
            content MEDIUMTEXT,
            media_url VARCHAR(500) DEFAULT NULL,
            media_size INT UNSIGNED NOT NULL DEFAULT 0,
            operator VARCHAR(64) DEFAULT NULL,
            addtime DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_cs (cs_id),
            KEY idx_cs_id (cs_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->ensureColumn('pre_ai_cs', 'staff_joined', 'staff_joined TINYINT NOT NULL DEFAULT 0');
        $this->ensureColumn('pre_ai_cs', 'ai_paused', 'ai_paused TINYINT NOT NULL DEFAULT 0');
        $this->ensureColumn('pre_ai_cs', 'last_msg_id', 'last_msg_id INT UNSIGNED NOT NULL DEFAULT 0');

        $this->ready = true;
        return true;
    }

    private function ensureColumn($table, $column, $ddl)
    {
        try {
            $cols = $this->DB->getAll("SHOW COLUMNS FROM `$table` LIKE '" . addslashes($column) . "'");
            if (!$cols || count($cols) === 0) {
                $this->DB->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
            }
        } catch (\Exception $e) {
            // ignore
        }
    }

    public function createCsTicket(array $row)
    {
        $now = date('Y-m-d H:i:s');
        $this->DB->exec("INSERT INTO pre_ai_cs (session_id,channel,contact,order_no,problem,summary,status,guest_id,zid,workorder_id,ip,addtime,updatetime) VALUES (:sid,:ch,:c,:o,:p,:s,0,:g,:z,:w,:ip,:a,:u)", array(
            ':sid' => intval(isset($row['session_id']) ? $row['session_id'] : 0),
            ':ch' => isset($row['channel']) ? strval($row['channel']) : 'shop',
            ':c' => mb_substr(strval(isset($row['contact']) ? $row['contact'] : ''), 0, 120),
            ':o' => mb_substr(strval(isset($row['order_no']) ? $row['order_no'] : ''), 0, 64),
            ':p' => mb_substr(strval(isset($row['problem']) ? $row['problem'] : ''), 0, 500),
            ':s' => strval(isset($row['summary']) ? $row['summary'] : ''),
            ':g' => mb_substr(strval(isset($row['guest_id']) ? $row['guest_id'] : ''), 0, 64),
            ':z' => intval(isset($row['zid']) ? $row['zid'] : 0),
            ':w' => intval(isset($row['workorder_id']) ? $row['workorder_id'] : 0),
            ':ip' => strval(isset($row['ip']) ? $row['ip'] : ''),
            ':a' => $now,
            ':u' => $now,
        ));
        return intval($this->DB->lastInsertId());
    }

    public function listCsTickets($opts = array())
    {
        $limit = isset($opts['limit']) ? min(100, max(1, intval($opts['limit']))) : 50;
        $where = '1=1';
        if (isset($opts['status']) && $opts['status'] !== '' && $opts['status'] !== null) {
            $where .= ' AND status=' . intval($opts['status']);
        }
        if (!empty($opts['keyword'])) {
            $kw = addslashes($opts['keyword']);
            $where .= " AND (contact LIKE '%$kw%' OR order_no LIKE '%$kw%' OR problem LIKE '%$kw%' OR summary LIKE '%$kw%')";
        }
        return $this->DB->getAll("SELECT id,session_id,channel,contact,order_no,problem,status,operator,zid,workorder_id,ip,addtime,updatetime,reply,staff_joined,ai_paused,last_msg_id,guest_id FROM pre_ai_cs WHERE $where ORDER BY id DESC LIMIT $limit");
    }

    public function getCsTicket($id)
    {
        $id = intval($id);
        return $this->DB->getRow("SELECT * FROM pre_ai_cs WHERE id='$id' LIMIT 1");
    }

    public function replyCsTicket($id, $reply, $operator = 'admin', $close = false)
    {
        $id = intval($id);
        $row = $this->getCsTicket($id);
        if (!$row) return false;
        // 兼容旧接口：写成聊天消息
        $this->addCsMessage($id, 'staff', 'text', $reply, '', 0, $operator);
        $status = $close ? 2 : 1;
        $replySql = addslashes(strval($reply));
        $op = addslashes(mb_substr(strval($operator), 0, 60));
        $ok = $this->DB->exec("UPDATE pre_ai_cs SET reply='$replySql',status='$status',staff_joined=1,ai_paused=1,operator='$op',updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'") !== false;
        if ($ok && !empty($row['session_id'])) {
            $sid = intval($row['session_id']);
            $msg = '人工客服回复（工单 #' . $id . '）：' . mb_substr(strval($reply), 0, 800);
            $this->addMessage($sid, 'assistant', $msg, null, null);
            $this->touchSession($sid, array('inc_msg' => 1));
        }
        return $ok;
    }

    /** 打开或复用未完结会话 */
    public function openCsThread(array $row)
    {
        $guestId = mb_substr(strval(isset($row['guest_id']) ? $row['guest_id'] : ''), 0, 64);
        if ($guestId !== '') {
            $g = addslashes($guestId);
            $exist = $this->DB->getRow("SELECT * FROM pre_ai_cs WHERE guest_id='$g' AND status<2 ORDER BY id DESC LIMIT 1");
            if ($exist) {
                $this->migrateLegacyCsMessages($exist);
                if (!empty($row['contact']) && empty($exist['contact'])) {
                    $c = addslashes(mb_substr(strval($row['contact']), 0, 120));
                    $this->DB->exec("UPDATE pre_ai_cs SET contact='$c',updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='" . intval($exist['id']) . "'");
                    $exist['contact'] = $row['contact'];
                }
                return $exist;
            }
        }
        $id = $this->createCsTicket($row);
        $ticket = $this->getCsTicket($id);
        if ($ticket && !empty($row['problem'])) {
            $this->addCsMessage($id, 'user', 'text', strval($row['problem']), '', 0, '');
            $this->addCsMessage($id, 'system', 'text', '已接入在线客服，可直接发文字或图片。商品问题可由助手先答，复杂售后请等待人工。', '', 0, 'system');
        }
        return $ticket;
    }

    public function migrateLegacyCsMessages($ticket)
    {
        if (!$ticket || empty($ticket['id'])) return;
        $csId = intval($ticket['id']);
        $cnt = intval($this->DB->getColumn("SELECT count(*) FROM pre_ai_cs_msg WHERE cs_id='$csId'"));
        if ($cnt > 0) return;
        if (!empty($ticket['problem'])) {
            $this->addCsMessage($csId, 'user', 'text', strval($ticket['problem']), '', 0, '', isset($ticket['addtime']) ? $ticket['addtime'] : null);
        }
        if (!empty($ticket['reply'])) {
            $this->addCsMessage($csId, 'staff', 'text', strval($ticket['reply']), '', 0, isset($ticket['operator']) ? $ticket['operator'] : 'admin', isset($ticket['updatetime']) ? $ticket['updatetime'] : null);
        }
        if ($cnt === 0 && empty($ticket['problem']) && empty($ticket['reply'])) {
            $this->addCsMessage($csId, 'system', 'text', '会话已创建，请直接发消息。', '', 0, 'system');
        }
    }

    public function addCsMessage($csId, $role, $msgType, $content, $mediaUrl = '', $mediaSize = 0, $operator = '', $addtime = null)
    {
        $csId = intval($csId);
        $role = in_array($role, array('user', 'staff', 'ai', 'system'), true) ? $role : 'user';
        $msgType = in_array($msgType, array('text', 'image', 'system'), true) ? $msgType : 'text';
        $now = $addtime ? $addtime : date('Y-m-d H:i:s');
        $this->DB->exec("INSERT INTO pre_ai_cs_msg (cs_id,role,msg_type,content,media_url,media_size,operator,addtime) VALUES (:c,:r,:t,:ct,:u,:sz,:op,:a)", array(
            ':c' => $csId,
            ':r' => $role,
            ':t' => $msgType,
            ':ct' => strval($content),
            ':u' => mb_substr(strval($mediaUrl), 0, 500),
            ':sz' => intval($mediaSize),
            ':op' => mb_substr(strval($operator), 0, 60),
            ':a' => $now,
        ));
        $mid = intval($this->DB->lastInsertId());
        $sets = "last_msg_id='$mid',updatetime='" . date('Y-m-d H:i:s') . "'";
        if ($role === 'staff') {
            $sets .= ',staff_joined=1,ai_paused=1,status=1,operator=\'' . addslashes(mb_substr(strval($operator), 0, 60)) . '\'';
        } elseif ($role === 'user') {
            // 用户新消息且未完结 → 待处理
            $sets .= ',status=IF(status=2,2,0)';
        }
        $this->DB->exec("UPDATE pre_ai_cs SET $sets WHERE id='$csId'");
        return $mid;
    }

    public function listCsMessages($csId, $afterId = 0, $limit = 100)
    {
        $csId = intval($csId);
        $afterId = intval($afterId);
        $limit = min(200, max(1, intval($limit)));
        $where = "cs_id='$csId'";
        if ($afterId > 0) $where .= " AND id>'$afterId'";
        return $this->DB->getAll("SELECT id,cs_id,role,msg_type,content,media_url,media_size,operator,addtime FROM pre_ai_cs_msg WHERE $where ORDER BY id ASC LIMIT $limit");
    }

    public function getCsForGuest($csId, $guestId)
    {
        $csId = intval($csId);
        $row = $this->getCsTicket($csId);
        if (!$row) return null;
        if (strval($row['guest_id']) !== strval($guestId)) return null;
        return $row;
    }

    public function setCsAiPaused($csId, $paused = true)
    {
        $csId = intval($csId);
        $v = $paused ? 1 : 0;
        return $this->DB->exec("UPDATE pre_ai_cs SET ai_paused='$v',updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$csId'") !== false;
    }

    public function closeCsTicket($csId, $operator = 'admin')
    {
        $csId = intval($csId);
        $op = addslashes(mb_substr(strval($operator), 0, 60));
        $this->addCsMessage($csId, 'system', 'text', '会话已完结，如需继续请重新转人工。', '', 0, $op);
        return $this->DB->exec("UPDATE pre_ai_cs SET status=2,operator='$op',updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$csId'") !== false;
    }

    public function createSession($title, $model = '', $channel = 'admin', $ownerId = '0', $context = null)
    {
        $now = date('Y-m-d H:i:s');
        $title = mb_substr(trim($title) !== '' ? trim($title) : '新对话', 0, 100);
        $channel = $this->normalizeChannel($channel);
        $ownerId = mb_substr(strval($ownerId), 0, 64);
        $ctx = $context === null ? null : json_encode($context, JSON_UNESCAPED_UNICODE);
        $this->DB->exec("INSERT INTO pre_ai_session (title, model, channel, owner_id, context_json, msg_count, tool_count, status, addtime, updatetime) VALUES (:t,:m,:ch,:oid,:ctx,0,0,1,:a,:u)", array(
            ':t' => $title,
            ':m' => $model,
            ':ch' => $channel,
            ':oid' => $ownerId,
            ':ctx' => $ctx,
            ':a' => $now,
            ':u' => $now,
        ));
        return intval($this->DB->lastInsertId());
    }

    public function listSessions($limit = 40, $channel = null, $ownerId = null)
    {
        $limit = max(1, min(100, intval($limit)));
        $where = 'status=1';
        if ($channel !== null) {
            $where .= " AND channel='" . addslashes($this->normalizeChannel($channel)) . "'";
        }
        if ($ownerId !== null) {
            $where .= " AND owner_id='" . addslashes(strval($ownerId)) . "'";
        }
        return $this->DB->getAll("SELECT id,title,model,channel,owner_id,msg_count,tool_count,status,addtime,updatetime FROM pre_ai_session WHERE $where ORDER BY updatetime DESC LIMIT $limit");
    }

    public function getSession($id, $channel = null, $ownerId = null)
    {
        $id = intval($id);
        $row = $this->DB->getRow("SELECT * FROM pre_ai_session WHERE id='$id' LIMIT 1");
        if (!$row) return null;
        if ($channel !== null && isset($row['channel']) && $row['channel'] !== $this->normalizeChannel($channel)) {
            return null;
        }
        if ($ownerId !== null && isset($row['owner_id']) && strval($row['owner_id']) !== strval($ownerId)) {
            return null;
        }
        return $row;
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
        if (isset($extra['context'])) {
            $sets[] = "context_json='" . addslashes(json_encode($extra['context'], JSON_UNESCAPED_UNICODE)) . "'";
        }
        if (isset($extra['inc_msg'])) {
            $sets[] = "msg_count=msg_count+" . intval($extra['inc_msg']);
        }
        if (isset($extra['inc_tool'])) {
            $sets[] = "tool_count=tool_count+" . intval($extra['inc_tool']);
        }
        return $this->DB->exec("UPDATE pre_ai_session SET " . implode(',', $sets) . " WHERE id='$id'");
    }

    public function renameSession($id, $title, $channel = null, $ownerId = null)
    {
        $id = intval($id);
        $title = mb_substr(trim($title), 0, 100);
        if ($title === '') return false;
        $session = $this->getSession($id, $channel, $ownerId);
        if (!$session) return false;
        return $this->DB->exec("UPDATE pre_ai_session SET title='" . addslashes($title) . "', updatetime='" . date('Y-m-d H:i:s') . "' WHERE id='$id'");
    }

    public function deleteSession($id, $channel = null, $ownerId = null)
    {
        $id = intval($id);
        $session = $this->getSession($id, $channel, $ownerId);
        if (!$session) return false;
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
        $this->DB->exec("DELETE FROM pre_ai_message WHERE session_id='$sessionId' ORDER BY id ASC LIMIT $cut");
        return $cut;
    }

    public function pruneSessions($keep = 40, $channel = null, $ownerId = null)
    {
        $keep = max(10, min(100, intval($keep)));
        $where = 'status=1';
        if ($channel !== null) {
            $where .= " AND channel='" . addslashes($this->normalizeChannel($channel)) . "'";
        }
        if ($ownerId !== null) {
            $where .= " AND owner_id='" . addslashes(strval($ownerId)) . "'";
        }
        $ids = $this->DB->getAll("SELECT id FROM pre_ai_session WHERE $where ORDER BY updatetime DESC");
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

    private function normalizeChannel($channel)
    {
        $channel = strtolower(trim(strval($channel)));
        if (!in_array($channel, array('admin', 'user', 'shop'), true)) {
            return 'admin';
        }
        return $channel;
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
            'apply_price_rule' => '应用加价模板',
            'batch_set_stock' => '批量设库存',
            'delete_goods' => '删除商品',
            'copy_goods' => '复制商品',
            'move_goods' => '移动商品',
            'list_classes' => '分类列表',
            'save_class' => '保存分类',
            'delete_class' => '删除分类',
            'list_sites' => '分站列表',
            'set_site' => '设置分站',
            'site_recharge' => '分站余额',
            'extend_site' => '分站续期',
            'list_money_records' => '余额流水',
            'list_invite_shops' => '推广商品',
            'save_invite_shop' => '保存推广商品',
            'delete_invite_shop' => '删除推广商品',
            'list_invite_logs' => '推广记录',
            'get_config' => '读取配置',
            'update_config' => '更新配置',
            'list_shequ' => '对接站点',
            'supplier_pull_goods' => '拉取货源商品',
            'list_pay_orders' => '支付订单',
            'list_workorders' => '工单列表',
            'get_workorder' => '工单详情',
            'reply_workorder' => '回复工单',
            'set_workorder_status' => '处理工单',
            'delete_workorder' => '删除工单',
            'change_shopname' => '批量改商品名',
            'change_inputs' => '批量改输入框',
            'reset_goods_sort' => '重置商品排序',
            'set_site_price' => '分站单独加价',
            'clear_site_price' => '清空分站加价',
            'delete_site' => '删除分站',
            'list_users' => '普通用户列表',
            'list_invite_records' => '推广链接记录',
            'delete_invite_record' => '删除推广记录',
            'list_dock_logs' => '对接日志',
            'list_rank' => '分站排行',
            'create_fanghong_url' => '生成防红短链',
            'export_orders' => '导出订单',
            'delete_shequ' => '删除对接站',
            'batch_sync_dock_goods' => '批量同步货源商品',
            'list_faka' => '发卡库存',
            'add_faka' => '导入发卡卡密',
            'delete_faka' => '删除发卡卡密',
            'list_kms' => '卡密列表',
            'generate_kms' => '生成卡密',
            'delete_kms' => '删除卡密',
            'list_articles' => '文章列表',
            'get_article' => '文章详情',
            'save_article' => '保存文章',
            'delete_article' => '删除文章',
            'list_tixian' => '提现列表',
            'set_tixian_status' => '处理提现',
            'list_messages' => '站内通知',
            'save_message' => '保存站内通知',
            'delete_message' => '删除站内通知',
            'save_shequ' => '保存对接站点',
            'list_price_rules' => '加价模板',
            'save_price_rule' => '保存加价模板',
            'delete_price_rule' => '删除加价模板',
            'list_gifts' => '抽奖奖品',
            'save_gift' => '保存抽奖奖品',
            'delete_gift' => '删除抽奖奖品',
            'list_templates' => '前台模板',
            'setup_site' => '设置站点品牌',
            'config_catalog' => '设置能力说明',
            'clean_data' => '系统数据清理',
            'capability_catalog' => '能力清单',
            'shop_list_classes' => '前台分类',
            'shop_search_goods' => '搜索商品',
            'shop_get_goods' => '商品公开信息',
            'shop_recommend_goods' => '推荐商品',
            'shop_explain_goods' => '解释商品',
            'shop_site_faq' => '站点说明',
            'shop_query_order' => '公开查单',
            'shop_aftersale_help' => '售后指导',
            'shop_transfer_human' => '转人工客服',
            'list_ai_cs' => 'AI客服工单',
            'get_ai_cs' => '客服工单详情',
            'reply_ai_cs' => '回复客服工单',
            'user_my_balance' => '我的余额',
            'user_my_orders' => '我的订单',
            'user_get_order' => '我的订单详情',
            'user_my_workorders' => '我的工单',
            'user_get_workorder' => '工单详情',
            'user_create_workorder' => '提交工单',
            'user_reply_workorder' => '回复工单',
            'user_site_info' => '站点信息',
            'user_update_site' => '更新站点信息',
            'user_list_goods' => '浏览商品',
            'user_list_classes' => '浏览分类',
        );
        return isset($map[$name]) ? $map[$name] : $name;
    }
}
