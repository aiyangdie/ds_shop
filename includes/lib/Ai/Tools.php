<?php
namespace lib\Ai;

/**
 * AI 可调用的系统工具：直接操作商城数据，无需进后台页面点选
 */
class Tools
{
    /** @var \lib\PdoHelper */
    private $DB;
    /** @var array */
    private $conf;
    /** @var \lib\Cache */
    private $CACHE;

    public function __construct($DB, $conf, $CACHE)
    {
        $this->DB = $DB;
        $this->conf = $conf;
        $this->CACHE = $CACHE;
    }

    /**
     * OpenAI tools schema
     * @return array
     */
    public function definitions()
    {
        return array(
            $this->fn('dashboard_stats', '查看今日/昨日经营概况：订单数、支付额、分站数、待处理工单等', array()),
            $this->fn('search_orders', '按条件搜索订单列表', array(
                'keyword' => array('type' => 'string', 'description' => '下单账号/备注/订单号关键词，可空'),
                'status' => array('type' => 'integer', 'description' => '订单状态：0未处理1已完成2处理中3异常4已退款，不传则全部'),
                'tid' => array('type' => 'integer', 'description' => '商品ID，可空'),
                'limit' => array('type' => 'integer', 'description' => '返回条数，默认20，最大50'),
            )),
            $this->fn('get_order', '查看单个订单详情', array(
                'id' => array('type' => 'integer', 'description' => '订单ID'),
            ), array('id')),
            $this->fn('set_order_status', '修改单个订单状态。status可用数字或中文：0未处理 1已完成 2处理中 3异常 4已退款 5删除', array(
                'id' => array('type' => 'integer', 'description' => '订单ID'),
                'status' => array('type' => 'string', 'description' => '目标状态，数字或中文均可'),
                'result' => array('type' => 'string', 'description' => '处理结果备注，可选'),
            ), array('id', 'status')),
            $this->fn('batch_set_order_status', '批量修改订单状态，用于处理一批未处理/异常单', array(
                'ids' => array(
                    'type' => 'array',
                    'description' => '订单ID数组',
                    'items' => array('type' => 'integer'),
                ),
                'status' => array('type' => 'string', 'description' => '目标状态，数字或中文'),
                'result' => array('type' => 'string', 'description' => '统一备注，可选'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true 才执行'),
            ), array('ids', 'status', 'confirm')),
            $this->fn('refund_order', '对未处理或异常订单退款到用户余额（游客订单改状态后仍需人工打款）', array(
                'id' => array('type' => 'integer', 'description' => '订单ID'),
                'money' => array('type' => 'number', 'description' => '退款金额，不传则全额'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true 才执行退款'),
            ), array('id', 'confirm')),
            $this->fn('redo_dock_order', '对对接类订单重新提交到货源（重新下单）', array(
                'id' => array('type' => 'integer', 'description' => '订单ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true'),
            ), array('id', 'confirm')),
            $this->fn('list_goods', '搜索/列出商品', array(
                'keyword' => array('type' => 'string', 'description' => '商品名关键词，可空'),
                'cid' => array('type' => 'integer', 'description' => '分类ID，可空'),
                'active' => array('type' => 'integer', 'description' => '1上架0下架，不传全部'),
                'limit' => array('type' => 'integer', 'description' => '默认30，最大80'),
            )),
            $this->fn('get_goods', '获取商品完整信息', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
            ), array('tid')),
            $this->fn('update_goods', '更新商品字段（仅传需要改的字段）', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
                'name' => array('type' => 'string', 'description' => '商品名称'),
                'price' => array('type' => 'number', 'description' => '成本/售价字段 price'),
                'cost' => array('type' => 'number', 'description' => '普及价'),
                'cost2' => array('type' => 'number', 'description' => '专业价'),
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'desc' => array('type' => 'string', 'description' => '商品说明'),
                'alert' => array('type' => 'string', 'description' => '下单提示'),
                'input' => array('type' => 'string', 'description' => '主输入框名称'),
                'inputs' => array('type' => 'string', 'description' => '附加输入，|分隔'),
                'stock' => array('type' => 'integer', 'description' => '库存，-1不限'),
                'active' => array('type' => 'integer', 'description' => '1上架0下架'),
                'close' => array('type' => 'integer', 'description' => '1关闭购买0开启'),
                'goods_id' => array('type' => 'string', 'description' => '对接商品ID'),
                'shequ' => array('type' => 'integer', 'description' => '对接站点ID'),
                'is_curl' => array('type' => 'integer', 'description' => '对接方式，2=同系统API'),
            ), array('tid')),
            $this->fn('create_goods', '快速创建一个本地/对接商品', array(
                'name' => array('type' => 'string', 'description' => '商品名称'),
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'price' => array('type' => 'number', 'description' => '价格'),
                'desc' => array('type' => 'string', 'description' => '说明'),
                'input' => array('type' => 'string', 'description' => '主输入名，默认下单账号'),
                'inputs' => array('type' => 'string', 'description' => '附加输入|分隔'),
                'is_curl' => array('type' => 'integer', 'description' => '0本地1访问URL2同系统对接'),
                'shequ' => array('type' => 'integer', 'description' => '对接站点ID'),
                'goods_id' => array('type' => 'string', 'description' => '货源商品ID'),
                'active' => array('type' => 'integer', 'description' => '默认1上架'),
            ), array('name', 'cid', 'price')),
            $this->fn('set_goods_shelf', '批量上下架/开关商品', array(
                'tids' => array(
                    'type' => 'array',
                    'description' => '商品ID数组',
                    'items' => array('type' => 'integer'),
                ),
                'active' => array('type' => 'integer', 'description' => '1上架0下架，与close二选一'),
                'close' => array('type' => 'integer', 'description' => '1关闭0开启'),
            ), array('tids')),
            $this->fn('list_classes', '列出商品分类', array(
                'active' => array('type' => 'integer', 'description' => '1启用0禁用，不传全部'),
            )),
            $this->fn('save_class', '新增或修改分类；传cid为修改，不传为新增', array(
                'cid' => array('type' => 'integer', 'description' => '分类ID，修改时必填'),
                'name' => array('type' => 'string', 'description' => '分类名'),
                'active' => array('type' => 'integer', 'description' => '1启用0禁用'),
                'sort' => array('type' => 'integer', 'description' => '排序'),
            ), array('name')),
            $this->fn('list_sites', '列出分站', array(
                'keyword' => array('type' => 'string', 'description' => '域名/用户名关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('set_site', '开启/关闭分站，或调整版本', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID'),
                'status' => array('type' => 'integer', 'description' => '1启用0禁用'),
                'power' => array('type' => 'integer', 'description' => '权限等级，可选'),
            ), array('zid')),
            $this->fn('site_recharge', '给分站余额充值或扣款', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID'),
                'money' => array('type' => 'number', 'description' => '金额，正数充值负数扣款'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('zid', 'money', 'confirm')),
            $this->fn('get_config', '读取站点配置项（可指定keys，不传返回常用项）', array(
                'keys' => array(
                    'type' => 'array',
                    'description' => '配置键名数组',
                    'items' => array('type' => 'string'),
                ),
            )),
            $this->fn('update_config', '更新白名单内的站点配置（如 sitename/kfqq/anounce 等）', array(
                'items' => array(
                    'type' => 'object',
                    'description' => '键值对对象，例如 {"sitename":"新店名"}',
                    'additionalProperties' => array('type' => 'string'),
                ),
            ), array('items')),
            $this->fn('list_shequ', '列出对接货源站点', array()),
            $this->fn('supplier_pull_goods', '从同系统对接站点拉取货源商品列表', array(
                'shequ_id' => array('type' => 'integer', 'description' => '对接站点ID，不传则取第一个daishua'),
            )),
            $this->fn('save_shequ', '新增或更新对接站点；传id为更新', array(
                'id' => array('type' => 'integer', 'description' => '对接站点ID，更新时必填'),
                'url' => array('type' => 'string', 'description' => '对接域名，不含协议'),
                'username' => array('type' => 'string', 'description' => '对接账号'),
                'password' => array('type' => 'string', 'description' => '对接密码/密钥'),
                'type' => array('type' => 'string', 'description' => '类型，如 daishua'),
                'protocol' => array('type' => 'integer', 'description' => '0=http 1=https'),
                'status' => array('type' => 'integer', 'description' => '1启用0停用'),
                'remark' => array('type' => 'string', 'description' => '备注'),
                'confirm' => array('type' => 'boolean', 'description' => '写操作必须 true'),
            ), array('confirm')),
            $this->fn('list_pay_orders', '查询支付订单', array(
                'keyword' => array('type' => 'string', 'description' => '订单号/账号关键词'),
                'status' => array('type' => 'integer', 'description' => '0未支付1已支付'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('list_workorders', '查询工单', array(
                'status' => array('type' => 'integer', 'description' => '工单状态，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('set_workorder_status', '修改工单状态', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'status' => array('type' => 'integer', 'description' => '目标状态'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'status', 'confirm')),
            $this->fn('list_faka', '查询发卡库存', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('list_kms', '查询卡密', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('list_articles', '查询文章', array(
                'keyword' => array('type' => 'string', 'description' => '标题关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('list_tixian', '查询分站提现', array(
                'status' => array('type' => 'integer', 'description' => '0待处理1已完成2已拒绝，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('set_tixian_status', '处理提现（通过或拒绝）', array(
                'id' => array('type' => 'integer', 'description' => '提现ID'),
                'status' => array('type' => 'integer', 'description' => '1通过2拒绝'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'status', 'confirm')),
            $this->fn('list_messages', '查询站内通知', array(
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('list_price_rules', '列出加价模板', array()),
            $this->fn('capability_catalog', '返回当前 AI 可调用的全部能力清单', array()),
        );
    }

    private function fn($name, $desc, $props, $required = array())
    {
        // DeepSeek/OpenAI 要求 properties 必须是 JSON object；PHP 空数组会编成 [] 导致 400
        $properties = $props;
        if (!is_array($properties) || count($properties) === 0) {
            $properties = new \stdClass();
        }
        $parameters = array(
            'type' => 'object',
            'properties' => $properties,
        );
        if (is_array($required) && count($required) > 0) {
            $parameters['required'] = array_values($required);
        }
        return array(
            'type' => 'function',
            'function' => array(
                'name' => $name,
                'description' => $desc,
                'parameters' => $parameters,
            ),
        );
    }

    /**
     * @param string $name
     * @param array $args
     * @return array
     */
    public function execute($name, $args)
    {
        if (!is_array($args)) $args = array();
        try {
            switch ($name) {
                case 'dashboard_stats':
                    return $this->dashboardStats();
                case 'search_orders':
                    return $this->searchOrders($args);
                case 'get_order':
                    return $this->getOrder($args);
                case 'set_order_status':
                    return $this->setOrderStatus($args);
                case 'batch_set_order_status':
                    return $this->batchSetOrderStatus($args);
                case 'refund_order':
                    return $this->refundOrder($args);
                case 'redo_dock_order':
                    return $this->redoDockOrder($args);
                case 'list_goods':
                    return $this->listGoods($args);
                case 'get_goods':
                    return $this->getGoods($args);
                case 'update_goods':
                    return $this->updateGoods($args);
                case 'create_goods':
                    return $this->createGoods($args);
                case 'set_goods_shelf':
                    return $this->setGoodsShelf($args);
                case 'list_classes':
                    return $this->listClasses($args);
                case 'save_class':
                    return $this->saveClass($args);
                case 'list_sites':
                    return $this->listSites($args);
                case 'set_site':
                    return $this->setSite($args);
                case 'site_recharge':
                    return $this->siteRecharge($args);
                case 'get_config':
                    return $this->getConfig($args);
                case 'update_config':
                    return $this->updateConfig($args);
                case 'list_shequ':
                    return $this->listShequ();
                case 'supplier_pull_goods':
                    return $this->supplierPullGoods($args);
                case 'save_shequ':
                    return $this->saveShequ($args);
                case 'list_pay_orders':
                    return $this->listPayOrders($args);
                case 'list_workorders':
                    return $this->listWorkorders($args);
                case 'set_workorder_status':
                    return $this->setWorkorderStatus($args);
                case 'list_faka':
                    return $this->listFaka($args);
                case 'list_kms':
                    return $this->listKms($args);
                case 'list_articles':
                    return $this->listArticles($args);
                case 'list_tixian':
                    return $this->listTixian($args);
                case 'set_tixian_status':
                    return $this->setTixianStatus($args);
                case 'list_messages':
                    return $this->listMessages($args);
                case 'list_price_rules':
                    return $this->listPriceRules();
                case 'capability_catalog':
                    return $this->capabilityCatalog();
                default:
                    return array('ok' => false, 'error' => '未知工具: ' . $name);
            }
        } catch (\Exception $e) {
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    private function dashboardStats()
    {
        $thtime = date('Y-m-d') . ' 00:00:00';
        $yesterday = date('Y-m-d', strtotime('-1 day')) . ' 00:00:00';
        return array(
            'ok' => true,
            'data' => array(
                'orders_total' => intval($this->DB->getColumn("SELECT count(*) FROM pre_orders")),
                'orders_done' => intval($this->DB->getColumn("SELECT count(*) FROM pre_orders WHERE status=1")),
                'orders_pending' => intval($this->DB->getColumn("SELECT count(*) FROM pre_orders WHERE status=0")),
                'orders_today' => intval($this->DB->getColumn("SELECT count(*) FROM pre_orders WHERE addtime>='$thtime'")),
                'pay_today' => round(floatval($this->DB->getColumn("SELECT sum(money) FROM pre_pay WHERE type IN ('qqpay','wxpay','alipay') AND addtime>='$thtime' AND status=1")), 2),
                'sites' => intval($this->DB->getColumn("SELECT count(*) FROM pre_site")),
                'sites_today' => intval($this->DB->getColumn("SELECT count(*) FROM pre_site WHERE addtime>='$thtime'")),
                'goods_active' => intval($this->DB->getColumn("SELECT count(*) FROM pre_tools WHERE active=1")),
                'workorder_open' => intval($this->DB->getColumn("SELECT count(*) FROM pre_workorder WHERE status=0 OR status=1")),
                'yesterday_orders' => intval($this->DB->getColumn("SELECT count(*) FROM pre_orders WHERE addtime>='$yesterday' AND addtime<'$thtime'")),
            ),
        );
    }

    private function orderStatusText($status)
    {
        $map = array(
            0 => '未处理',
            1 => '已完成',
            2 => '处理中',
            3 => '异常',
            4 => '已退款',
            5 => '删除',
        );
        $s = intval($status);
        return isset($map[$s]) ? $map[$s] : ('状态' . $s);
    }

    private function parseOrderStatus($status)
    {
        if (is_numeric($status)) {
            return intval($status);
        }
        $s = trim(strval($status));
        $map = array(
            '未处理' => 0, '待处理' => 0,
            '已完成' => 1, '完成' => 1,
            '处理中' => 2, '进行中' => 2,
            '异常' => 3,
            '已退款' => 4, '退款' => 4,
            '删除' => 5,
        );
        return isset($map[$s]) ? $map[$s] : -1;
    }

    private function decorateOrderRow($row)
    {
        if (!is_array($row)) return $row;
        $row['status_text'] = $this->orderStatusText(isset($row['status']) ? $row['status'] : 0);
        return $row;
    }

    private function searchOrders($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $st = is_numeric($args['status']) ? intval($args['status']) : $this->parseOrderStatus($args['status']);
            if ($st >= 0) $where .= ' AND status=' . $st;
        }
        if (!empty($args['tid'])) {
            $where .= ' AND tid=' . intval($args['tid']);
        }
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (input LIKE '%$kw%' OR input2 LIKE '%$kw%' OR tradeno LIKE '%$kw%' OR result LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT id,tid,zid,input,value,status,money,cost,addtime,tradeno FROM pre_orders WHERE $where ORDER BY id DESC LIMIT $limit");
        $data = array();
        foreach ($rows as $row) {
            $data[] = $this->decorateOrderRow($row);
        }
        return array('ok' => true, 'count' => count($data), 'data' => $data);
    }

    private function getOrder($args)
    {
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $row = $this->DB->getRow("SELECT * FROM pre_orders WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '订单不存在');
        $tool = $this->DB->getRow("SELECT tid,name,price,is_curl,shequ,goods_id FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
        return array('ok' => true, 'order' => $this->decorateOrderRow($row), 'goods' => $tool);
    }

    private function setOrderStatus($args)
    {
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $status = $this->parseOrderStatus(isset($args['status']) ? $args['status'] : -1);
        if ($id <= 0) return array('ok' => false, 'error' => '无效订单ID');
        if ($status === 5) {
            $ok = $this->DB->exec("DELETE FROM pre_orders WHERE id='$id'");
            return array('ok' => $ok !== false, 'msg' => $ok !== false ? '订单已删除' : $this->DB->error());
        }
        if ($status < 0 || $status > 4) return array('ok' => false, 'error' => '状态无效，请用未处理/已完成/处理中/异常/已退款/删除');
        $result = isset($args['result']) ? addslashes($args['result']) : null;
        if ($result !== null && $result !== '') {
            $sql = "UPDATE pre_orders SET status='$status', result='$result' WHERE id='$id'";
        } else {
            $sql = "UPDATE pre_orders SET status='$status', result=NULL WHERE id='$id'";
        }
        $ok = $this->DB->exec($sql);
        return array(
            'ok' => $ok !== false,
            'msg' => $ok !== false ? ('订单已更新为' . $this->orderStatusText($status)) : $this->DB->error(),
            'status' => $status,
            'status_text' => $this->orderStatusText($status),
        );
    }

    private function batchSetOrderStatus($args)
    {
        if (empty($args['confirm'])) {
            return array('ok' => false, 'error' => '批量改状态需 confirm=true 确认');
        }
        $ids = isset($args['ids']) ? $args['ids'] : array();
        if (!is_array($ids) || !$ids) return array('ok' => false, 'error' => 'ids 不能为空');
        $status = $this->parseOrderStatus(isset($args['status']) ? $args['status'] : -1);
        if ($status < 0 || $status > 5) return array('ok' => false, 'error' => '状态无效');
        $result = isset($args['result']) ? strval($args['result']) : '';
        $okIds = array();
        $fail = array();
        foreach ($ids as $id) {
            $ret = $this->setOrderStatus(array(
                'id' => intval($id),
                'status' => $status,
                'result' => $result,
            ));
            if (!empty($ret['ok'])) $okIds[] = intval($id);
            else $fail[] = array('id' => intval($id), 'error' => isset($ret['error']) ? $ret['error'] : (isset($ret['msg']) ? $ret['msg'] : '失败'));
        }
        return array(
            'ok' => count($fail) === 0,
            'msg' => '成功 ' . count($okIds) . ' 笔，失败 ' . count($fail) . ' 笔，目标状态：' . $this->orderStatusText($status),
            'success_ids' => $okIds,
            'failed' => $fail,
        );
    }

    private function redoDockOrder($args)
    {
        if (empty($args['confirm'])) {
            return array('ok' => false, 'error' => '重新对接需 confirm=true 确认');
        }
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效订单ID');
        if (!function_exists('do_goods')) {
            return array('ok' => false, 'error' => '系统未加载对接下单函数 do_goods');
        }
        $result = do_goods($id);
        $ok = is_string($result) && (strpos($result, '成功') !== false);
        return array(
            'ok' => $ok,
            'msg' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE),
            'id' => $id,
        );
    }

    private function refundOrder($args)
    {
        if (empty($args['confirm'])) {
            return array('ok' => false, 'error' => '退款需 confirm=true 确认');
        }
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $row = $this->DB->getRow("SELECT * FROM pre_orders WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '订单不存在');
        if ($row['status'] == 4) return array('ok' => false, 'error' => '已退款，勿重复');
        if ($row['status'] != 0 && $row['status'] != 3) {
            return array('ok' => false, 'error' => '仅未处理/异常订单可退款');
        }
        $money = isset($args['money']) && floatval($args['money']) > 0 ? floatval($args['money']) : floatval($row['money']);
        $msg = '';
        if (is_numeric($row['userid'])) {
            $zid = intval($row['userid']);
            if (function_exists('changeUserMoney')) {
                changeUserMoney($zid, $money, true, '退款', '订单(ID' . $id . ')已退款到余额');
                $msg = '已退款到UID' . $zid;
            } else {
                return array('ok' => false, 'error' => 'changeUserMoney 不可用');
            }
        } else {
            $msg = '游客订单，状态已改退款，需人工打款';
        }
        if (function_exists('rollbackPoint')) {
            rollbackPoint($id);
        }
        $this->DB->exec("UPDATE pre_orders SET status='4', result=NULL WHERE id='$id'");
        return array('ok' => true, 'msg' => $msg, 'money' => $money);
    }

    private function listGoods($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (isset($args['active']) && $args['active'] !== '' && $args['active'] !== null) {
            $where .= ' AND active=' . intval($args['active']);
        }
        if (!empty($args['cid'])) {
            $where .= ' AND cid=' . intval($args['cid']);
        }
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND name LIKE '%$kw%'";
        }
        $rows = $this->DB->getAll("SELECT tid,cid,name,price,cost,cost2,active,close,stock,is_curl,shequ,goods_id,sort FROM pre_tools WHERE $where ORDER BY sort ASC, tid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function getGoods($args)
    {
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        $row = $this->DB->getRow("SELECT * FROM pre_tools WHERE tid='$tid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '商品不存在');
        return array('ok' => true, 'data' => $row);
    }

    private function updateGoods($args)
    {
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        if ($tid <= 0) return array('ok' => false, 'error' => '无效商品ID');
        $allow = array('name', 'price', 'cost', 'cost2', 'cid', 'desc', 'alert', 'input', 'inputs', 'stock', 'active', 'close', 'goods_id', 'shequ', 'is_curl');
        $sets = array();
        foreach ($allow as $k) {
            if (!array_key_exists($k, $args)) continue;
            $v = $args[$k];
            if (in_array($k, array('price', 'cost', 'cost2'), true)) {
                $sets[] = "`$k`='" . addslashes(strval($v)) . "'";
            } elseif (in_array($k, array('cid', 'stock', 'active', 'close', 'shequ', 'is_curl'), true)) {
                $sets[] = "`$k`='" . intval($v) . "'";
            } else {
                $sets[] = "`$k`='" . addslashes(strval($v)) . "'";
            }
        }
        if (!$sets) return array('ok' => false, 'error' => '没有可更新字段');
        $ok = $this->DB->exec("UPDATE pre_tools SET " . implode(',', $sets) . " WHERE tid='$tid' LIMIT 1");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '商品已更新' : $this->DB->error(), 'tid' => $tid);
    }

    private function createGoods($args)
    {
        $name = trim(isset($args['name']) ? $args['name'] : '');
        $cid = intval(isset($args['cid']) ? $args['cid'] : 0);
        $price = isset($args['price']) ? floatval($args['price']) : 0;
        if ($name === '' || $cid <= 0) return array('ok' => false, 'error' => 'name 与 cid 必填');
        $desc = isset($args['desc']) ? $args['desc'] : '';
        $input = isset($args['input']) && $args['input'] !== '' ? $args['input'] : '下单账号';
        $inputs = isset($args['inputs']) ? $args['inputs'] : '';
        $is_curl = isset($args['is_curl']) ? intval($args['is_curl']) : 0;
        $shequ = isset($args['shequ']) ? intval($args['shequ']) : 0;
        $goods_id = isset($args['goods_id']) ? $args['goods_id'] : '';
        $active = isset($args['active']) ? intval($args['active']) : 1;
        $sort = intval($this->DB->getColumn("SELECT IFNULL(MAX(sort),0)+1 FROM pre_tools WHERE cid='$cid'"));
        $sql = "INSERT INTO pre_tools (cid,name,price,cost,cost2,prid,input,inputs,`desc`,value,is_curl,shequ,goods_id,multi,min,max,sort,active,close) VALUES (
            '$cid','" . addslashes($name) . "','$price','$price','$price',0,'" . addslashes($input) . "','" . addslashes($inputs) . "','" . addslashes($desc) . "',1,'$is_curl','$shequ','" . addslashes($goods_id) . "',0,1,1,'$sort','$active',0)";
        $ok = $this->DB->exec($sql);
        if ($ok === false) return array('ok' => false, 'error' => $this->DB->error());
        $tid = intval($this->DB->lastInsertId());
        return array('ok' => true, 'tid' => $tid, 'msg' => '商品已创建');
    }

    private function setGoodsShelf($args)
    {
        $tids = isset($args['tids']) ? $args['tids'] : array();
        if (!is_array($tids) || !$tids) return array('ok' => false, 'error' => 'tids 不能为空');
        $n = 0;
        foreach ($tids as $tid) {
            $tid = intval($tid);
            if ($tid <= 0) continue;
            if (isset($args['active'])) {
                $this->DB->exec("UPDATE pre_tools SET active='" . intval($args['active']) . "' WHERE tid='$tid' LIMIT 1");
            } elseif (isset($args['close'])) {
                $this->DB->exec("UPDATE pre_tools SET close='" . intval($args['close']) . "' WHERE tid='$tid' LIMIT 1");
            } else {
                return array('ok' => false, 'error' => '请传 active 或 close');
            }
            $n++;
        }
        return array('ok' => true, 'msg' => "已处理 {$n} 个商品");
    }

    private function listClasses($args)
    {
        $where = '1=1';
        if (isset($args['active']) && $args['active'] !== '' && $args['active'] !== null) {
            $where .= ' AND active=' . intval($args['active']);
        }
        $rows = $this->DB->getAll("SELECT * FROM pre_class WHERE $where ORDER BY sort ASC, cid ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function saveClass($args)
    {
        $name = trim(isset($args['name']) ? $args['name'] : '');
        if ($name === '') return array('ok' => false, 'error' => '分类名必填');
        $active = isset($args['active']) ? intval($args['active']) : 1;
        $sort = isset($args['sort']) ? intval($args['sort']) : 10;
        if (!empty($args['cid'])) {
            $cid = intval($args['cid']);
            $ok = $this->DB->exec("UPDATE pre_class SET name='" . addslashes($name) . "', active='$active', sort='$sort' WHERE cid='$cid'");
            return array('ok' => $ok !== false, 'cid' => $cid, 'msg' => '分类已更新');
        }
        $ok = $this->DB->exec("INSERT INTO pre_class (name,active,sort) VALUES ('" . addslashes($name) . "','$active','$sort')");
        if ($ok === false) return array('ok' => false, 'error' => $this->DB->error());
        return array('ok' => true, 'cid' => intval($this->DB->lastInsertId()), 'msg' => '分类已创建');
    }

    private function listSites($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (user LIKE '%$kw%' OR domain LIKE '%$kw%' OR qq LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT zid,upzid,power,domain,user,qq,rmb,status,addtime FROM pre_site WHERE $where ORDER BY zid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function setSite($args)
    {
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        if ($zid <= 1) return array('ok' => false, 'error' => '不能操作主站或无效 zid');
        $sets = array();
        if (isset($args['status'])) $sets[] = "status='" . intval($args['status']) . "'";
        if (isset($args['power'])) $sets[] = "power='" . intval($args['power']) . "'";
        if (!$sets) return array('ok' => false, 'error' => '无更新字段');
        $ok = $this->DB->exec("UPDATE pre_site SET " . implode(',', $sets) . " WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '分站已更新' : $this->DB->error());
    }

    private function siteRecharge($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '充值需 confirm=true');
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        $money = floatval(isset($args['money']) ? $args['money'] : 0);
        if ($zid <= 1 || $money == 0) return array('ok' => false, 'error' => '参数无效');
        $site = $this->DB->getRow("SELECT zid,rmb FROM pre_site WHERE zid='$zid' LIMIT 1");
        if (!$site) return array('ok' => false, 'error' => '分站不存在');
        if (function_exists('changeUserMoney')) {
            $doAdd = $money > 0;
            changeUserMoney($zid, abs($money), $doAdd, $doAdd ? 'AI充值' : 'AI扣款', 'AI助手调整余额');
            $new = $this->DB->getColumn("SELECT rmb FROM pre_site WHERE zid='$zid'");
            return array('ok' => true, 'msg' => '余额已调整', 'rmb' => $new);
        }
        $newRmb = floatval($site['rmb']) + $money;
        $ok = $this->DB->exec("UPDATE pre_site SET rmb='$newRmb' WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'rmb' => $newRmb);
    }

    private function configWhitelist()
    {
        return array(
            'sitename', 'title', 'keywords', 'description', 'kfqq', 'anounce', 'modal', 'footer',
            'gg_search', 'paymsg', 'cjmsg', 'appurl', 'daiguaurl', 'musicurl', 'build',
            'fenzhan_buy', 'gift_open', 'verify_open', 'captcha_open', 'template', 'ui_cdn',
            'apikey', 'ai_enabled', 'ai_system_prompt', 'ai_max_tokens', 'ai_temperature',
        );
    }

    private function getConfig($args)
    {
        $keys = isset($args['keys']) && is_array($args['keys']) && $args['keys'] ? $args['keys'] : array(
            'sitename', 'kfqq', 'anounce', 'verify_open', 'template', 'apikey', 'ai_enabled', 'ai_model', 'ai_api_base'
        );
        $out = array();
        foreach ($keys as $k) {
            $k = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
            if ($k === '') continue;
            if ($k === 'ai_api_key' || $k === 'admin_pwd' || $k === 'admin_user') {
                $out[$k] = isset($this->conf[$k]) && $this->conf[$k] !== '' ? '(已设置)' : '(未设置)';
                continue;
            }
            $out[$k] = isset($this->conf[$k]) ? $this->conf[$k] : null;
        }
        return array('ok' => true, 'data' => $out);
    }

    private function updateConfig($args)
    {
        $items = isset($args['items']) ? $args['items'] : null;
        if (!is_array($items) || !$items) return array('ok' => false, 'error' => 'items 必须是对象');
        $allow = $this->configWhitelist();
        $changed = array();
        foreach ($items as $k => $v) {
            $k = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
            if (!in_array($k, $allow, true)) continue;
            if (!function_exists('saveSetting')) {
                return array('ok' => false, 'error' => 'saveSetting 不可用');
            }
            saveSetting($k, is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            $changed[] = $k;
        }
        if ($this->CACHE) $this->CACHE->clear();
        if (!$changed) return array('ok' => false, 'error' => '没有落在白名单内的配置项');
        return array('ok' => true, 'changed' => $changed);
    }

    private function listShequ()
    {
        $rows = $this->DB->getAll("SELECT id,url,username,type,status,protocol,remark FROM pre_shequ ORDER BY id ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function supplierPullGoods($args)
    {
        $id = isset($args['shequ_id']) ? intval($args['shequ_id']) : 0;
        if ($id > 0) {
            $shequ = $this->DB->getRow("SELECT * FROM pre_shequ WHERE id='$id' LIMIT 1");
        } else {
            $shequ = $this->DB->getRow("SELECT * FROM pre_shequ WHERE type='daishua' ORDER BY id ASC LIMIT 1");
        }
        if (!$shequ) return array('ok' => false, 'error' => '未找到对接站点');
        if (!class_exists('\\plugins\\third_daishua')) {
            return array('ok' => false, 'error' => 'third_daishua 插件不可用');
        }
        $plugin = new \plugins\third_daishua($shequ);
        $list = $plugin->goods_list();
        if (!is_array($list)) {
            return array('ok' => false, 'error' => is_string($list) ? $list : json_encode($list, JSON_UNESCAPED_UNICODE));
        }
        return array('ok' => true, 'shequ_id' => intval($shequ['id']), 'count' => count($list), 'data' => array_slice($list, 0, 40));
    }

    private function saveShequ($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存对接站点需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $fields = array('url', 'username', 'password', 'type', 'protocol', 'status', 'remark');
        $data = array();
        foreach ($fields as $f) {
            if (array_key_exists($f, $args)) $data[$f] = $args[$f];
        }
        if ($id > 0) {
            if (!$data) return array('ok' => false, 'error' => '无更新字段');
            $sets = array();
            foreach ($data as $k => $v) {
                if (in_array($k, array('protocol', 'status'), true)) $sets[] = "`$k`='" . intval($v) . "'";
                else $sets[] = "`$k`='" . addslashes(strval($v)) . "'";
            }
            $ok = $this->DB->exec("UPDATE pre_shequ SET " . implode(',', $sets) . " WHERE id='$id'");
            return array('ok' => $ok !== false, 'id' => $id, 'msg' => '对接站点已更新');
        }
        $url = isset($data['url']) ? trim($data['url']) : '';
        if ($url === '') return array('ok' => false, 'error' => '新建时 url 必填');
        $username = isset($data['username']) ? $data['username'] : '';
        $password = isset($data['password']) ? $data['password'] : '';
        $type = isset($data['type']) ? $data['type'] : 'daishua';
        $protocol = isset($data['protocol']) ? intval($data['protocol']) : 0;
        $status = isset($data['status']) ? intval($data['status']) : 1;
        $remark = isset($data['remark']) ? $data['remark'] : '';
        $ok = $this->DB->exec("INSERT INTO pre_shequ (url,username,password,type,protocol,status,remark) VALUES ('" . addslashes($url) . "','" . addslashes($username) . "','" . addslashes($password) . "','" . addslashes($type) . "','$protocol','$status','" . addslashes($remark) . "')");
        if ($ok === false) return array('ok' => false, 'error' => $this->DB->error());
        return array('ok' => true, 'id' => intval($this->DB->lastInsertId()), 'msg' => '对接站点已创建');
    }

    private function listPayOrders($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $where .= ' AND status=' . intval($args['status']);
        }
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (trade_no LIKE '%$kw%' OR input LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT trade_no,type,tid,input,money,status,ip,addtime,endtime FROM pre_pay WHERE $where ORDER BY addtime DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listWorkorders($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $where .= ' AND status=' . intval($args['status']);
        }
        $rows = $this->DB->getAll("SELECT * FROM pre_workorder WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function setWorkorderStatus($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '处理工单需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $status = intval(isset($args['status']) ? $args['status'] : -1);
        if ($id <= 0) return array('ok' => false, 'error' => '无效工单ID');
        $ok = $this->DB->exec("UPDATE pre_workorder SET status='$status' WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '工单状态已更新' : $this->DB->error());
    }

    private function listFaka($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (!empty($args['tid'])) $where .= ' AND tid=' . intval($args['tid']);
        $rows = $this->DB->getAll("SELECT kid,tid,km,pw,orderid,addtime,usetime FROM pre_faka WHERE $where ORDER BY kid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listKms($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (!empty($args['tid'])) $where .= ' AND tid=' . intval($args['tid']);
        $rows = $this->DB->getAll("SELECT kid,tid,km,money,status,addtime FROM pre_kms WHERE $where ORDER BY kid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listArticles($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND title LIKE '%$kw%'";
        }
        $rows = $this->DB->getAll("SELECT id,title,active,addtime FROM pre_article WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listTixian($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $where .= ' AND status=' . intval($args['status']);
        }
        $rows = $this->DB->getAll("SELECT id,zid,money,realmoney,pay_type,pay_account,pay_name,status,addtime FROM pre_tixian WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function setTixianStatus($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '处理提现需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $status = intval(isset($args['status']) ? $args['status'] : -1);
        if ($id <= 0 || ($status !== 1 && $status !== 2)) return array('ok' => false, 'error' => '参数无效，status 仅支持 1通过 2拒绝');
        $row = $this->DB->getRow("SELECT * FROM pre_tixian WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '提现记录不存在');
        if (intval($row['status']) !== 0) return array('ok' => false, 'error' => '仅待处理提现可操作');
        $ok = $this->DB->exec("UPDATE pre_tixian SET status='$status' WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => $status === 1 ? '已通过提现' : '已拒绝提现');
    }

    private function listMessages($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $rows = $this->DB->getAll("SELECT id,zid,type,title,content,addtime FROM pre_message ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listPriceRules()
    {
        $rows = $this->DB->getAll("SELECT * FROM pre_price ORDER BY id ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function capabilityCatalog()
    {
        $defs = $this->definitions();
        $list = array();
        foreach ($defs as $d) {
            $list[] = array(
                'name' => $d['function']['name'],
                'label' => Store::toolLabel($d['function']['name']),
                'description' => $d['function']['description'],
            );
        }
        return array('ok' => true, 'count' => count($list), 'data' => $list);
    }
}
