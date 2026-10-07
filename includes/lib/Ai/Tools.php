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
    /** @var string admin|user|shop */
    private $scope = 'admin';
    /** @var array zid/power/cookiesid/ip/context */
    private $actor = array();

    public function __construct($DB, $conf, $CACHE, $opts = array())
    {
        $this->DB = $DB;
        $this->conf = $conf;
        $this->CACHE = $CACHE;
        if (is_array($opts)) {
            if (!empty($opts['scope'])) {
                $s = strtolower(trim($opts['scope']));
                if (in_array($s, array('admin', 'user', 'shop'), true)) $this->scope = $s;
            }
            if (!empty($opts['actor']) && is_array($opts['actor'])) {
                $this->actor = $opts['actor'];
            }
        }
    }

    public function getScope()
    {
        return $this->scope;
    }

    /**
     * OpenAI tools schema
     * @return array
     */
    public function definitions()
    {
        if ($this->scope === 'shop') {
            return $this->definitionsShop();
        }
        if ($this->scope === 'user') {
            return $this->definitionsUser();
        }
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
                'price' => array('type' => 'number', 'description' => '售价/成本价字段 price'),
                'cost' => array('type' => 'number', 'description' => '普及价'),
                'cost2' => array('type' => 'number', 'description' => '专业价'),
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'prid' => array('type' => 'integer', 'description' => '加价模板ID，0表示不用模板'),
                'desc' => array('type' => 'string', 'description' => '商品说明'),
                'alert' => array('type' => 'string', 'description' => '下单提示'),
                'input' => array('type' => 'string', 'description' => '主输入框名称'),
                'inputs' => array('type' => 'string', 'description' => '附加输入，|分隔'),
                'stock' => array('type' => 'integer', 'description' => '库存，空字符串或不传可表示不限（传 null_stock=true 清空）'),
                'null_stock' => array('type' => 'boolean', 'description' => 'true 则库存设为不限'),
                'active' => array('type' => 'integer', 'description' => '1上架0下架'),
                'close' => array('type' => 'integer', 'description' => '1关闭购买0开启'),
                'goods_id' => array('type' => 'string', 'description' => '对接商品ID'),
                'shequ' => array('type' => 'integer', 'description' => '对接站点ID'),
                'is_curl' => array('type' => 'integer', 'description' => '对接方式，2=同系统API'),
                'shopimg' => array('type' => 'string', 'description' => '商品图片URL（仅链接，不上传文件）'),
                'value' => array('type' => 'integer', 'description' => '默认下单数量'),
                'multi' => array('type' => 'integer', 'description' => '是否允许多份 0/1'),
                'min' => array('type' => 'integer', 'description' => '最少购买份数'),
                'max' => array('type' => 'integer', 'description' => '最多购买份数'),
                'sort' => array('type' => 'integer', 'description' => '排序'),
                'goods_param' => array('type' => 'string', 'description' => '对接参数'),
                'validate' => array('type' => 'integer', 'description' => '验证开关'),
                'valiserv' => array('type' => 'string', 'description' => '验证服务标识'),
                'prices' => array('type' => 'string', 'description' => '自定义价格串'),
            ), array('tid')),
            $this->fn('create_goods', '快速创建一个本地/对接商品', array(
                'name' => array('type' => 'string', 'description' => '商品名称'),
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'price' => array('type' => 'number', 'description' => '价格'),
                'prid' => array('type' => 'integer', 'description' => '加价模板ID，默认0'),
                'desc' => array('type' => 'string', 'description' => '说明'),
                'input' => array('type' => 'string', 'description' => '主输入名，默认下单账号'),
                'inputs' => array('type' => 'string', 'description' => '附加输入|分隔'),
                'is_curl' => array('type' => 'integer', 'description' => '0本地1访问URL2同系统对接4发卡'),
                'shequ' => array('type' => 'integer', 'description' => '对接站点ID'),
                'goods_id' => array('type' => 'string', 'description' => '货源商品ID'),
                'shopimg' => array('type' => 'string', 'description' => '图片URL'),
                'stock' => array('type' => 'integer', 'description' => '库存，不传则不限'),
                'multi' => array('type' => 'integer', 'description' => '是否多份默认0'),
                'min' => array('type' => 'integer', 'description' => '最少份数默认1'),
                'max' => array('type' => 'integer', 'description' => '最多份数默认1'),
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
            $this->fn('apply_price_rule', '给商品或分类批量挂加价模板；传tids或cids其一', array(
                'prid' => array('type' => 'integer', 'description' => '加价模板ID'),
                'tids' => array('type' => 'array', 'description' => '商品ID数组', 'items' => array('type' => 'integer')),
                'cids' => array('type' => 'array', 'description' => '分类ID数组', 'items' => array('type' => 'integer')),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('prid', 'confirm')),
            $this->fn('batch_set_stock', '批量设置商品库存；stock空字符串或不传表示不限库存', array(
                'tids' => array('type' => 'array', 'description' => '商品ID数组', 'items' => array('type' => 'integer')),
                'stock' => array('type' => 'string', 'description' => '库存数字，空=不限'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tids', 'confirm')),
            $this->fn('delete_goods', '删除商品（会连带删除该商品订单，高危）', array(
                'tids' => array('type' => 'array', 'description' => '商品ID数组', 'items' => array('type' => 'integer')),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tids', 'confirm')),
            $this->fn('copy_goods', '复制商品（新建副本）', array(
                'tids' => array('type' => 'array', 'description' => '商品ID数组', 'items' => array('type' => 'integer')),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tids', 'confirm')),
            $this->fn('move_goods', '批量移动商品到指定分类', array(
                'tids' => array('type' => 'array', 'description' => '商品ID数组', 'items' => array('type' => 'integer')),
                'cid' => array('type' => 'integer', 'description' => '目标分类ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tids', 'cid', 'confirm')),
            $this->fn('list_classes', '列出商品分类', array(
                'active' => array('type' => 'integer', 'description' => '1启用0禁用，不传全部'),
            )),
            $this->fn('save_class', '新增或修改分类；传cid为修改，不传为新增', array(
                'cid' => array('type' => 'integer', 'description' => '分类ID，修改时必填'),
                'name' => array('type' => 'string', 'description' => '分类名'),
                'active' => array('type' => 'integer', 'description' => '1启用0禁用'),
                'sort' => array('type' => 'integer', 'description' => '排序'),
            ), array('name')),
            $this->fn('delete_class', '删除分类及其下全部商品（高危）', array(
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('cid', 'confirm')),
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
            $this->fn('extend_site', '给分站续期（按月延长到期日）', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID'),
                'months' => array('type' => 'integer', 'description' => '续期月数，默认1'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('zid', 'confirm')),
            $this->fn('list_money_records', '查询分站余额流水（只读）', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID，可空'),
                'keyword' => array('type' => 'string', 'description' => '备注关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('list_invite_shops', '列出推广奖励商品', array()),
            $this->fn('save_invite_shop', '新增或修改推广商品；传id为修改。type:0下单金额达标1累计访问次数；times:0一次性1可多次', array(
                'id' => array('type' => 'integer', 'description' => '推广商品配置ID'),
                'tid' => array('type' => 'integer', 'description' => '奖励发放的商品ID'),
                'type' => array('type' => 'integer', 'description' => '0金额1访问次数'),
                'value' => array('type' => 'number', 'description' => '达标阈值或访问次数'),
                'times' => array('type' => 'integer', 'description' => '0一次性1可多次'),
                'sort' => array('type' => 'integer', 'description' => '排序'),
                'active' => array('type' => 'integer', 'description' => '1显示0隐藏'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tid', 'value', 'confirm')),
            $this->fn('delete_invite_shop', '删除推广商品配置', array(
                'id' => array('type' => 'integer', 'description' => '配置ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_invite_logs', '查询推广访问/领取记录', array(
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('get_config', '读取站点配置（密钥只显示是否已设置）。不确定能改什么时先调 config_catalog', array(
                'keys' => array(
                    'type' => 'array',
                    'description' => '配置键名数组，不传返回常用项',
                    'items' => array('type' => 'string'),
                ),
            )),
            $this->fn('update_config', '更新白名单内站点配置。公告类(anounce/modal/footer等)可直接写入完整 HTML（按用户要求排版，勿套固定模板）。密钥类会被拒绝', array(
                'items' => array(
                    'type' => 'object',
                    'description' => '键值对。示例：{"sitename":"新店名"}；公告 HTML 示例：{"anounce":"<div style=\\"padding:12px\\">...</div>"}。anounce/modal/footer/gg_search/paymsg 等按用户要求写完整 HTML，勿套固定模板',
                    'additionalProperties' => array('type' => 'string'),
                ),
                'confirm' => array('type' => 'boolean', 'description' => '改重要业务开关建议 true；批量改设置时传 true'),
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
                'status' => array('type' => 'integer', 'description' => '工单状态：0待处理1已回复2已完结，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('get_workorder', '查看工单详情与沟通记录', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
            ), array('id')),
            $this->fn('reply_workorder', '回复工单（客服侧），可选同时完结、发提醒邮件', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'content' => array('type' => 'string', 'description' => '回复内容'),
                'complete' => array('type' => 'boolean', 'description' => 'true则回复后直接完结'),
                'send_email' => array('type' => 'boolean', 'description' => 'true则邮件提醒分站（需已配邮箱且分站有QQ）'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'content', 'confirm')),
            $this->fn('set_workorder_status', '修改工单状态（0待处理1已回复2已完结）', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'status' => array('type' => 'integer', 'description' => '目标状态'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'status', 'confirm')),
            $this->fn('delete_workorder', '删除工单', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_ai_cs', '查询前台 AI 转人工客服工单', array(
                'status' => array('type' => 'integer', 'description' => '0待处理1已回复2已完结，可空'),
                'keyword' => array('type' => 'string', 'description' => '联系方式/订单号/问题关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('get_ai_cs', '查看 AI 转人工工单详情', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
            ), array('id')),
            $this->fn('reply_ai_cs', '回复或完结 AI 转人工工单', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'reply' => array('type' => 'string', 'description' => '回复内容'),
                'close' => array('type' => 'boolean', 'description' => 'true 则完结'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'reply', 'confirm')),
            $this->fn('change_shopname', '批量替换商品名称中的文字（全局 replace）', array(
                'old_name' => array('type' => 'string', 'description' => '原文字'),
                'new_name' => array('type' => 'string', 'description' => '新文字'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('old_name', 'new_name', 'confirm')),
            $this->fn('change_inputs', '批量替换商品输入框名称（input/inputs 字段）', array(
                'old_name' => array('type' => 'string', 'description' => '原文字'),
                'new_name' => array('type' => 'string', 'description' => '新文字'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('old_name', 'new_name', 'confirm')),
            $this->fn('reset_goods_sort', '按商品ID重置某分类下排序', array(
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('cid', 'confirm')),
            $this->fn('set_site_price', '设置分站对某商品的单独售价（分站加价）', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID'),
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
                'price' => array('type' => 'number', 'description' => '单独售价，传0表示取消该商品单独价'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('zid', 'tid', 'price', 'confirm')),
            $this->fn('clear_site_price', '清空某分站全部单独加价', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('zid', 'confirm')),
            $this->fn('delete_site', '删除分站（高危不可恢复）', array(
                'zid' => array('type' => 'integer', 'description' => '分站ID，不能删主站'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('zid', 'confirm')),
            $this->fn('list_users', '列出普通用户（power=0 的站点账号）', array(
                'keyword' => array('type' => 'string', 'description' => '用户名/QQ关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('list_invite_records', '查询推广链接记录（pre_invite）', array(
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('delete_invite_record', '删除推广链接记录', array(
                'id' => array('type' => 'integer', 'description' => '记录ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_dock_logs', '查询社区对接日志', array(
                'keyword' => array('type' => 'string', 'description' => '关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('list_rank', '查询分站销量排行（只读）', array(
                'days' => array('type' => 'integer', 'description' => '统计天数，默认1表示今天；传0表示昨日'),
                'limit' => array('type' => 'integer', 'description' => '默认10'),
            )),
            $this->fn('create_fanghong_url', '生成防红短链（需已配置 fanghong_*）', array(
                'url' => array('type' => 'string', 'description' => '原始长链接'),
                'force' => array('type' => 'boolean', 'description' => 'true强制换接口重试'),
            ), array('url')),
            $this->fn('export_orders', '导出订单为文本（最多500行）。status默认0未处理；mark_status可选导出后改状态', array(
                'cid' => array('type' => 'integer', 'description' => '分类ID，与tid二选一或都空表示条件放宽'),
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
                'status' => array('type' => 'integer', 'description' => '订单状态，默认0'),
                'starttime' => array('type' => 'string', 'description' => '开始日期 YYYY-MM-DD'),
                'endtime' => array('type' => 'string', 'description' => '结束日期 YYYY-MM-DD'),
                'mark_status' => array('type' => 'integer', 'description' => '导出后改为此状态，不传则不改'),
                'limit' => array('type' => 'integer', 'description' => '最多行数默认200最大500'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('confirm')),
            $this->fn('delete_shequ', '删除对接货源站点', array(
                'id' => array('type' => 'integer', 'description' => '对接站ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('batch_sync_dock_goods', '从同系统货源批量同步商品到本地分类（最多80个）。goods_ids为货源商品tid数组', array(
                'shequ_id' => array('type' => 'integer', 'description' => '对接站ID'),
                'cid' => array('type' => 'integer', 'description' => '本地分类ID'),
                'prid' => array('type' => 'integer', 'description' => '加价模板ID，默认0'),
                'goods_ids' => array('type' => 'array', 'description' => '货源商品ID数组', 'items' => array('type' => 'integer')),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('shequ_id', 'cid', 'goods_ids', 'confirm')),
            $this->fn('list_faka', '查询发卡库存', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('add_faka', '批量导入发卡卡密，每行一条；可用空格分隔卡号与密码', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
                'kms' => array('type' => 'string', 'description' => '多行卡密文本'),
                'split' => array('type' => 'string', 'description' => '卡号与密码分隔符，空则按空格'),
                'check_repeat' => array('type' => 'boolean', 'description' => 'true则跳过已存在卡号'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('tid', 'kms', 'confirm')),
            $this->fn('delete_faka', '删除发卡卡密。action=kid 按ID删；action=tid_all 清空该商品；action=tid_used 清空该商品已售', array(
                'action' => array('type' => 'string', 'description' => 'kid|tid_all|tid_used'),
                'kid' => array('type' => 'integer', 'description' => '卡密ID，action=kid 时必填'),
                'tid' => array('type' => 'integer', 'description' => '商品ID，tid_all/tid_used 时必填'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('action', 'confirm')),
            $this->fn('list_kms', '查询卡密。type:1兑换卡密0加款卡密', array(
                'type' => array('type' => 'integer', 'description' => '1兑换0加款，可空'),
                'tid' => array('type' => 'integer', 'description' => '兑换卡对应商品ID，可空'),
                'status' => array('type' => 'integer', 'description' => '0未用1已用，可空'),
                'keyword' => array('type' => 'string', 'description' => '卡密关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认30'),
            )),
            $this->fn('generate_kms', '批量生成卡密。type=1兑换卡需tid与num(购买份数)；type=0加款卡需money金额', array(
                'type' => array('type' => 'integer', 'description' => '1兑换卡0加款卡'),
                'count' => array('type' => 'integer', 'description' => '生成数量，默认10，最大200'),
                'tid' => array('type' => 'integer', 'description' => '兑换卡商品ID'),
                'num' => array('type' => 'integer', 'description' => '兑换卡购买份数，默认1'),
                'money' => array('type' => 'number', 'description' => '加款卡面额'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('type', 'confirm')),
            $this->fn('delete_kms', '删除卡密。action=kid按ID；action=type_all清空该类型；action=type_used清空该类型已使用', array(
                'action' => array('type' => 'string', 'description' => 'kid|type_all|type_used'),
                'kid' => array('type' => 'integer', 'description' => '卡密ID'),
                'type' => array('type' => 'integer', 'description' => '0加款1兑换，清空时必填'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('action', 'confirm')),
            $this->fn('list_articles', '查询文章', array(
                'keyword' => array('type' => 'string', 'description' => '标题关键词'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('get_article', '获取文章详情', array(
                'id' => array('type' => 'integer', 'description' => '文章ID'),
            ), array('id')),
            $this->fn('save_article', '新增或修改文章；传 id 为修改', array(
                'id' => array('type' => 'integer', 'description' => '文章ID，更新时必填'),
                'title' => array('type' => 'string', 'description' => '标题'),
                'content' => array('type' => 'string', 'description' => '正文HTML/文本'),
                'keywords' => array('type' => 'string', 'description' => '关键词'),
                'description' => array('type' => 'string', 'description' => '摘要'),
                'active' => array('type' => 'integer', 'description' => '1显示0隐藏'),
                'top' => array('type' => 'integer', 'description' => '1置顶0否'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('title', 'content', 'confirm')),
            $this->fn('delete_article', '删除文章', array(
                'id' => array('type' => 'integer', 'description' => '文章ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
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
            $this->fn('save_message', '发布或修改站内通知；传 id 为修改。type:0全部1普通用户2所有站长3普及版4专业版', array(
                'id' => array('type' => 'integer', 'description' => '通知ID，更新时必填'),
                'title' => array('type' => 'string', 'description' => '标题'),
                'content' => array('type' => 'string', 'description' => '内容'),
                'type' => array('type' => 'integer', 'description' => '接收类别0-4，默认0'),
                'active' => array('type' => 'integer', 'description' => '1启用0停用，默认1'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('title', 'content', 'confirm')),
            $this->fn('delete_message', '删除站内通知', array(
                'id' => array('type' => 'integer', 'description' => '通知ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_price_rules', '列出加价模板', array()),
            $this->fn('save_price_rule', '新增或修改加价模板；传 id 为修改。kind:0倍数1固定金额。须满足专业版加价<=普及版<=普通用户', array(
                'id' => array('type' => 'integer', 'description' => '模板ID，更新时必填'),
                'name' => array('type' => 'string', 'description' => '模板名称'),
                'kind' => array('type' => 'integer', 'description' => '0倍数1固定金额'),
                'p_2' => array('type' => 'number', 'description' => '专业版加价'),
                'p_1' => array('type' => 'number', 'description' => '普及版加价'),
                'p_0' => array('type' => 'number', 'description' => '普通用户加价'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('name', 'p_2', 'p_1', 'p_0', 'confirm')),
            $this->fn('delete_price_rule', '删除加价模板', array(
                'id' => array('type' => 'integer', 'description' => '模板ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_gifts', '列出抽奖奖品', array()),
            $this->fn('save_gift', '新增或修改抽奖奖品；传 id 为修改', array(
                'id' => array('type' => 'integer', 'description' => '奖品ID，更新时必填'),
                'name' => array('type' => 'string', 'description' => '奖品名称'),
                'tid' => array('type' => 'integer', 'description' => '对应商品ID'),
                'rate' => array('type' => 'number', 'description' => '中奖几率百分比数字，如 10 表示10%'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('name', 'tid', 'rate', 'confirm')),
            $this->fn('delete_gift', '删除抽奖奖品', array(
                'id' => array('type' => 'integer', 'description' => '奖品ID'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('id', 'confirm')),
            $this->fn('list_templates', '列出可用前台模板目录名', array()),
            $this->fn('setup_site', '设置站点品牌与展示文案。公告类字段请按用户要求写完整 HTML（可含内联样式），勿套固定通用模板；改已有公告前宜先 get_config', array(
                'sitename' => array('type' => 'string', 'description' => '站点对外名称，完全由用户决定，如 XX下单平台 / XX网'),
                'title' => array('type' => 'string', 'description' => '浏览器标题，可选，默认可与站名相同'),
                'keywords' => array('type' => 'string', 'description' => 'SEO关键词'),
                'description' => array('type' => 'string', 'description' => '站点简介'),
                'kfqq' => array('type' => 'string', 'description' => '客服QQ'),
                'anounce' => array('type' => 'string', 'description' => '首页公告完整 HTML。按用户风格/文案/布局来写，可内联 CSS；禁止无关万能模板；不要 script/on*'),
                'modal' => array('type' => 'string', 'description' => '弹窗公告完整 HTML，规则同 anounce'),
                'gg_search' => array('type' => 'string', 'description' => '查询页提示，可为 HTML'),
                'footer' => array('type' => 'string', 'description' => '底部信息，可为 HTML'),
                'assistant_name' => array('type' => 'string', 'description' => 'AI助手对外称呼，用户自定义'),
                'template' => array('type' => 'string', 'description' => '前台模板目录名，先用 list_templates 查看'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('confirm')),
            $this->fn('config_catalog', '列出系统设置分组：哪些配置 AI 可改、哪些因密钥/上传/高危不可改（优先用此了解设置能力）', array(
                'group' => array('type' => 'string', 'description' => '可选：site/fenzhan/gonggao/mail/pay/template/oauth/captcha/logo/cron/clean/proxy/qiandao/invite/choujiang/fanghong/app/ai；空=全部'),
            )),
            $this->fn('clean_data', '系统数据清理（与后台清理页一致）。高危，必须 confirm=true', array(
                'action' => array('type' => 'string', 'description' => 'cleancache|cleanlog|cleanpay|cleanorders|cleanqiandao|cleanwork|cleanpoints|cleangift|cleaninvite|cleanpayi|cleanordersi|cleansite'),
                'days' => array('type' => 'integer', 'description' => '自定义清理天数，仅 cleanpayi/cleanordersi/cleansite 需要'),
                'money' => array('type' => 'number', 'description' => '金额上限，仅自定义清理需要'),
                'confirm' => array('type' => 'boolean', 'description' => '必须 true'),
            ), array('action', 'confirm')),
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
        if (!$this->isToolAllowed($name)) {
            return array('ok' => false, 'error' => '当前角色无权调用工具：' . $name);
        }
        try {
            switch ($name) {
                case 'shop_list_classes':
                    return $this->shopListClasses($args);
                case 'shop_search_goods':
                    return $this->shopSearchGoods($args);
                case 'shop_get_goods':
                    return $this->shopGetGoods($args);
                case 'shop_recommend_goods':
                    return $this->shopRecommendGoods($args);
                case 'shop_explain_goods':
                    return $this->shopExplainGoods($args);
                case 'shop_site_faq':
                    return $this->shopSiteFaq($args);
                case 'shop_query_order':
                    return $this->shopQueryOrder($args);
                case 'shop_aftersale_help':
                    return $this->shopAftersaleHelp($args);
                case 'shop_transfer_human':
                    return $this->shopTransferHuman($args);
                case 'list_ai_cs':
                    return $this->listAiCs($args);
                case 'get_ai_cs':
                    return $this->getAiCs($args);
                case 'reply_ai_cs':
                    return $this->replyAiCs($args);
                case 'user_my_balance':
                    return $this->userMyBalance();
                case 'user_my_orders':
                    return $this->userMyOrders($args);
                case 'user_get_order':
                    return $this->userGetOrder($args);
                case 'user_my_workorders':
                    return $this->userMyWorkorders($args);
                case 'user_get_workorder':
                    return $this->userGetWorkorder($args);
                case 'user_create_workorder':
                    return $this->userCreateWorkorder($args);
                case 'user_reply_workorder':
                    return $this->userReplyWorkorder($args);
                case 'user_site_info':
                    return $this->userSiteInfo();
                case 'user_update_site':
                    return $this->userUpdateSite($args);
                case 'user_list_goods':
                    return $this->shopSearchGoods($args);
                case 'user_list_classes':
                    return $this->shopListClasses($args);
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
                case 'apply_price_rule':
                    return $this->applyPriceRule($args);
                case 'batch_set_stock':
                    return $this->batchSetStock($args);
                case 'delete_goods':
                    return $this->deleteGoods($args);
                case 'copy_goods':
                    return $this->copyGoods($args);
                case 'move_goods':
                    return $this->moveGoods($args);
                case 'list_classes':
                    return $this->listClasses($args);
                case 'save_class':
                    return $this->saveClass($args);
                case 'delete_class':
                    return $this->deleteClass($args);
                case 'list_sites':
                    return $this->listSites($args);
                case 'set_site':
                    return $this->setSite($args);
                case 'site_recharge':
                    return $this->siteRecharge($args);
                case 'extend_site':
                    return $this->extendSite($args);
                case 'list_money_records':
                    return $this->listMoneyRecords($args);
                case 'list_invite_shops':
                    return $this->listInviteShops();
                case 'save_invite_shop':
                    return $this->saveInviteShop($args);
                case 'delete_invite_shop':
                    return $this->deleteInviteShop($args);
                case 'list_invite_logs':
                    return $this->listInviteLogs($args);
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
                case 'get_workorder':
                    return $this->getWorkorder($args);
                case 'reply_workorder':
                    return $this->replyWorkorder($args);
                case 'set_workorder_status':
                    return $this->setWorkorderStatus($args);
                case 'delete_workorder':
                    return $this->deleteWorkorder($args);
                case 'change_shopname':
                    return $this->changeShopname($args);
                case 'change_inputs':
                    return $this->changeInputs($args);
                case 'reset_goods_sort':
                    return $this->resetGoodsSort($args);
                case 'set_site_price':
                    return $this->setSitePrice($args);
                case 'clear_site_price':
                    return $this->clearSitePrice($args);
                case 'delete_site':
                    return $this->deleteSite($args);
                case 'list_users':
                    return $this->listUsers($args);
                case 'list_invite_records':
                    return $this->listInviteRecords($args);
                case 'delete_invite_record':
                    return $this->deleteInviteRecord($args);
                case 'list_dock_logs':
                    return $this->listDockLogs($args);
                case 'list_rank':
                    return $this->listRank($args);
                case 'create_fanghong_url':
                    return $this->createFanghongUrl($args);
                case 'export_orders':
                    return $this->exportOrders($args);
                case 'delete_shequ':
                    return $this->deleteShequ($args);
                case 'batch_sync_dock_goods':
                    return $this->batchSyncDockGoods($args);
                case 'list_faka':
                    return $this->listFaka($args);
                case 'add_faka':
                    return $this->addFaka($args);
                case 'delete_faka':
                    return $this->deleteFaka($args);
                case 'list_kms':
                    return $this->listKms($args);
                case 'generate_kms':
                    return $this->generateKms($args);
                case 'delete_kms':
                    return $this->deleteKms($args);
                case 'list_articles':
                    return $this->listArticles($args);
                case 'get_article':
                    return $this->getArticle($args);
                case 'save_article':
                    return $this->saveArticle($args);
                case 'delete_article':
                    return $this->deleteArticle($args);
                case 'list_tixian':
                    return $this->listTixian($args);
                case 'set_tixian_status':
                    return $this->setTixianStatus($args);
                case 'list_messages':
                    return $this->listMessages($args);
                case 'save_message':
                    return $this->saveMessage($args);
                case 'delete_message':
                    return $this->deleteMessage($args);
                case 'list_price_rules':
                    return $this->listPriceRules();
                case 'save_price_rule':
                    return $this->savePriceRule($args);
                case 'delete_price_rule':
                    return $this->deletePriceRule($args);
                case 'list_gifts':
                    return $this->listGifts();
                case 'save_gift':
                    return $this->saveGift($args);
                case 'delete_gift':
                    return $this->deleteGift($args);
                case 'list_templates':
                    return $this->listTemplates();
                case 'setup_site':
                    return $this->setupSite($args);
                case 'config_catalog':
                    return $this->configCatalog($args);
                case 'clean_data':
                    return $this->cleanData($args);
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
        $allowNum = array('price', 'cost', 'cost2');
        $allowInt = array('cid', 'prid', 'stock', 'active', 'close', 'shequ', 'is_curl', 'value', 'multi', 'min', 'max', 'sort', 'validate');
        $allowStr = array('name', 'desc', 'alert', 'input', 'inputs', 'goods_id', 'shopimg', 'goods_param', 'valiserv', 'prices');
        $sets = array();
        if (!empty($args['null_stock'])) {
            $sets[] = "`stock`=NULL";
        }
        foreach ($allowNum as $k) {
            if (!array_key_exists($k, $args)) continue;
            $sets[] = "`$k`='" . addslashes(strval($args[$k])) . "'";
        }
        foreach ($allowInt as $k) {
            if (!array_key_exists($k, $args)) continue;
            if ($k === 'stock' && !empty($args['null_stock'])) continue;
            $sets[] = "`$k`='" . intval($args[$k]) . "'";
        }
        foreach ($allowStr as $k) {
            if (!array_key_exists($k, $args)) continue;
            $sets[] = "`$k`='" . addslashes(strval($args[$k])) . "'";
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
        $shopimg = isset($args['shopimg']) ? $args['shopimg'] : '';
        $prid = isset($args['prid']) ? intval($args['prid']) : 0;
        $active = isset($args['active']) ? intval($args['active']) : 1;
        $multi = isset($args['multi']) ? intval($args['multi']) : 0;
        $min = isset($args['min']) ? intval($args['min']) : 1;
        $max = isset($args['max']) ? intval($args['max']) : 1;
        $stockSql = array_key_exists('stock', $args) ? ("'" . intval($args['stock']) . "'") : 'NULL';
        $sort = intval($this->DB->getColumn("SELECT IFNULL(MAX(sort),0)+1 FROM pre_tools WHERE cid='$cid'"));
        $sql = "INSERT INTO pre_tools (cid,name,price,cost,cost2,prid,input,inputs,`desc`,shopimg,value,is_curl,shequ,goods_id,stock,multi,min,max,sort,active,close) VALUES (
            '$cid','" . addslashes($name) . "','$price','$price','$price','$prid','" . addslashes($input) . "','" . addslashes($inputs) . "','" . addslashes($desc) . "','" . addslashes($shopimg) . "',1,'$is_curl','$shequ','" . addslashes($goods_id) . "',$stockSql,'$multi','$min','$max','$sort','$active',0)";
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

    private function normalizeIdList($list)
    {
        $out = array();
        if (!is_array($list)) return $out;
        foreach ($list as $v) {
            $id = intval($v);
            if ($id > 0) $out[] = $id;
        }
        return array_values(array_unique($out));
    }

    private function applyPriceRule($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '应用加价模板需 confirm=true');
        $prid = intval(isset($args['prid']) ? $args['prid'] : 0);
        if ($prid < 0) return array('ok' => false, 'error' => '无效 prid');
        if ($prid > 0 && !$this->DB->getRow("SELECT id FROM pre_price WHERE id='$prid' LIMIT 1")) {
            return array('ok' => false, 'error' => '加价模板不存在');
        }
        $tids = $this->normalizeIdList(isset($args['tids']) ? $args['tids'] : array());
        $cids = $this->normalizeIdList(isset($args['cids']) ? $args['cids'] : array());
        $n = 0;
        if ($tids) {
            foreach ($tids as $tid) {
                $this->DB->exec("UPDATE pre_tools SET prid='$prid',`cost`='0',`cost2`='0' WHERE tid='$tid' LIMIT 1");
                $n++;
            }
            return array('ok' => true, 'msg' => "已为 {$n} 个商品挂上模板 $prid", 'count' => $n);
        }
        if ($cids) {
            $in = implode(',', $cids);
            $count = $this->DB->exec("UPDATE pre_tools SET prid='$prid' WHERE cid IN ($in) AND price>0");
            return array('ok' => true, 'msg' => '已按分类更新加价模板', 'count' => intval($count));
        }
        return array('ok' => false, 'error' => '请提供 tids 或 cids');
    }

    private function batchSetStock($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '批量设库存需 confirm=true');
        $tids = $this->normalizeIdList(isset($args['tids']) ? $args['tids'] : array());
        if (!$tids) return array('ok' => false, 'error' => 'tids 不能为空');
        $num = array_key_exists('stock', $args) ? trim(strval($args['stock'])) : '';
        $n = 0;
        foreach ($tids as $tid) {
            if ($num === '') {
                $this->DB->exec("UPDATE pre_tools SET stock=NULL WHERE tid='$tid' AND is_curl!=4");
            } else {
                $this->DB->exec("UPDATE pre_tools SET stock='" . intval($num) . "' WHERE tid='$tid' AND is_curl!=4");
            }
            $n++;
        }
        return array('ok' => true, 'msg' => "已更新 {$n} 个商品库存", 'count' => $n);
    }

    private function deleteGoods($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除商品需 confirm=true');
        $tids = $this->normalizeIdList(isset($args['tids']) ? $args['tids'] : array());
        if (!$tids) return array('ok' => false, 'error' => 'tids 不能为空');
        $n = 0;
        foreach ($tids as $tid) {
            if ($this->DB->exec("DELETE FROM pre_tools WHERE tid='$tid' LIMIT 1") !== false) {
                $this->DB->exec("DELETE FROM pre_orders WHERE tid='$tid'");
                $n++;
            }
        }
        return array('ok' => true, 'msg' => "已删除 {$n} 个商品及其订单", 'count' => $n);
    }

    private function copyGoods($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '复制商品需 confirm=true');
        $tids = $this->normalizeIdList(isset($args['tids']) ? $args['tids'] : array());
        if (!$tids) return array('ok' => false, 'error' => 'tids 不能为空');
        $n = 0;
        $newIds = array();
        foreach ($tids as $tid) {
            $ok = $this->DB->exec("INSERT INTO `pre_tools` (`cid`,`name`,`price`,`cost`,`cost2`,`prid`,`prices`,`input`,`inputs`,`desc`,`alert`,`shopimg`,`value`,`is_curl`,`curl`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`repeat`,`multi`,`min`,`max`,`validate`,`valiserv`,`sort`,`active`,`stock`,`close`) SELECT `cid`,`name`,`price`,`cost`,`cost2`,`prid`,`prices`,`input`,`inputs`,`desc`,`alert`,`shopimg`,`value`,`is_curl`,`curl`,`shequ`,`goods_id`,`goods_type`,`goods_param`,`repeat`,`multi`,`min`,`max`,`validate`,`valiserv`,`sort`,`active`,`stock`,`close` FROM `pre_tools` WHERE `tid`='$tid'");
            if ($ok !== false) {
                $n++;
                $newIds[] = intval($this->DB->lastInsertId());
            }
        }
        return array('ok' => true, 'msg' => "已复制 {$n} 个商品", 'count' => $n, 'new_tids' => $newIds);
    }

    private function moveGoods($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '移动商品需 confirm=true');
        $cid = intval(isset($args['cid']) ? $args['cid'] : 0);
        $tids = $this->normalizeIdList(isset($args['tids']) ? $args['tids'] : array());
        if ($cid <= 0 || !$tids) return array('ok' => false, 'error' => 'cid 与 tids 必填');
        if (!$this->DB->getRow("SELECT cid FROM pre_class WHERE cid='$cid' LIMIT 1")) {
            return array('ok' => false, 'error' => '目标分类不存在');
        }
        $n = 0;
        foreach ($tids as $tid) {
            $this->DB->exec("UPDATE pre_tools SET cid='$cid' WHERE tid='$tid' LIMIT 1");
            $n++;
        }
        return array('ok' => true, 'msg' => "已移动 {$n} 个商品到分类 $cid", 'count' => $n);
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

    private function deleteClass($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除分类需 confirm=true');
        $cid = intval(isset($args['cid']) ? $args['cid'] : 0);
        if ($cid <= 0) return array('ok' => false, 'error' => '无效分类ID');
        $this->DB->exec("DELETE FROM pre_tools WHERE cid='$cid'");
        $ok = $this->DB->exec("DELETE FROM pre_class WHERE cid='$cid'");
        return array('ok' => $ok !== false, 'msg' => '分类及其商品已删除', 'cid' => $cid);
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

    private function extendSite($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '续期需 confirm=true');
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        $months = isset($args['months']) ? intval($args['months']) : 1;
        if ($zid <= 1) return array('ok' => false, 'error' => '不能操作主站或无效 zid');
        if ($months <= 0 || $months > 120) return array('ok' => false, 'error' => 'months 需在 1-120');
        $row = $this->DB->getRow("SELECT zid,endtime FROM pre_site WHERE zid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '分站不存在');
        $base = (!empty($row['endtime']) && $row['endtime'] > date('Y-m-d')) ? $row['endtime'] : date('Y-m-d');
        $endtime = date('Y-m-d', strtotime('+' . $months . ' months', strtotime($base)));
        $ok = $this->DB->exec("UPDATE pre_site SET endtime='$endtime' WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'msg' => '已续期', 'zid' => $zid, 'endtime' => $endtime);
    }

    private function listMoneyRecords($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (!empty($args['zid'])) $where .= ' AND zid=' . intval($args['zid']);
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (action LIKE '%$kw%' OR bz LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT id,zid,action,point,bz,addtime,orderid,status FROM pre_points WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listInviteShops()
    {
        $rows = $this->DB->getAll("SELECT A.*,B.name AS shopname FROM pre_inviteshop A LEFT JOIN pre_tools B ON A.tid=B.tid ORDER BY A.sort ASC, A.id ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function saveInviteShop($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存推广商品需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        $value = isset($args['value']) ? floatval($args['value']) : 0;
        if ($tid <= 0 || $value <= 0) return array('ok' => false, 'error' => 'tid 与 value 必填且 value>0');
        if (!$this->DB->getRow("SELECT tid FROM pre_tools WHERE tid='$tid' LIMIT 1")) {
            return array('ok' => false, 'error' => '奖励商品不存在');
        }
        $type = isset($args['type']) ? intval($args['type']) : 0;
        if ($type !== 0 && $type !== 1) $type = 0;
        $times = isset($args['times']) ? intval($args['times']) : 0;
        if ($times !== 0 && $times !== 1) $times = 0;
        $sort = isset($args['sort']) ? intval($args['sort']) : 10;
        $active = array_key_exists('active', $args) ? intval($args['active']) : 1;
        if ($id > 0) {
            if (!$this->DB->getRow("SELECT id FROM pre_inviteshop WHERE id='$id' LIMIT 1")) {
                return array('ok' => false, 'error' => '推广配置不存在');
            }
            $ok = $this->DB->exec("UPDATE pre_inviteshop SET tid='$tid',type='$type',times='$times',value='$value',sort='$sort',active='$active' WHERE id='$id'");
            return array('ok' => $ok !== false, 'msg' => '推广商品已更新', 'id' => $id);
        }
        $date = date('Y-m-d H:i:s');
        $ok = $this->DB->exec("INSERT INTO pre_inviteshop (tid,type,times,value,sort,addtime,active) VALUES ('$tid','$type','$times','$value','$sort','$date','$active')");
        if ($ok === false) return array('ok' => false, 'error' => '添加失败：' . $this->DB->error());
        return array('ok' => true, 'msg' => '推广商品已添加', 'id' => intval($this->DB->lastInsertId()));
    }

    private function deleteInviteShop($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除推广商品需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效ID');
        $ok = $this->DB->exec("DELETE FROM pre_inviteshop WHERE id='$id' LIMIT 1");
        return array('ok' => $ok !== false, 'msg' => '推广商品已删除', 'id' => $id);
    }

    private function listInviteLogs($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $rows = $this->DB->getAll("SELECT id,iid,type,date,ip,orderid,status FROM pre_invitelog ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function configWhitelist()
    {
        return array(
            // 网站信息
            'sitename', 'title', 'keywords', 'description', 'kfqq', 'kfwx', 'kaurl',
            'appurl', 'daiguaurl', 'musicurl', 'style', 'cdnpublic', 'staticurl',
            'verify_open', 'user_open', 'gift_open', 'search_open', 'shoppingcart',
            'workorder_open', 'workorder_type', 'workorder_pic', 'shopdesc_editor',
            'forcelogin', 'forceloginhome', 'forcermb', 'iskami', 'selfrefund',
            'openbatchorder', 'queryorderlimit', 'show_changepwd', 'show_complain',
            'hide_tongji', 'tongji_time', 'articlenum', 'classblock', 'blacklist',
            'defaultcid', 'sitename_hide', 'user_level', 'ordername',
            'faka_input', 'faka_showleft',
            // 公告
            'anounce', 'modal', 'gg_search', 'paymsg', 'footer', 'bottom', 'chatframe',
            'gg_announce', 'gg_panel',
            // 分站
            'fenzhan_buy', 'fenzhan_price', 'fenzhan_price2', 'fenzhan_free', 'fenzhan_rank',
            'fenzhan_expiry', 'fenzhan_tixian', 'fenzhan_tixian_alipay', 'fenzhan_tixian_wx', 'fenzhan_tixian_qq',
            'fenzhan_pricelimit', 'fenzhan_kfqq', 'fenzhan_edithtml',
            'fenzhan_domain', 'fenzhan_remain', 'fenzhan_cost', 'fenzhan_cost2', 'fenzhan_upgrade',
            'fenzhan_adds', 'fenzhan_html', 'fenzhan_template', 'fenzhan_editd', 'fenzhan_page',
            'fenzhan_gift', 'fenzhan_skimg', 'fenzhan_jiakuanka', 'fenzhan_regalert', 'fenzhan_regrand',
            'fenzhan_recharge', 'fenzhan_workorder', 'fenzhan_daifu',
            'tixian_rate', 'tixian_min', 'tixian_limit', 'tixian_days', 'recharge_min',
            // 邮箱与提醒（不含密钥）
            'mail_smtp', 'mail_port', 'mail_name', 'mail_name2', 'mail_recv', 'mail_cloud', 'mail_apiuser',
            'message_type', 'message_buy', 'message_duijie', 'message_fakastock', 'message_tixian', 'message_workorder',
            'faka_mail',
            // 支付通道开关 / 接口选择（不含商户密钥）
            'alipay_api', 'qqpay_api', 'wxpay_api',
            'payapi', 'payapi2', 'payapi3', 'micropayapi',
            'epay_url', 'epay_url2', 'epay_url3',
            // 首页模板 / UI
            'template', 'template_m', 'ui_background', 'ui_bing',
            'ui_color1', 'ui_color2', 'ui_colorto', 'ui_shop', 'ui_user',
            'template_about', 'template_help', 'template_style', 'template_showprice',
            'template_showsales', 'template_virtualdata', 'template_bgopen',
            'template_label_auto', 'template_label_manual', 'index_class_num_style',
            // 快捷登录开关（不含 appkey）
            'thirdlogin_open', 'thirdlogin_closepwd', 'login_qq', 'login_wx', 'login_apiurl', 'login_appid',
            // 验证码开关 / IP（不含 captcha_key）
            'captcha_open', 'captcha_open_free', 'captcha_open_reg', 'captcha_open_login', 'captcha_id',
            'ip_type', 'defend',
            // 计划任务 / 价格监控（不含 cronkey）
            'updatestatus', 'updatestatus_interval',
            'pricejk_cid', 'pricejk_edit', 'pricejk_time', 'pricejk_yile',
            // 代理（不含密码，密码见 secret 说明）
            'proxy', 'proxy_server', 'proxy_port', 'proxy_user', 'proxy_type',
            // 签到 / 推广 / 抽奖 / 防红 / 排行 / APP / 文章
            'qiandao_reward', 'qiandao_mult', 'qiandao_day', 'qiandao_limitip',
            'invite_content', 'invite_tid', 'invite_name',
            'cjcishu', 'cjmsg', 'cjmoney', 'gift_log',
            'fanghong_api', 'fanghong_type', 'fanghong_url', 'fanghong_diy',
            'rank_reward', 'rank_percentage', 'article_rewrite',
            'appcreate_open', 'appcreate_price', 'appcreate_price2', 'appcreate_diy',
            'appcreate_theme', 'appcreate_nonav', 'appcreate_default', 'appcreate_source',
            // 对接对外 key（非管理员密码）
            'apikey',
            // AI 非密钥
            'ai_enabled', 'ai_provider', 'ai_api_base', 'ai_model',
            'ai_assistant_name', 'ai_system_prompt', 'ai_max_tokens', 'ai_temperature',
            'ai_session_limit', 'ai_message_limit',
        );
    }

    private function secretKeys()
    {
        return array(
            'admin_pwd', 'admin_user', 'syskey', 'cronkey',
            'mail_pwd', 'mail_apikey',
            'ai_api_key', 'ai_api_token',
            'proxy_pwd',
            'login_appkey',
            'captcha_key',
            'appcreate_key',
            'alipay_key', 'alipay_privatekey', 'alipay_publickey', 'alipay_pid', 'alipay_appid',
            'wxpay_key', 'wxpay_appid', 'wxpay_mchid', 'wxpay_appsecret',
            'qqpay_key', 'qqpay_mchid',
            'epay_pid', 'epay_key', 'epay_pid2', 'epay_key2', 'epay_pid3', 'epay_key3',
            'codepay_id', 'codepay_key',
            'transfer_id', 'transfer_key',
            'thirdlogin_qq', 'thirdlogin_wx',
        );
    }

    private function refreshConf()
    {
        if ($this->CACHE) {
            $this->CACHE->clear();
            $this->conf = $this->CACHE->update();
        }
    }

    private function getConfig($args)
    {
        $keys = isset($args['keys']) && is_array($args['keys']) && $args['keys'] ? $args['keys'] : array(
            'sitename', 'title', 'keywords', 'description', 'kfqq', 'template', 'template_m',
            'anounce', 'modal', 'gg_search', 'gg_panel', 'footer',
            'verify_open', 'fenzhan_buy', 'fenzhan_price', 'fenzhan_price2',
            'alipay_api', 'wxpay_api', 'qqpay_api',
            'mail_smtp', 'mail_port', 'mail_name', 'message_type',
            'thirdlogin_open', 'captcha_open', 'captcha_open_login', 'ip_type',
            'proxy', 'proxy_server', 'proxy_port', 'proxy_type',
            'updatestatus', 'updatestatus_interval',
            'ui_background', 'ai_assistant_name', 'ai_enabled', 'ai_model', 'ai_api_base'
        );
        $secret = $this->secretKeys();
        $out = array();
        foreach ($keys as $k) {
            $k = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
            if ($k === '') continue;
            if (in_array($k, $secret, true)) {
                $out[$k] = isset($this->conf[$k]) && $this->conf[$k] !== '' ? '(已设置，密钥不可通过AI读取)' : '(未设置，请在后台页面填写)';
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
        if (isset($items['assistant_name']) && !isset($items['ai_assistant_name'])) {
            $items['ai_assistant_name'] = $items['assistant_name'];
            unset($items['assistant_name']);
        }
        // 兼容旧抽奖键名
        if (isset($items['cishu']) && !isset($items['cjcishu'])) {
            $items['cjcishu'] = $items['cishu'];
            unset($items['cishu']);
        }
        $allow = $this->configWhitelist();
        $secret = $this->secretKeys();
        $changed = array();
        $blocked = array();
        foreach ($items as $k => $v) {
            $k = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
            if ($k === '') continue;
            if (in_array($k, $secret, true)) {
                $blocked[] = $k;
                continue;
            }
            if (!in_array($k, $allow, true)) {
                $blocked[] = $k;
                continue;
            }
            if (!function_exists('saveSetting')) {
                return array('ok' => false, 'error' => 'saveSetting 不可用');
            }
            saveSetting($k, is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            $changed[] = $k;
        }
        $this->refreshConf();
        if (!$changed) {
            return array(
                'ok' => false,
                'error' => '没有可保存项。密钥类请在后台页面填写（工具日志会明文记录参数，不能走AI）。',
                'blocked' => $blocked,
            );
        }
        return array(
            'ok' => true,
            'changed' => $changed,
            'blocked' => $blocked,
            'sitename' => isset($this->conf['sitename']) ? $this->conf['sitename'] : null,
            'assistant_name' => isset($this->conf['ai_assistant_name']) ? $this->conf['ai_assistant_name'] : null,
            'hint' => $blocked ? '部分键被拒绝：密钥/高危项请到后台对应设置页填写' : null,
        );
    }

    private function configCatalog($args)
    {
        $all = array(
            'site' => array(
                'name' => '网站信息配置',
                'writable' => 'sitename/title/keywords/description/kfqq/开关类(forcelogin/iskami/selfrefund等)/user_open/shoppingcart…',
                'blocked' => 'admin_user/admin_pwd/syskey（账号接管风险）',
                'ai' => '可用 update_config / setup_site',
            ),
            'fenzhan' => array(
                'name' => '分站相关配置',
                'writable' => '开通价/成本/域名后缀/提现开关费率/升级价/赠送规则/加款卡等几乎全部开关与数值',
                'blocked' => '代付商户密钥 transfer_*',
                'ai' => '可用 update_config；分站列表用 list_sites/set_site/site_recharge',
            ),
            'gonggao' => array(
                'name' => '网站公告配置',
                'writable' => 'anounce/modal/gg_search/paymsg/footer/bottom/chatframe/gg_announce/gg_panel（均可写完整HTML，按用户要求设计，先读再改）',
                'blocked' => '无',
                'ai' => 'get_config 读现有 → 按用户要求写 HTML → update_config/setup_site 保存；勿套万能模板',
            ),
            'mail' => array(
                'name' => '邮箱与提醒配置',
                'writable' => 'SMTP主机端口/发信名/收信/云发信用户/提醒开关 message_* /发卡邮件模板',
                'blocked' => 'mail_pwd、mail_apikey（密钥会进AI日志，请后台填写）',
                'ai' => '可用 update_config',
            ),
            'pay' => array(
                'name' => '支付接口配置',
                'writable' => 'alipay_api/wxpay_api/qqpay_api 通道开关；payapi* 选择；易支付网关URL',
                'blocked' => '商户号/密钥/私钥（alipay_key、epay_key、wxpay_* 等），请后台填写',
                'ai' => '可用 update_config 改开关；支付单一 list_pay_orders',
            ),
            'template' => array(
                'name' => '首页模板设置',
                'writable' => 'template/template_m/ui_* 颜色与背景模式/模板展示开关',
                'blocked' => 'Logo/客服二维码/自定义背景图片上传（需 multipart 文件）',
                'ai' => 'list_templates + update_config',
            ),
            'oauth' => array(
                'name' => '快捷登录配置',
                'writable' => '开关 thirdlogin_open/closepwd；login_qq/login_wx 模式；login_apiurl/login_appid',
                'blocked' => 'login_appkey；已绑定的 QQ/微信账号标识',
                'ai' => '可用 update_config',
            ),
            'captcha' => array(
                'name' => '验证与IP配置',
                'writable' => 'captcha_open* 开关、captcha_id、ip_type、blacklist、defend',
                'blocked' => 'captcha_key',
                'ai' => '可用 update_config',
            ),
            'logo' => array(
                'name' => 'Logo与背景设置',
                'writable' => 'ui_background 背景模式（纯色/必应等）',
                'blocked' => '图片文件上传（Logo、背景图、收款码）',
                'ai' => '仅模式；文件请后台上传',
            ),
            'cron' => array(
                'name' => '计划任务设置',
                'writable' => 'updatestatus、间隔、pricejk_* 价格监控参数',
                'blocked' => 'cronkey（泄露可被外人触发任务）；不代跑完整 cron URL',
                'ai' => '可用 update_config；cron 地址仍看后台',
            ),
            'clean' => array(
                'name' => '系统数据清理',
                'writable' => 'n/a（动作为 clean_data）',
                'blocked' => '无，但属高危不可逆删除',
                'ai' => 'clean_data，必须 confirm=true',
            ),
            'proxy' => array(
                'name' => '代理服务器设置',
                'writable' => 'proxy开关、proxy_server/port/user/type',
                'blocked' => 'proxy_pwd（请后台填写）',
                'ai' => '可用 update_config',
            ),
            'qiandao' => array(
                'name' => '签到设置',
                'writable' => 'qiandao_reward/mult/day/limitip',
                'blocked' => '无',
                'ai' => '可用 update_config',
            ),
            'invite' => array(
                'name' => '推广设置',
                'writable' => 'invite_tid/content/name',
                'blocked' => '无',
                'ai' => '可用 update_config',
            ),
            'choujiang' => array(
                'name' => '抽奖设置',
                'writable' => 'gift_open、cjcishu、cjmsg、cjmoney、gift_log',
                'blocked' => '奖品商品条目需在后台抽奖商品管理（非单一配置键）',
                'ai' => '可用 update_config',
            ),
            'fanghong' => array(
                'name' => '防红短链',
                'writable' => 'fanghong_api/type/url/diy',
                'blocked' => '无',
                'ai' => '可用 update_config',
            ),
            'app' => array(
                'name' => 'APP生成',
                'writable' => 'appcreate_open/price/diy/theme 等开关与价格',
                'blocked' => 'appcreate_key',
                'ai' => '可用 update_config',
            ),
            'ai' => array(
                'name' => 'AI模型配置',
                'writable' => '启用、provider、api_base、model、温度、助手名、提示词等',
                'blocked' => 'ai_api_key、ai_api_token',
                'ai' => '可用 update_config；密钥请在模型配置页填写',
            ),
        );
        $group = isset($args['group']) ? trim($args['group']) : '';
        if ($group !== '' && isset($all[$group])) {
            return array('ok' => true, 'data' => array($group => $all[$group]));
        }
        return array(
            'ok' => true,
            'note' => '密钥类不能走AI：工具参数会写入操作日志。图片上传需后台。其余开关/文案/数值大多可用 update_config。',
            'data' => $all,
        );
    }

    private function cleanData($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '数据清理需 confirm=true');
        $action = isset($args['action']) ? preg_replace('/[^a-z]/', '', strtolower($args['action'])) : '';
        $allowed = array(
            'cleancache', 'cleanlog', 'cleanpay', 'cleanorders', 'cleanqiandao',
            'cleanwork', 'cleanpoints', 'cleangift', 'cleaninvite',
            'cleanpayi', 'cleanordersi', 'cleansite',
        );
        if (!in_array($action, $allowed, true)) {
            return array('ok' => false, 'error' => 'action 无效', 'allowed' => $allowed);
        }
        if ($action === 'cleancache') {
            if ($this->CACHE) $this->CACHE->clear();
            if (function_exists('opcache_reset')) @opcache_reset();
            return array('ok' => true, 'msg' => '已清理系统设置缓存');
        }
        if ($action === 'cleanlog') {
            $this->DB->exec("TRUNCATE TABLE `pre_logs`");
            return array('ok' => true, 'msg' => '已清空社区对接日志');
        }
        if ($action === 'cleanpay') {
            $this->DB->exec("DELETE FROM `pre_pay` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'");
            $this->DB->exec("DELETE FROM `pre_pay` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-3 hours')) . "' and status=0");
            $this->DB->exec("DELETE FROM `pre_cart` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'");
            $this->DB->exec("DELETE FROM `pre_cart` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-12 hours')) . "' and status<2");
            $this->DB->exec("OPTIMIZE TABLE `pre_pay`");
            return array('ok' => true, 'msg' => '已删除30天前支付/购物车记录');
        }
        if ($action === 'cleanorders') {
            $this->DB->exec("DELETE FROM `pre_orders` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_orders`");
            return array('ok' => true, 'msg' => '已删除30天前订单');
        }
        if ($action === 'cleanqiandao') {
            $this->DB->exec("DELETE FROM `pre_qiandao` WHERE time<'" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_qiandao`");
            return array('ok' => true, 'msg' => '已删除30天前签到记录');
        }
        if ($action === 'cleanwork') {
            $this->DB->exec("DELETE FROM `pre_workorder` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_workorder`");
            return array('ok' => true, 'msg' => '已删除30天前工单');
        }
        if ($action === 'cleanpoints') {
            $this->DB->exec("DELETE FROM `pre_points` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-7 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_points`");
            return array('ok' => true, 'msg' => '已删除7天前收支明细');
        }
        if ($action === 'cleangift') {
            $this->DB->exec("DELETE FROM `pre_giftlog` WHERE addtime<'" . date('Y-m-d H:i:s', strtotime('-1 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_giftlog`");
            return array('ok' => true, 'msg' => '已删除1天前中奖记录');
        }
        if ($action === 'cleaninvite') {
            $this->DB->exec("DELETE FROM `pre_invitelog` WHERE date<'" . date('Y-m-d H:i:s', strtotime('-1 days')) . "'");
            $this->DB->exec("OPTIMIZE TABLE `pre_invitelog`");
            return array('ok' => true, 'msg' => '已删除1天前推广记录');
        }
        $days = isset($args['days']) ? intval($args['days']) : 0;
        $money = isset($args['money']) ? floatval($args['money']) : null;
        if ($days <= 0 || $money === null) {
            return array('ok' => false, 'error' => '自定义清理需要 days>0 和 money');
        }
        $before = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $moneySql = addslashes(strval($money));
        if ($action === 'cleanpayi') {
            $this->DB->exec("DELETE FROM `pre_pay` WHERE money<='$moneySql' and addtime<'$before'");
            $this->DB->exec("OPTIMIZE TABLE `pre_pay`");
            return array('ok' => true, 'msg' => "已删除{$days}天前金额≤{$money}的支付记录");
        }
        if ($action === 'cleanordersi') {
            $this->DB->exec("DELETE FROM `pre_orders` WHERE money<='$moneySql' and addtime<'$before'");
            $this->DB->exec("OPTIMIZE TABLE `pre_orders`");
            return array('ok' => true, 'msg' => "已删除{$days}天前金额≤{$money}的订单");
        }
        if ($action === 'cleansite') {
            $this->DB->exec("DELETE FROM `pre_site` WHERE rmb<='$moneySql' and addtime<'$before' and (lasttime<'$before' or lasttime is null)");
            return array('ok' => true, 'msg' => "已删除长期未登录且余额≤{$money}的分站");
        }
        return array('ok' => false, 'error' => '未执行');
    }

    private function setupSite($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '设置站点需 confirm=true');
        $map = array(
            'sitename' => 'sitename',
            'title' => 'title',
            'keywords' => 'keywords',
            'description' => 'description',
            'kfqq' => 'kfqq',
            'anounce' => 'anounce',
            'modal' => 'modal',
            'gg_search' => 'gg_search',
            'footer' => 'footer',
            'template' => 'template',
            'assistant_name' => 'ai_assistant_name',
        );
        $items = array();
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $args) && $args[$from] !== null && $args[$from] !== '') {
                $items[$to] = $args[$from];
            }
        }
        // 只改了站名且未给 title 时，同步浏览器标题，避免仍显示旧品牌
        if (isset($items['sitename']) && !isset($items['title'])) {
            $items['title'] = $items['sitename'];
        }
        if (!$items) return array('ok' => false, 'error' => '未提供任何可保存字段');
        return $this->updateConfig(array('items' => $items));
    }

    private function listTemplates()
    {
        $root = defined('ROOT') ? ROOT : dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;
        $dir = $root . 'template';
        $list = array();
        if (is_dir($dir)) {
            foreach (scandir($dir) as $name) {
                if ($name === '.' || $name === '..') continue;
                if (is_dir($dir . DIRECTORY_SEPARATOR . $name)) $list[] = $name;
            }
        }
        $cur = isset($this->conf['template']) ? $this->conf['template'] : '';
        return array('ok' => true, 'current' => $cur, 'data' => $list);
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
        $rows = $this->DB->getAll("SELECT id,zid,type,orderid,addtime,status FROM pre_workorder WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function parseWorkorderContent($raw)
    {
        $parts = explode('*', strval($raw));
        $msgs = array();
        if (isset($parts[0]) && $parts[0] !== '') {
            $msgs[] = array('role' => 'user', 'time' => null, 'text' => $parts[0]);
        }
        for ($i = 1; $i < count($parts); $i++) {
            $c = explode('^', $parts[$i]);
            if (count($c) >= 3) {
                $msgs[] = array(
                    'role' => (strval($c[0]) === '1') ? 'staff' : 'user',
                    'time' => $c[1],
                    'text' => $c[2],
                );
            }
        }
        return $msgs;
    }

    private function getWorkorder($args)
    {
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效工单ID');
        $row = $this->DB->getRow("SELECT * FROM pre_workorder WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '工单不存在');
        $statusMap = array(0 => '待处理', 1 => '已回复', 2 => '已完结');
        $row['status_text'] = isset($statusMap[intval($row['status'])]) ? $statusMap[intval($row['status'])] : strval($row['status']);
        $row['messages'] = $this->parseWorkorderContent(isset($row['content']) ? $row['content'] : '');
        return array('ok' => true, 'data' => $row);
    }

    private function replyWorkorder($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '回复工单需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $text = isset($args['content']) ? trim(strip_tags(strval($args['content']))) : '';
        $text = str_replace(array('*', '^', '|'), '', $text);
        if ($id <= 0 || $text === '') return array('ok' => false, 'error' => '工单ID与回复内容必填');
        $row = $this->DB->getRow("SELECT * FROM pre_workorder WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '工单不存在');
        if (intval($row['status']) === 2) return array('ok' => false, 'error' => '工单已完结，无法回复');
        $date = date('Y-m-d H:i:s');
        $complete = !empty($args['complete']);
        $newStatus = $complete ? 2 : 1;
        $content = $row['content'] . '*1^' . $date . '^' . $text;
        $contentSql = addslashes($content);
        $ok = $this->DB->exec("UPDATE pre_workorder SET content='$contentSql',status='$newStatus' WHERE id='$id'");
        if ($ok === false) return array('ok' => false, 'error' => '回复失败：' . $this->DB->error());
        $mailSent = false;
        if (!empty($args['send_email'])) {
            $mailSent = $this->sendWorkorderMail($id);
        }
        return array(
            'ok' => true,
            'msg' => $complete ? '已回复并完结工单' : '已回复工单',
            'id' => $id,
            'status' => $newStatus,
            'mail_sent' => $mailSent,
        );
    }

    private function sendWorkorderMail($id)
    {
        $id = intval($id);
        $rows = $this->DB->getRow("SELECT * FROM pre_workorder WHERE id='$id' LIMIT 1");
        if (!$rows) return false;
        $siterow = $this->DB->getRow("SELECT zid,user,qq FROM pre_site WHERE zid='" . intval($rows['zid']) . "' LIMIT 1");
        if (!$siterow || empty($siterow['qq'])) return false;
        $mail_name = $siterow['qq'] . '@qq.com';
        if (!function_exists('checkEmail') || !checkEmail($mail_name)) return false;
        if (!function_exists('send_mail')) return false;
        $sitename = isset($this->conf['sitename']) ? $this->conf['sitename'] : '本站';
        $content = explode('*', $rows['content']);
        $title = mb_substr(isset($content[0]) ? $content[0] : '', 0, 16, 'utf-8');
        $siteurl = (function_exists('is_https') && is_https() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . '/';
        $sub = $sitename . '售后支持工单待反馈提醒';
        $msg = '尊敬的' . $siterow['user'] . '：<br/>您于' . $rows['addtime'] . '提交的售后支持工单(ID:' . $id . ') 需要您进一步提供相关信息。请登录网站后台“我的工单”查看详情并回复。<a href="' . $siteurl . 'user/workorder.php?my=view&id=' . $id . '" target="_blank">点此查看</a><br/>工单标题：' . htmlspecialchars($title) . '<br/>----------------<br/>' . $sitename;
        @send_mail($mail_name, $sub, $msg);
        return true;
    }

    private function setWorkorderStatus($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '处理工单需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $status = intval(isset($args['status']) ? $args['status'] : -1);
        if ($id <= 0 || $status < 0 || $status > 2) return array('ok' => false, 'error' => '无效参数，status 仅支持 0/1/2');
        $ok = $this->DB->exec("UPDATE pre_workorder SET status='$status' WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '工单状态已更新' : $this->DB->error());
    }

    private function deleteWorkorder($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除工单需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效工单ID');
        $ok = $this->DB->exec("DELETE FROM pre_workorder WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => '工单已删除', 'id' => $id);
    }

    private function changeShopname($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '批量改名需 confirm=true');
        $old = isset($args['old_name']) ? trim(strval($args['old_name'])) : '';
        $new = isset($args['new_name']) ? trim(strval($args['new_name'])) : '';
        if ($old === '' || $new === '') return array('ok' => false, 'error' => 'old_name/new_name 不能为空');
        $ok = $this->DB->exec("UPDATE pre_tools SET name=REPLACE(name,:old,:new) WHERE 1", array(':old' => $old, ':new' => $new));
        return array('ok' => $ok !== false, 'msg' => '商品名批量替换完成');
    }

    private function changeInputs($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '批量替换输入框需 confirm=true');
        $old = isset($args['old_name']) ? trim(strval($args['old_name'])) : '';
        $new = isset($args['new_name']) ? trim(strval($args['new_name'])) : '';
        if ($old === '' || $new === '') return array('ok' => false, 'error' => 'old_name/new_name 不能为空');
        if ($old === '下单账号') {
            $this->DB->exec("UPDATE pre_tools SET input=REPLACE(input,:old,:new) WHERE 1", array(':old' => $old, ':new' => $new));
        }
        $ok = $this->DB->exec("UPDATE pre_tools SET inputs=REPLACE(inputs,:old,:new) WHERE 1", array(':old' => $old, ':new' => $new));
        return array('ok' => $ok !== false, 'msg' => '输入框名称批量替换完成');
    }

    private function resetGoodsSort($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '重置排序需 confirm=true');
        $cid = intval(isset($args['cid']) ? $args['cid'] : 0);
        if ($cid <= 0) return array('ok' => false, 'error' => 'cid 必填');
        $ok = $this->DB->exec("UPDATE pre_tools SET sort=tid WHERE cid='$cid'");
        return array('ok' => $ok !== false, 'msg' => '分类 ' . $cid . ' 排序已重置');
    }

    private function setSitePrice($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '设置分站加价需 confirm=true');
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        $price = isset($args['price']) ? floatval($args['price']) : -1;
        if ($zid <= 1 || $tid <= 0 || $price < 0) return array('ok' => false, 'error' => '参数无效');
        if (!class_exists('\\lib\\Price')) return array('ok' => false, 'error' => 'Price 类不可用');
        $priceObj = new \lib\Price($zid);
        $ok = $priceObj->setiPriceInfo($tid, $price);
        return array('ok' => (bool)$ok, 'msg' => $price > 0 ? '分站单独价已设置' : '已取消该商品单独价', 'zid' => $zid, 'tid' => $tid, 'price' => $price);
    }

    private function clearSitePrice($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '清空分站加价需 confirm=true');
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        if ($zid <= 1) return array('ok' => false, 'error' => '无效 zid');
        $ok = $this->DB->exec("UPDATE pre_site SET iprice=NULL WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'msg' => '已清空分站单独加价', 'zid' => $zid);
    }

    private function deleteSite($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除分站需 confirm=true');
        $zid = intval(isset($args['zid']) ? $args['zid'] : 0);
        if ($zid <= 1) return array('ok' => false, 'error' => '不能删除主站');
        $ok = $this->DB->exec("DELETE FROM pre_site WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'msg' => '分站已删除', 'zid' => $zid);
    }

    private function listUsers($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = 'power=0';
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (user LIKE '%$kw%' OR qq LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT zid,user,qq,rmb,status,addtime,lasttime FROM pre_site WHERE $where ORDER BY zid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listInviteRecords($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $rows = $this->DB->getAll("SELECT id,nid,tid,qq,input,`key`,ip,plan,click,count,status FROM pre_invite ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function deleteInviteRecord($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除推广记录需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效ID');
        $ok = $this->DB->exec("DELETE FROM pre_invite WHERE id='$id' LIMIT 1");
        return array('ok' => $ok !== false, 'msg' => '推广记录已删除', 'id' => $id);
    }

    private function listDockLogs($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (action LIKE '%$kw%' OR param LIKE '%$kw%' OR result LIKE '%$kw%')";
        }
        $rows = $this->DB->getAll("SELECT id,action,param,result,addtime,status FROM pre_logs WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function listRank($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 10;
        $days = isset($args['days']) ? intval($args['days']) : 1;
        $thtime = date('Y-m-d') . ' 00:00:00';
        if ($days === 0) {
            $lastday = date('Y-m-d', strtotime('-1 day')) . ' 00:00:00';
            $sql = "SELECT a.zid,(SELECT b.sitename FROM pre_site as b WHERE a.zid=b.zid) as sitename,count(id) as count,sum(money) as money FROM pre_orders as a WHERE addtime>='$lastday' AND addtime<'$thtime' AND zid>1 AND status!=4 GROUP BY zid ORDER BY money DESC LIMIT $limit";
        } else {
            $sql = "SELECT a.zid,(SELECT b.sitename FROM pre_site as b WHERE a.zid=b.zid) as sitename,count(id) as count,sum(money) as money FROM pre_orders as a WHERE addtime>='$thtime' AND zid>1 AND status!=4 GROUP BY zid ORDER BY money DESC LIMIT $limit";
        }
        $rows = $this->DB->getAll($sql);
        return array('ok' => true, 'count' => count($rows), 'data' => $rows, 'scope' => $days === 0 ? 'yesterday' : 'today');
    }

    private function createFanghongUrl($args)
    {
        $url = isset($args['url']) ? trim(strval($args['url'])) : '';
        if ($url === '') return array('ok' => false, 'error' => 'url 必填');
        if (!function_exists('fanghongdwz')) {
            return array('ok' => false, 'error' => 'fanghongdwz 函数不可用，请先在后台配置防红接口');
        }
        $force = !empty($args['force']);
        $turl = fanghongdwz($url, $force);
        if ($turl == $url) return array('ok' => false, 'error' => '生成失败，请更换防红接口');
        if (strpos($turl, '/') === false) return array('ok' => false, 'error' => '生成失败：' . $turl);
        return array('ok' => true, 'url' => $turl, 'original' => $url);
    }

    private function exportOrders($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '导出订单需 confirm=true');
        $tid = isset($args['tid']) ? intval($args['tid']) : 0;
        $cid = isset($args['cid']) ? intval($args['cid']) : 0;
        $status = isset($args['status']) ? intval($args['status']) : 0;
        $limit = isset($args['limit']) ? min(500, max(1, intval($args['limit']))) : 200;
        $where = '1=1';
        $values = array();
        if ($tid > 0) {
            $tool = $this->DB->getRow("SELECT tid,value FROM pre_tools WHERE tid='$tid' LIMIT 1");
            if (!$tool) return array('ok' => false, 'error' => '商品不存在');
            $values[$tid] = $tool['value'] > 0 ? $tool['value'] : 1;
            $where = "tid='$tid'";
        } elseif ($cid > 0) {
            $tools = $this->DB->getAll("SELECT tid,value FROM pre_tools WHERE cid='$cid'");
            $tids = array();
            foreach ($tools as $t) {
                $values[intval($t['tid'])] = $t['value'] > 0 ? $t['value'] : 1;
                $tids[] = intval($t['tid']);
            }
            if (!$tids) return array('ok' => false, 'error' => '分类下无商品');
            $where = 'tid IN (' . implode(',', $tids) . ')';
        }
        if (!empty($args['starttime'])) $where .= " AND addtime>='" . addslashes($args['starttime']) . " 00:00:00'";
        if (!empty($args['endtime'])) $where .= " AND addtime<='" . addslashes($args['endtime']) . " 23:59:59'";
        $where .= ' AND status=' . $status;
        $rows = $this->DB->getAll("SELECT id,tid,input,input2,input3,input4,input5,value FROM pre_orders WHERE $where ORDER BY id DESC LIMIT $limit");
        $lines = array();
        $mark = array_key_exists('mark_status', $args) ? intval($args['mark_status']) : null;
        foreach ($rows as $row) {
            $mul = isset($values[intval($row['tid'])]) ? $values[intval($row['tid'])] : 1;
            $line = $row['input'];
            if (!empty($row['input2'])) $line .= '----' . $row['input2'];
            if (!empty($row['input3'])) $line .= '----' . $row['input3'];
            if (!empty($row['input4'])) $line .= '----' . $row['input4'];
            if (!empty($row['input5'])) $line .= '----' . $row['input5'];
            $line .= '----' . (floatval($row['value']) * $mul);
            $lines[] = $line;
            if ($mark !== null && $mark >= 0) {
                $this->DB->exec("UPDATE pre_orders SET status='$mark' WHERE id='" . intval($row['id']) . "'");
            }
        }
        return array(
            'ok' => true,
            'count' => count($lines),
            'text' => implode("\n", $lines),
            'marked' => $mark !== null,
        );
    }

    private function deleteShequ($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除对接站需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效ID');
        $cnt = intval($this->DB->getColumn("SELECT count(*) FROM pre_tools WHERE shequ='$id'"));
        $ok = $this->DB->exec("DELETE FROM pre_shequ WHERE id='$id'");
        return array(
            'ok' => $ok !== false,
            'msg' => '对接站已删除' . ($cnt > 0 ? "（仍有 {$cnt} 个商品指向该站，请自行改商品）" : ''),
            'id' => $id,
            'linked_goods' => $cnt,
        );
    }

    private function batchSyncDockGoods($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '批量同步需 confirm=true');
        $shequId = intval(isset($args['shequ_id']) ? $args['shequ_id'] : 0);
        $cid = intval(isset($args['cid']) ? $args['cid'] : 0);
        $prid = isset($args['prid']) ? intval($args['prid']) : 0;
        $ids = $this->normalizeIdList(isset($args['goods_ids']) ? $args['goods_ids'] : array());
        if ($shequId <= 0 || $cid <= 0 || !$ids) return array('ok' => false, 'error' => 'shequ_id/cid/goods_ids 必填');
        if (count($ids) > 80) return array('ok' => false, 'error' => '单次最多同步80个商品');
        if (!$this->DB->getRow("SELECT cid FROM pre_class WHERE cid='$cid' LIMIT 1")) {
            return array('ok' => false, 'error' => '本地分类不存在');
        }
        $shequ = $this->DB->getRow("SELECT * FROM pre_shequ WHERE id='$shequId' LIMIT 1");
        if (!$shequ) return array('ok' => false, 'error' => '对接站不存在');
        if ($shequ['type'] !== 'daishua') return array('ok' => false, 'error' => '当前仅支持 daishua 类型批量同步');
        if (!class_exists('\\plugins\\third_daishua')) return array('ok' => false, 'error' => 'third_daishua 不可用');
        $plugin = new \plugins\third_daishua($shequ);
        $added = 0;
        $updated = 0;
        $missed = array();
        foreach ($ids as $gid) {
            $row = $plugin->goods_info($gid);
            if (!is_array($row)) {
                $missed[] = $gid;
                continue;
            }
            $name = isset($row['name']) ? $row['name'] : ('货源商品' . $gid);
            $price = isset($row['price']) ? floatval($row['price']) : 0;
            $input = isset($row['input']) ? $row['input'] : '下单账号';
            $inputs = isset($row['inputs']) ? $row['inputs'] : '';
            $desc = isset($row['desc']) ? $row['desc'] : '';
            $alert = isset($row['alert']) ? $row['alert'] : '';
            $shopimg = isset($row['shopimg']) ? $row['shopimg'] : '';
            $repeat = isset($row['repeat']) ? intval($row['repeat']) : 0;
            $multi = isset($row['multi']) ? intval($row['multi']) : 0;
            $min = isset($row['min']) ? intval($row['min']) : 1;
            $max = isset($row['max']) ? intval($row['max']) : 1;
            $validate = isset($row['validate']) ? intval($row['validate']) : 0;
            $valiserv = isset($row['valiserv']) ? $row['valiserv'] : '';
            $close = isset($row['close']) ? intval($row['close']) : 0;
            $isfaka = !empty($row['isfaka']) ? 1 : 0;
            $exist = $this->DB->getRow("SELECT tid FROM pre_tools WHERE shequ='$shequId' AND goods_id='" . addslashes(strval($gid)) . "' LIMIT 1");
            if ($exist) {
                $this->DB->exec("UPDATE pre_tools SET cid='$cid',name='" . addslashes($name) . "',price='$price',prid='$prid',cost='0',cost2='0',input='" . addslashes($input) . "',inputs='" . addslashes($inputs) . "',`desc`='" . addslashes($desc) . "',alert='" . addslashes($alert) . "',shopimg='" . addslashes($shopimg) . "',is_curl='2',goods_type='$isfaka',`repeat`='$repeat',multi='$multi',min='$min',max='$max',validate='$validate',valiserv='" . addslashes($valiserv) . "',close='$close' WHERE tid='" . intval($exist['tid']) . "'");
                $updated++;
            } else {
                $this->DB->exec("INSERT INTO pre_tools (cid,name,price,cost,cost2,prid,input,inputs,`desc`,alert,shopimg,value,is_curl,shequ,goods_id,goods_type,`repeat`,multi,min,max,validate,valiserv,close,active,addtime) VALUES ('$cid','" . addslashes($name) . "','$price','0','0','$prid','" . addslashes($input) . "','" . addslashes($inputs) . "','" . addslashes($desc) . "','" . addslashes($alert) . "','" . addslashes($shopimg) . "',1,2,'$shequId','" . addslashes(strval($gid)) . "','$isfaka','$repeat','$multi','$min','$max','$validate','" . addslashes($valiserv) . "','$close',1,NOW())");
                $added++;
            }
        }
        return array(
            'ok' => true,
            'msg' => "新增{$added}个，更新{$updated}个",
            'added' => $added,
            'updated' => $updated,
            'missed' => $missed,
        );
    }

    private function listFaka($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (!empty($args['tid'])) $where .= ' AND tid=' . intval($args['tid']);
        $rows = $this->DB->getAll("SELECT kid,tid,km,pw,orderid,addtime,usetime FROM pre_faka WHERE $where ORDER BY kid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function addFaka($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '导入卡密需 confirm=true');
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        $kms = isset($args['kms']) ? strval($args['kms']) : '';
        if ($tid <= 0 || trim($kms) === '') return array('ok' => false, 'error' => 'tid 与 kms 必填');
        $tool = $this->DB->getRow("SELECT tid,name FROM pre_tools WHERE tid='$tid' LIMIT 1");
        if (!$tool) return array('ok' => false, 'error' => '商品不存在');
        $split = isset($args['split']) ? strval($args['split']) : '';
        $check = !empty($args['check_repeat']);
        $kms = str_replace(array("\r\n", "\r", "\n"), '[br]', $kms);
        $lines = explode('[br]', $kms);
        $added = 0;
        $skipped = 0;
        foreach ($lines as $val) {
            $val = trim($val);
            if ($val === '') continue;
            if ($split === '') {
                $arr = preg_split('/\s+/', $val, 2);
            } else {
                $arr = explode($split, $val, 2);
            }
            $km = addslashes(trim($arr[0]));
            $pw = isset($arr[1]) ? addslashes(trim($arr[1])) : '';
            if ($km === '') continue;
            if ($check && $this->DB->getRow("SELECT kid FROM pre_faka WHERE km='$km' LIMIT 1")) {
                $skipped++;
                continue;
            }
            $ok = $this->DB->exec("INSERT INTO `pre_faka` (`tid`,`km`,`pw`,`addtime`) VALUES ('$tid','$km','$pw',NOW())");
            if ($ok) $added++;
        }
        return array('ok' => true, 'msg' => "成功添加{$added}张卡密", 'added' => $added, 'skipped' => $skipped, 'tid' => $tid);
    }

    private function deleteFaka($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除卡密需 confirm=true');
        $action = isset($args['action']) ? preg_replace('/[^a-z_]/', '', strtolower($args['action'])) : '';
        if ($action === 'kid') {
            $kid = intval(isset($args['kid']) ? $args['kid'] : 0);
            if ($kid <= 0) return array('ok' => false, 'error' => 'kid 必填');
            $ok = $this->DB->exec("DELETE FROM pre_faka WHERE kid='$kid'");
            return array('ok' => $ok !== false, 'msg' => '已删除卡密 #' . $kid);
        }
        if ($action === 'tid_all') {
            $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
            if ($tid <= 0) return array('ok' => false, 'error' => 'tid 必填');
            $ok = $this->DB->exec("DELETE FROM pre_faka WHERE tid='$tid'");
            return array('ok' => $ok !== false, 'msg' => '已清空商品 ' . $tid . ' 全部卡密');
        }
        if ($action === 'tid_used') {
            $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
            if ($tid <= 0) return array('ok' => false, 'error' => 'tid 必填');
            $ok = $this->DB->exec("DELETE FROM pre_faka WHERE tid='$tid' AND orderid!=0");
            return array('ok' => $ok !== false, 'msg' => '已清空商品 ' . $tid . ' 已售卡密');
        }
        return array('ok' => false, 'error' => 'action 仅支持 kid|tid_all|tid_used');
    }

    private function listKms($args)
    {
        $limit = isset($args['limit']) ? min(80, max(1, intval($args['limit']))) : 30;
        $where = '1=1';
        if (isset($args['type']) && $args['type'] !== '' && $args['type'] !== null) {
            $where .= ' AND type=' . intval($args['type']);
        }
        if (!empty($args['tid'])) $where .= ' AND tid=' . intval($args['tid']);
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $where .= ' AND status=' . intval($args['status']);
        }
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND km LIKE '%$kw%'";
        }
        $rows = $this->DB->getAll("SELECT kid,type,tid,num,km,money,status,addtime,usetime,orderid FROM pre_kms WHERE $where ORDER BY kid DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function randomKm($len = 18)
    {
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $n = strlen($str);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $str[mt_rand(0, $n - 1)];
        }
        return $out;
    }

    private function generateKms($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '生成卡密需 confirm=true');
        $type = intval(isset($args['type']) ? $args['type'] : -1);
        $count = isset($args['count']) ? intval($args['count']) : 10;
        if ($count <= 0) $count = 10;
        if ($count > 200) $count = 200;
        $date = date('Y-m-d H:i:s');
        $created = array();
        if ($type === 1) {
            $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
            $num = isset($args['num']) ? intval($args['num']) : 1;
            if ($tid <= 0 || $num <= 0) return array('ok' => false, 'error' => '兑换卡需 tid 与 num>0');
            if (!$this->DB->getRow("SELECT tid FROM pre_tools WHERE tid='$tid' LIMIT 1")) {
                return array('ok' => false, 'error' => '商品不存在');
            }
            for ($i = 0; $i < $count; $i++) {
                $km = $this->randomKm(18);
                $ok = $this->DB->exec("INSERT INTO pre_kms (`type`,`km`,`tid`,`num`,`addtime`) VALUES (1,'$km','$tid','$num','$date')");
                if ($ok) $created[] = $km;
            }
            return array('ok' => true, 'msg' => '已生成' . count($created) . '张兑换卡', 'type' => 1, 'tid' => $tid, 'kms' => $created);
        }
        if ($type === 0) {
            $money = isset($args['money']) ? floatval($args['money']) : 0;
            if ($money <= 0) return array('ok' => false, 'error' => '加款卡需 money>0');
            for ($i = 0; $i < $count; $i++) {
                $km = $this->randomKm(18);
                $ok = $this->DB->exec("INSERT INTO pre_kms (`type`,`km`,`money`,`addtime`) VALUES (0,'$km','$money','$date')");
                if ($ok) $created[] = $km;
            }
            return array('ok' => true, 'msg' => '已生成' . count($created) . '张加款卡', 'type' => 0, 'money' => $money, 'kms' => $created);
        }
        return array('ok' => false, 'error' => 'type 仅支持 0加款卡 或 1兑换卡');
    }

    private function deleteKms($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除卡密需 confirm=true');
        $action = isset($args['action']) ? preg_replace('/[^a-z_]/', '', strtolower($args['action'])) : '';
        if ($action === 'kid') {
            $kid = intval(isset($args['kid']) ? $args['kid'] : 0);
            if ($kid <= 0) return array('ok' => false, 'error' => 'kid 必填');
            $ok = $this->DB->exec("DELETE FROM pre_kms WHERE kid='$kid'");
            return array('ok' => $ok !== false, 'msg' => '已删除卡密 #' . $kid);
        }
        $type = isset($args['type']) ? intval($args['type']) : -1;
        if ($type !== 0 && $type !== 1) return array('ok' => false, 'error' => '清空时 type 必填（0或1）');
        if ($action === 'type_all') {
            $ok = $this->DB->exec("DELETE FROM pre_kms WHERE type='$type'");
            return array('ok' => $ok !== false, 'msg' => '已清空 type=' . $type . ' 全部卡密');
        }
        if ($action === 'type_used') {
            $ok = $this->DB->exec("DELETE FROM pre_kms WHERE type='$type' AND status=1");
            return array('ok' => $ok !== false, 'msg' => '已清空 type=' . $type . ' 已使用卡密');
        }
        return array('ok' => false, 'error' => 'action 仅支持 kid|type_all|type_used');
    }

    private function listArticles($args)
    {
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = '1=1';
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND title LIKE '%$kw%'";
        }
        $rows = $this->DB->getAll("SELECT id,title,active,top,addtime,count FROM pre_article WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function getArticle($args)
    {
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效文章ID');
        $row = $this->DB->getRow("SELECT * FROM pre_article WHERE id='$id' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '文章不存在');
        return array('ok' => true, 'data' => $row);
    }

    private function saveArticle($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存文章需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $title = isset($args['title']) ? trim(strval($args['title'])) : '';
        $content = isset($args['content']) ? strval($args['content']) : '';
        if ($title === '' || $content === '') return array('ok' => false, 'error' => 'title 与 content 必填');
        $keywords = isset($args['keywords']) ? trim(strval($args['keywords'])) : '';
        $description = isset($args['description']) ? trim(strval($args['description'])) : '';
        $active = array_key_exists('active', $args) ? intval($args['active']) : 1;
        $top = array_key_exists('top', $args) ? intval($args['top']) : 0;
        $titleSql = addslashes($title);
        $contentSql = addslashes($content);
        $kwSql = addslashes($keywords);
        $descSql = addslashes($description);
        if ($id > 0) {
            $row = $this->DB->getRow("SELECT id FROM pre_article WHERE id='$id' LIMIT 1");
            if (!$row) return array('ok' => false, 'error' => '文章不存在');
            $ok = $this->DB->exec("UPDATE pre_article SET title='$titleSql',content='$contentSql',keywords='$kwSql',description='$descSql',active='$active',top='$top' WHERE id='$id'");
            return array('ok' => $ok !== false, 'msg' => '文章已更新', 'id' => $id);
        }
        $date = date('Y-m-d H:i:s');
        $ok = $this->DB->exec("INSERT INTO pre_article (zid,title,content,keywords,description,addtime,top,active) VALUES (1,'$titleSql','$contentSql','$kwSql','$descSql','$date','$top','$active')");
        if ($ok === false) return array('ok' => false, 'error' => '新增失败：' . $this->DB->error());
        $newId = intval($this->DB->lastInsertId());
        return array('ok' => true, 'msg' => '文章已发布', 'id' => $newId);
    }

    private function deleteArticle($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除文章需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效文章ID');
        $ok = $this->DB->exec("DELETE FROM pre_article WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => '文章已删除', 'id' => $id);
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
        $rows = $this->DB->getAll("SELECT id,type,title,content,addtime,active,count FROM pre_message ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function saveMessage($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存通知需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $title = isset($args['title']) ? trim(strval($args['title'])) : '';
        $content = isset($args['content']) ? strval($args['content']) : '';
        if ($title === '' || $content === '') return array('ok' => false, 'error' => 'title 与 content 必填');
        $type = array_key_exists('type', $args) ? intval($args['type']) : 0;
        if ($type < 0 || $type > 4) $type = 0;
        $active = array_key_exists('active', $args) ? intval($args['active']) : 1;
        $titleSql = addslashes($title);
        $contentSql = addslashes($content);
        if ($id > 0) {
            $row = $this->DB->getRow("SELECT id FROM pre_message WHERE id='$id' LIMIT 1");
            if (!$row) return array('ok' => false, 'error' => '通知不存在');
            $ok = $this->DB->exec("UPDATE pre_message SET type='$type',title='$titleSql',content='$contentSql',active='$active' WHERE id='$id'");
            return array('ok' => $ok !== false, 'msg' => '通知已更新', 'id' => $id);
        }
        $date = date('Y-m-d H:i:s');
        $ok = $this->DB->exec("INSERT INTO pre_message (type,title,content,addtime,active) VALUES ('$type','$titleSql','$contentSql','$date','$active')");
        if ($ok === false) return array('ok' => false, 'error' => '发布失败：' . $this->DB->error());
        return array('ok' => true, 'msg' => '通知已发布', 'id' => intval($this->DB->lastInsertId()));
    }

    private function deleteMessage($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除通知需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效通知ID');
        $ok = $this->DB->exec("DELETE FROM pre_message WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => '通知已删除', 'id' => $id);
    }

    private function listPriceRules()
    {
        $rows = $this->DB->getAll("SELECT * FROM pre_price ORDER BY id ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function savePriceRule($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存加价模板需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $name = isset($args['name']) ? trim(strval($args['name'])) : '';
        $kind = isset($args['kind']) ? intval($args['kind']) : 0;
        $p2 = isset($args['p_2']) ? floatval($args['p_2']) : null;
        $p1 = isset($args['p_1']) ? floatval($args['p_1']) : null;
        $p0 = isset($args['p_0']) ? floatval($args['p_0']) : null;
        if ($name === '' || $p2 === null || $p1 === null || $p0 === null) {
            return array('ok' => false, 'error' => 'name/p_2/p_1/p_0 必填');
        }
        if ($p2 > $p1) return array('ok' => false, 'error' => '专业版加价不能高于普及版加价');
        if ($p2 > $p0) return array('ok' => false, 'error' => '专业版加价不能高于普通用户加价');
        if ($p1 > $p0) return array('ok' => false, 'error' => '普及版加价不能高于普通用户加价');
        $nameSql = addslashes($name);
        $dupWhere = $id > 0 ? "id!='$id' AND name='$nameSql'" : "name='$nameSql'";
        if ($this->DB->getRow("SELECT id FROM pre_price WHERE $dupWhere LIMIT 1")) {
            return array('ok' => false, 'error' => '模板名称已存在');
        }
        if ($id > 0) {
            $row = $this->DB->getRow("SELECT id FROM pre_price WHERE id='$id' LIMIT 1");
            if (!$row) return array('ok' => false, 'error' => '模板不存在');
            $ok = $this->DB->exec("UPDATE pre_price SET kind='$kind',name='$nameSql',p_2='$p2',p_1='$p1',p_0='$p0' WHERE id='$id'");
            if ($ok !== false && $this->CACHE) $this->CACHE->clear('pricerules');
            return array('ok' => $ok !== false, 'msg' => '加价模板已更新', 'id' => $id);
        }
        $ok = $this->DB->exec("INSERT INTO pre_price (kind,name,p_0,p_1,p_2) VALUES ('$kind','$nameSql','$p0','$p1','$p2')");
        if ($ok === false) return array('ok' => false, 'error' => '添加失败：' . $this->DB->error());
        if ($this->CACHE) $this->CACHE->clear('pricerules');
        return array('ok' => true, 'msg' => '加价模板已添加', 'id' => intval($this->DB->lastInsertId()));
    }

    private function deletePriceRule($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除加价模板需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效模板ID');
        $ok = $this->DB->exec("DELETE FROM pre_price WHERE id='$id' LIMIT 1");
        if ($ok !== false && $this->CACHE) $this->CACHE->clear('pricerules');
        return array('ok' => $ok !== false, 'msg' => '加价模板已删除', 'id' => $id);
    }

    private function listGifts()
    {
        $rows = $this->DB->getAll("SELECT a.id,a.name,a.tid,a.rate,a.ok,(SELECT b.name FROM pre_tools as b WHERE a.tid=b.tid) as shopname FROM pre_gift as a ORDER BY a.id ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function saveGift($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '保存奖品需 confirm=true');
        $id = isset($args['id']) ? intval($args['id']) : 0;
        $name = isset($args['name']) ? trim(strval($args['name'])) : '';
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        $rate = isset($args['rate']) ? floatval($args['rate']) : -1;
        if ($name === '' || $tid <= 0 || $rate < 0) return array('ok' => false, 'error' => 'name/tid/rate 必填');
        $tool = $this->DB->getRow("SELECT tid FROM pre_tools WHERE tid='$tid' LIMIT 1");
        if (!$tool) return array('ok' => false, 'error' => '对应商品不存在');
        $nameSql = addslashes($name);
        $rateInt = intval($rate);
        if ($id > 0) {
            $row = $this->DB->getRow("SELECT id FROM pre_gift WHERE id='$id' LIMIT 1");
            if (!$row) return array('ok' => false, 'error' => '奖品不存在');
            $ok = $this->DB->exec("UPDATE pre_gift SET name='$nameSql',tid='$tid',rate='$rateInt' WHERE id='$id'");
            return array('ok' => $ok !== false, 'msg' => '奖品已更新', 'id' => $id);
        }
        $ok = $this->DB->exec("INSERT INTO pre_gift (name,tid,rate,ok) VALUES ('$nameSql','$tid','$rateInt',0)");
        if ($ok === false) return array('ok' => false, 'error' => '添加失败：' . $this->DB->error());
        return array('ok' => true, 'msg' => '奖品已添加', 'id' => intval($this->DB->lastInsertId()));
    }

    private function deleteGift($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '删除奖品需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        if ($id <= 0) return array('ok' => false, 'error' => '无效奖品ID');
        $ok = $this->DB->exec("DELETE FROM pre_gift WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => '奖品已删除', 'id' => $id);
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

    private function isToolAllowed($name)
    {
        if ($this->scope === 'admin') return true;
        foreach ($this->definitions() as $d) {
            if (isset($d['function']['name']) && $d['function']['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    private function definitionsShop()
    {
        return array(
            $this->fn('shop_list_classes', '列出前台可见的商品分类', array()),
            $this->fn('shop_search_goods', '按关键词或分类搜索上架商品（公开信息）', array(
                'keyword' => array('type' => 'string', 'description' => '商品名关键词，可空'),
                'cid' => array('type' => 'integer', 'description' => '分类ID，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认12，最大30'),
            )),
            $this->fn('shop_get_goods', '获取单个商品的公开详情：名称、价格、说明、提示、输入框、库存概况', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
            ), array('tid')),
            $this->fn('shop_recommend_goods', '淘宝式推荐：同类/热门/同价位/关键词；返回商品卡片信息', array(
                'mode' => array('type' => 'string', 'description' => 'similar同类|hot热门|price同价位|keyword关键词，默认自动'),
                'keyword' => array('type' => 'string', 'description' => '需求描述或关键词，可空'),
                'cid' => array('type' => 'integer', 'description' => '分类ID，可空'),
                'tid' => array('type' => 'integer', 'description' => '当前商品ID，用于找同类/同价，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认6，最大12'),
            )),
            $this->fn('shop_explain_goods', '用白话解释商品怎么买、填什么、注意什么', array(
                'tid' => array('type' => 'integer', 'description' => '商品ID'),
            ), array('tid')),
            $this->fn('shop_site_faq', '获取站点公告、简介、文章摘要、客服联系方式', array(
                'limit' => array('type' => 'integer', 'description' => '文章条数默认5'),
            )),
            $this->fn('shop_query_order', '查订单状态。优先用订单号/支付单号；若只有下单账号，必须同时提供 money 或 date 之一。不含卡密', array(
                'keyword' => array('type' => 'string', 'description' => '订单号、支付单号，或下单账号'),
                'money' => array('type' => 'number', 'description' => '按账号查时必填之一：订单金额'),
                'date' => array('type' => 'string', 'description' => '按账号查时必填之一：下单日期 YYYY-MM-DD'),
            ), array('keyword')),
            $this->fn('shop_aftersale_help', '售后指导：根据订单号解读状态；按账号查须带 money 或 date。复杂问题引导用户点转人工按钮', array(
                'keyword' => array('type' => 'string', 'description' => '订单号或下单账号，可空则给通用售后指引'),
                'money' => array('type' => 'number', 'description' => '按账号查时的金额，可空'),
                'date' => array('type' => 'string', 'description' => '按账号查时的日期，可空'),
                'issue' => array('type' => 'string', 'description' => '用户描述的问题，如未到账/卡密无效/要退款'),
            )),
            $this->fn('shop_transfer_human', '引导用户转人工：不直接建单。告知用户点击浮窗「转人工」填写联系方式。可整理 summary 供用户参考', array(
                'reason' => array('type' => 'string', 'description' => '为何需要人工'),
                'summary' => array('type' => 'string', 'description' => '问题摘要，可空'),
            )),
        );
    }

    private function definitionsUser()
    {
        $defs = array(
            $this->fn('user_my_balance', '查看当前登录用户余额与基本账号信息', array()),
            $this->fn('user_my_orders', '查看当前用户自己的订单列表', array(
                'keyword' => array('type' => 'string', 'description' => '账号/订单号关键词，可空'),
                'status' => array('type' => 'integer', 'description' => '订单状态，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认20，最大50'),
            )),
            $this->fn('user_get_order', '查看当前用户自己的订单详情', array(
                'id' => array('type' => 'integer', 'description' => '订单ID'),
            ), array('id')),
            $this->fn('user_my_workorders', '查看当前用户工单列表', array(
                'status' => array('type' => 'integer', 'description' => '工单状态，可空'),
                'limit' => array('type' => 'integer', 'description' => '默认20'),
            )),
            $this->fn('user_get_workorder', '查看自己的工单详情', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
            ), array('id')),
            $this->fn('user_create_workorder', '为当前用户提交工单', array(
                'content' => array('type' => 'string', 'description' => '问题描述'),
                'type' => array('type' => 'integer', 'description' => '工单类型，默认0'),
                'orderid' => array('type' => 'integer', 'description' => '关联订单ID，可空'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true'),
            ), array('content', 'confirm')),
            $this->fn('user_reply_workorder', '回复自己的工单', array(
                'id' => array('type' => 'integer', 'description' => '工单ID'),
                'content' => array('type' => 'string', 'description' => '回复内容'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true'),
            ), array('id', 'content', 'confirm')),
            $this->fn('user_list_classes', '浏览商品分类', array()),
            $this->fn('user_list_goods', '浏览/搜索商品公开信息', array(
                'keyword' => array('type' => 'string', 'description' => '关键词'),
                'cid' => array('type' => 'integer', 'description' => '分类ID'),
                'limit' => array('type' => 'integer', 'description' => '条数'),
            )),
        );
        $power = isset($this->actor['power']) ? intval($this->actor['power']) : 0;
        if ($power > 0) {
            $defs[] = $this->fn('user_site_info', '查看本分站显示信息（站名/标题/关键词/简介/客服）', array());
            $defs[] = $this->fn('user_update_site', '更新本分站显示信息；仅允许 sitename/title/keywords/description/kfqq', array(
                'sitename' => array('type' => 'string', 'description' => '站名'),
                'title' => array('type' => 'string', 'description' => '标题'),
                'keywords' => array('type' => 'string', 'description' => '关键词'),
                'description' => array('type' => 'string', 'description' => '简介'),
                'kfqq' => array('type' => 'string', 'description' => '客服QQ'),
                'confirm' => array('type' => 'boolean', 'description' => '必须为 true'),
            ), array('confirm'));
        }
        return $defs;
    }

    private function actorZid()
    {
        return isset($this->actor['zid']) ? intval($this->actor['zid']) : 0;
    }

    private function shopPublicPrice($row)
    {
        global $price_obj;
        if (isset($price_obj) && is_object($price_obj)) {
            try {
                $price_obj->setToolInfo($row['tid'], $row);
                if (method_exists($price_obj, 'getToolDel') && $price_obj->getToolDel($row['tid']) == 1) {
                    return null;
                }
                return floatval($price_obj->getToolPrice($row['tid']));
            } catch (\Exception $e) {
            }
        }
        return isset($row['price']) ? floatval($row['price']) : 0;
    }

    private function shopPublicGoodsRow($row)
    {
        $price = $this->shopPublicPrice($row);
        if ($price === null) return null;
        $isfaka = (isset($row['is_curl']) && intval($row['is_curl']) === 4) ? 1 : 0;
        $stock = isset($row['stock']) ? $row['stock'] : null;
        $stockText = ($stock === null || $stock === '') ? '不限' : (intval($stock) > 0 ? ('约' . intval($stock)) : '暂无');
        $desc = isset($row['desc']) ? strip_tags(strval($row['desc'])) : '';
        if (mb_strlen($desc) > 500) $desc = mb_substr($desc, 0, 500) . '…';
        $alert = isset($row['alert']) ? strip_tags(strval($row['alert'])) : '';
        if (mb_strlen($alert) > 300) $alert = mb_substr($alert, 0, 300) . '…';
        return array(
            'tid' => intval($row['tid']),
            'cid' => intval($row['cid']),
            'name' => strval($row['name']),
            'price' => $price,
            'input' => isset($row['input']) ? strval($row['input']) : '下单账号',
            'inputs' => isset($row['inputs']) ? strval($row['inputs']) : '',
            'desc' => $desc,
            'alert' => $alert,
            'isfaka' => $isfaka,
            'stock_text' => $stockText,
            'close' => isset($row['close']) ? intval($row['close']) : 0,
            'multi' => isset($row['multi']) ? intval($row['multi']) : 0,
            'min' => isset($row['min']) ? intval($row['min']) : 1,
            'max' => isset($row['max']) ? intval($row['max']) : 1,
            'link' => '?cid=' . intval($row['cid']) . '&tid=' . intval($row['tid']),
        );
    }

    private function shopListClasses($args = array())
    {
        $rows = $this->DB->getAll("SELECT cid,name,sort FROM pre_class WHERE active=1 ORDER BY sort ASC");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function shopSearchGoods($args)
    {
        $limit = isset($args['limit']) ? min(30, max(1, intval($args['limit']))) : 12;
        $where = 'active=1';
        if (!empty($args['cid'])) $where .= ' AND cid=' . intval($args['cid']);
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND name LIKE '%$kw%'";
        }
        $rows = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort FROM pre_tools WHERE $where ORDER BY sort ASC LIMIT $limit");
        $data = array();
        foreach ($rows as $row) {
            $item = $this->shopPublicGoodsRow($row);
            if ($item) $data[] = $item;
        }
        return array('ok' => true, 'count' => count($data), 'data' => $data);
    }

    private function shopGetGoods($args)
    {
        $tid = intval(isset($args['tid']) ? $args['tid'] : 0);
        if ($tid <= 0 && !empty($this->actor['context']['tid'])) {
            $tid = intval($this->actor['context']['tid']);
        }
        if ($tid <= 0) return array('ok' => false, 'error' => '请提供商品 tid');
        $row = $this->DB->getRow("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,value FROM pre_tools WHERE tid='$tid' AND active=1 LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '商品不存在或已下架');
        $item = $this->shopPublicGoodsRow($row);
        if (!$item) return array('ok' => false, 'error' => '商品当前不可购买');
        $cls = $this->DB->getRow("SELECT cid,name FROM pre_class WHERE cid='{$row['cid']}' LIMIT 1");
        $item['class_name'] = $cls ? $cls['name'] : '';
        $item['default_num'] = isset($row['value']) ? intval($row['value']) : 1;
        return array('ok' => true, 'goods' => $item);
    }

    private function shopRecommendGoods($args)
    {
        $limit = isset($args['limit']) ? min(12, max(1, intval($args['limit']))) : 6;
        $cid = !empty($args['cid']) ? intval($args['cid']) : 0;
        $tid = !empty($args['tid']) ? intval($args['tid']) : 0;
        $mode = isset($args['mode']) ? strtolower(trim(strval($args['mode']))) : '';
        $keyword = !empty($args['keyword']) ? trim(strval($args['keyword'])) : '';
        if ($tid <= 0 && !empty($this->actor['context']['tid'])) {
            $tid = intval($this->actor['context']['tid']);
        }
        if ($cid <= 0 && !empty($this->actor['context']['cid'])) {
            $cid = intval($this->actor['context']['cid']);
        }

        $cur = null;
        $curPrice = null;
        if ($tid > 0) {
            $cur = $this->DB->getRow("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE tid='$tid' AND active=1 LIMIT 1");
            if ($cur) {
                if ($cid <= 0) $cid = intval($cur['cid']);
                $curPrice = $this->shopPublicPrice($cur);
            }
        }

        if ($mode === '' || $mode === 'auto') {
            if ($keyword !== '') $mode = 'keyword';
            elseif ($tid > 0) $mode = 'similar';
            else $mode = 'hot';
        }
        if (!in_array($mode, array('similar', 'hot', 'price', 'keyword'), true)) {
            $mode = 'hot';
        }

        $exclude = $tid > 0 ? (' AND tid!=' . $tid) : '';
        $rows = array();
        $reasonMap = array(
            'similar' => '看了又看·同分类',
            'hot' => '热销推荐',
            'price' => '同价位优选',
            'keyword' => '猜你喜欢',
        );

        if ($mode === 'keyword' && $keyword !== '') {
            $kw = addslashes($keyword);
            $rows = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE active=1 AND name LIKE '%$kw%'$exclude ORDER BY sales DESC, sort ASC LIMIT $limit");
        } elseif ($mode === 'price' && $curPrice !== null) {
            $lo = max(0, $curPrice * 0.7);
            $hi = $curPrice * 1.3;
            $whereCid = $cid > 0 ? (' AND cid=' . $cid) : '';
            $cand = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE active=1$whereCid$exclude ORDER BY sales DESC, sort ASC LIMIT 40");
            $scored = array();
            foreach ($cand as $row) {
                $p = $this->shopPublicPrice($row);
                if ($p === null || $p < $lo || $p > $hi) continue;
                $row['_price'] = $p;
                $row['_diff'] = abs($p - $curPrice);
                $scored[] = $row;
            }
            usort($scored, function ($a, $b) {
                if ($a['_diff'] == $b['_diff']) return 0;
                return ($a['_diff'] < $b['_diff']) ? -1 : 1;
            });
            $rows = array_slice($scored, 0, $limit);
        } elseif ($mode === 'similar' && $cid > 0) {
            $rows = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE active=1 AND cid='$cid'$exclude ORDER BY sales DESC, sort ASC LIMIT $limit");
        } else {
            $mode = 'hot';
            $rows = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE active=1$exclude ORDER BY sales DESC, sort ASC LIMIT $limit");
        }

        if (count($rows) < 2 && $mode !== 'hot') {
            $rows = $this->DB->getAll("SELECT tid,cid,name,price,input,inputs,`desc`,alert,is_curl,stock,close,multi,min,max,sort,sales FROM pre_tools WHERE active=1$exclude ORDER BY sales DESC, sort ASC LIMIT $limit");
            $mode = 'hot';
        }

        $data = array();
        $baseReason = isset($reasonMap[$mode]) ? $reasonMap[$mode] : '站内推荐';
        foreach ($rows as $row) {
            $item = $this->shopPublicGoodsRow($row);
            if (!$item) continue;
            $extra = '';
            if ($mode === 'price' && $curPrice !== null) {
                $extra = ' · 约 ¥' . $item['price'];
            } elseif ($mode === 'similar' && $cur) {
                $extra = ' · 与「' . mb_substr($cur['name'], 0, 12) . '」同类';
            } elseif ($mode === 'hot' && !empty($row['sales'])) {
                $extra = ' · 已售' . intval($row['sales']);
            }
            $item['reason'] = $baseReason . $extra;
            $item['mode'] = $mode;
            $data[] = $item;
        }
        return array(
            'ok' => true,
            'mode' => $mode,
            'count' => count($data),
            'data' => $data,
            'tip' => '点击商品链接到页面自行下单，我不能代付',
        );
    }

    private function shopExplainGoods($args)
    {
        $got = $this->shopGetGoods($args);
        if (empty($got['ok'])) return $got;
        $g = $got['goods'];
        $inputs = array();
        if (!empty($g['input'])) $inputs[] = $g['input'];
        if (!empty($g['inputs'])) {
            foreach (explode('|', $g['inputs']) as $x) {
                $x = trim($x);
                if ($x === '') continue;
                if (strpos($x, '{') !== false) $x = substr($x, 0, strpos($x, '{'));
                if (strpos($x, '[') !== false) $x = substr($x, 0, strpos($x, '['));
                $inputs[] = $x;
            }
        }
        $steps = array();
        $steps[] = '商品：' . $g['name'] . '（tid ' . $g['tid'] . '），售价约 ' . $g['price'] . ' 元。';
        if (!empty($g['class_name'])) $steps[] = '分类：' . $g['class_name'] . '。';
        if ($g['isfaka']) $steps[] = '这是发卡商品，下单支付后系统自动发码，请填写正确联系方式。';
        if ($inputs) $steps[] = '需要填写：' . implode('、', $inputs) . '。请按页面提示如实填写，密码类勿发给陌生人。';
        if ($g['multi']) $steps[] = '支持购买多份，范围大约 ' . $g['min'] . '-' . $g['max'] . '。';
        $steps[] = '库存：' . $g['stock_text'] . ($g['close'] ? '；当前已关闭购买' : '。');
        if ($g['desc'] !== '') $steps[] = '说明：' . $g['desc'];
        if ($g['alert'] !== '') $steps[] = '下单注意：' . $g['alert'];
        $steps[] = '请在页面选择该商品后自行下单支付，我无法代你付款。链接：' . $g['link'];
        return array('ok' => true, 'goods' => $g, 'explain' => $steps, 'fill_fields' => $inputs);
    }

    private function shopSiteFaq($args)
    {
        $limit = isset($args['limit']) ? min(10, max(1, intval($args['limit']))) : 5;
        $conf = $this->conf;
        $announce = isset($conf['anounce']) ? strip_tags(strval($conf['anounce'])) : '';
        if (mb_strlen($announce) > 800) $announce = mb_substr($announce, 0, 800) . '…';
        $desc = isset($conf['description']) ? strval($conf['description']) : '';
        $articles = $this->DB->getAll("SELECT id,title,description,addtime FROM pre_article WHERE active=1 ORDER BY top DESC,id DESC LIMIT $limit");
        foreach ($articles as &$a) {
            if (!empty($a['description']) && mb_strlen($a['description']) > 120) {
                $a['description'] = mb_substr($a['description'], 0, 120) . '…';
            }
        }
        return array(
            'ok' => true,
            'sitename' => isset($conf['sitename']) ? $conf['sitename'] : '本站',
            'description' => $desc,
            'announce' => $announce,
            'kfqq' => isset($conf['kfqq']) ? $conf['kfqq'] : '',
            'kfwx' => !empty($conf['kfwx']) ? '已配置微信客服' : '',
            'workorder_open' => !empty($conf['workorder_open']) ? 1 : 0,
            'articles' => $articles,
            'tip' => '复杂售后请联系客服或登录用户中心提交工单；也可让我帮你转人工',
        );
    }

    private function maskInput($input)
    {
        $s = strval($input);
        $len = mb_strlen($s);
        if ($len <= 2) return str_repeat('*', $len);
        if ($len <= 5) return mb_substr($s, 0, 1) . str_repeat('*', $len - 2) . mb_substr($s, -1);
        return mb_substr($s, 0, 2) . str_repeat('*', min(6, $len - 4)) . mb_substr($s, -2);
    }

    private function shopQueryOrder($args)
    {
        $kw = isset($args['keyword']) ? trim(strval($args['keyword'])) : '';
        if ($kw === '') return array('ok' => false, 'error' => '请提供订单号或支付单号');

        $money = null;
        if (isset($args['money']) && $args['money'] !== '' && $args['money'] !== null) {
            $money = floatval($args['money']);
        }
        $date = isset($args['date']) ? trim(strval($args['date'])) : '';
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return array('ok' => false, 'error' => '日期格式应为 YYYY-MM-DD');
        }

        $kwSql = addslashes($kw);
        // 订单号 / 支付单号：精确查
        $rows = $this->DB->getAll("SELECT id,tid,input,status,money,addtime,tradeno FROM pre_orders WHERE id='$kwSql' OR tradeno='$kwSql' ORDER BY id DESC LIMIT 8");
        $byAccount = false;
        if (!$rows) {
            // 按账号查必须二次校验
            if ($money === null && $date === '') {
                return array(
                    'ok' => false,
                    'error' => '未匹配到订单号。若用下单账号查询，请同时提供订单金额(money)或下单日期(date，如 2026-10-02)',
                    'need_verify' => true,
                );
            }
            $byAccount = true;
            $where = "input='$kwSql'";
            if ($money !== null) {
                $where .= ' AND ABS(money-' . floatval($money) . ')<0.02';
            }
            if ($date !== '') {
                $where .= " AND DATE(addtime)='" . addslashes($date) . "'";
            }
            $rows = $this->DB->getAll("SELECT id,tid,input,status,money,addtime,tradeno FROM pre_orders WHERE $where ORDER BY id DESC LIMIT 5");
        }

        if (!$rows) {
            return array('ok' => true, 'count' => 0, 'data' => array(), 'msg' => '未找到相关订单，请核对订单号，或带金额/日期用账号再查，也可点转人工');
        }

        $data = array();
        foreach ($rows as $row) {
            $tool = $this->DB->getRow("SELECT name FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
            $data[] = array(
                'id' => intval($row['id']),
                'name' => $tool ? $tool['name'] : ('商品' . $row['tid']),
                'input' => $this->maskInput($row['input']),
                'status' => intval($row['status']),
                'status_text' => $this->orderStatusText($row['status']),
                'money' => $row['money'],
                'addtime' => $row['addtime'],
            );
        }
        return array(
            'ok' => true,
            'count' => count($data),
            'data' => $data,
            'by_account' => $byAccount,
            'tip' => '卡密等敏感内容请到订单查询页用完整凭证查看；账号已脱敏',
        );
    }

    private function shopAftersaleHelp($args)
    {
        $issue = isset($args['issue']) ? trim(strval($args['issue'])) : '';
        $kw = isset($args['keyword']) ? trim(strval($args['keyword'])) : '';
        $steps = array();
        $needHuman = false;
        $orders = array();

        if ($kw !== '') {
            $qArgs = array('keyword' => $kw);
            if (isset($args['money'])) $qArgs['money'] = $args['money'];
            if (!empty($args['date'])) $qArgs['date'] = $args['date'];
            $q = $this->shopQueryOrder($qArgs);
            if (empty($q['ok']) && !empty($q['need_verify'])) {
                $steps[] = $q['error'];
                $steps[] = '查到订单后我再帮你解读售后步骤。';
                return array('ok' => true, 'need_human' => false, 'orders' => array(), 'guide' => $steps, 'issue' => $issue, 'need_verify' => true);
            }
            if (!empty($q['data'])) {
                $orders = $q['data'];
                foreach ($orders as $o) {
                    $st = intval($o['status']);
                    $steps[] = '订单 #' . $o['id'] . '「' . $o['name'] . '」当前：' . $o['status_text'] . '，金额 ' . $o['money'] . '。';
                    if ($st === 0) {
                        $steps[] = '未处理：请稍候；若长时间未动请点浮窗「转人工」。';
                        $needHuman = true;
                    } elseif ($st === 2) {
                        $steps[] = '处理中：一般需等待完成，请勿重复下单。';
                    } elseif ($st === 1) {
                        $steps[] = '已完成：发卡类请到订单查询页查看卡密；若卡密无效，请点「转人工」并留下订单号。';
                        if ($issue !== '' && (mb_strpos($issue, '无效') !== false || mb_strpos($issue, '退') !== false || mb_strpos($issue, '不能') !== false)) {
                            $needHuman = true;
                        }
                    } elseif ($st === 3) {
                        $steps[] = '异常：请点「转人工」说明情况。';
                        $needHuman = true;
                    } elseif ($st === 4) {
                        $steps[] = '已退款：余额退款请登录用户中心查看；游客请核对原支付渠道。';
                    }
                }
            } else {
                $steps[] = isset($q['msg']) ? $q['msg'] : '未查到该订单，请核对订单号。';
                $needHuman = true;
            }
        } else {
            $steps[] = '通用售后自助：';
            $steps[] = '1. 优先用订单号/支付单号查单；只有账号时请同时告诉我金额或下单日期。';
            $steps[] = '2. 发卡商品到「订单查询」页查看卡密，勿把卡密发给陌生人。';
            $steps[] = '3. 未到账/处理中请耐心等待，勿重复付款。';
            $steps[] = '4. 退款、卡密失效等请点浮窗「转人工」留下联系方式。';
        }

        if ($issue !== '') {
            if (mb_strpos($issue, '退') !== false || mb_strpos($issue, '骗') !== false || mb_strpos($issue, '投诉') !== false) {
                $needHuman = true;
                $steps[] = '涉及退款/投诉，AI 不能直接退款，请点「转人工」。';
            }
        }

        $kfqq = isset($this->conf['kfqq']) ? $this->conf['kfqq'] : '';
        $steps[] = $needHuman
            ? '请点击浮窗底部「转人工」提交工单' . ($kfqq !== '' ? ('，或联系客服QQ ' . $kfqq) : '') . '。'
            : '若仍解决不了，可随时点「转人工」。';

        return array(
            'ok' => true,
            'need_human' => $needHuman,
            'orders' => $orders,
            'guide' => $steps,
            'issue' => $issue,
        );
    }

    /**
     * 对话内仅引导；真正建单由 ajax handoff（actor.handoff_api）调用 createCsTicketHandoff
     */
    private function shopTransferHuman($args)
    {
        if (!empty($this->actor['handoff_api'])) {
            return $this->createCsTicketHandoff($args);
        }
        $reason = isset($args['reason']) ? trim(strval($args['reason'])) : '';
        $summary = isset($args['summary']) ? trim(strval($args['summary'])) : '';
        $kfqq = isset($this->conf['kfqq']) ? $this->conf['kfqq'] : '';
        return array(
            'ok' => true,
            'created' => false,
            'need_human' => true,
            'msg' => '请点击浮窗「转人工」填写联系方式和问题后提交，我无法在对话里直接建单。'
                . ($kfqq !== '' ? ('也可加客服QQ ' . $kfqq) : ''),
            'reason' => $reason,
            'summary' => $summary,
        );
    }

    /**
     * 仅供前台 handoff API 调用（actor.handoff_api=true）
     */
    public function createCsTicketHandoff(array $args)
    {
        $contact = isset($args['contact']) ? trim(strval($args['contact'])) : '';
        $problem = isset($args['problem']) ? trim(strval($args['problem'])) : '';
        $orderNo = isset($args['order_no']) ? trim(strval($args['order_no'])) : '';
        $summary = isset($args['summary']) ? trim(strval($args['summary'])) : '';
        if ($contact === '' || mb_strlen($contact) < 2) {
            return array('ok' => false, 'error' => '请提供有效联系方式（QQ/微信/手机）');
        }
        if ($problem === '') {
            return array('ok' => false, 'error' => '请简要描述问题');
        }

        $guestId = isset($this->actor['guest_id']) ? strval($this->actor['guest_id']) : '';
        $sessionId = isset($this->actor['session_id']) ? intval($this->actor['session_id']) : 0;
        $ip = isset($this->actor['ip']) ? strval($this->actor['ip']) : '';
        $zid = isset($this->actor['zid']) ? intval($this->actor['zid']) : 0;
        $workorderId = 0;

        if ($zid > 0 && !empty($this->conf['workorder_open'])) {
            $content = '【AI转人工】' . $problem . ($orderNo !== '' ? (' 订单:' . $orderNo) : '') . ' 联系:' . $contact;
            if ($summary !== '') $content .= ' 摘要:' . mb_substr($summary, 0, 200);
            $contentSql = addslashes(str_replace(array('*', '^', '|'), '', strip_tags($content)));
            $orderid = 0;
            if ($orderNo !== '' && ctype_digit($orderNo)) {
                $oid = intval($orderNo);
                $ord = $this->DB->getRow("SELECT id FROM pre_orders WHERE id='$oid' AND userid='$zid' LIMIT 1");
                if ($ord) $orderid = $oid;
            }
            $okWo = $this->DB->exec("INSERT INTO pre_workorder (zid,type,orderid,content,picurl,addtime,status) VALUES ('$zid',0,'$orderid','$contentSql','',NOW(),0)");
            if ($okWo !== false) $workorderId = intval($this->DB->lastInsertId());
        }

        $store = new Store($this->DB);
        $store->ensureSchema();
        $id = $store->createCsTicket(array(
            'session_id' => $sessionId,
            'channel' => 'shop',
            'contact' => $contact,
            'order_no' => $orderNo,
            'problem' => $problem,
            'summary' => $summary,
            'guest_id' => $guestId,
            'zid' => $zid,
            'workorder_id' => $workorderId,
            'ip' => $ip,
        ));

        $kfqq = isset($this->conf['kfqq']) ? $this->conf['kfqq'] : '';
        return array(
            'ok' => true,
            'created' => true,
            'cs_id' => $id,
            'workorder_id' => $workorderId,
            'msg' => '已提交人工客服工单 #' . $id . '，工作人员会尽快联系你。' . ($kfqq !== '' ? ('也可加客服QQ ' . $kfqq) : ''),
            'need_human' => true,
        );
    }

    private function listAiCs($args)
    {
        if ($this->scope !== 'admin') return array('ok' => false, 'error' => '仅管理员可用');
        $store = new Store($this->DB);
        $store->ensureSchema();
        $list = $store->listCsTickets(array(
            'status' => isset($args['status']) ? $args['status'] : '',
            'keyword' => isset($args['keyword']) ? trim(strval($args['keyword'])) : '',
            'limit' => isset($args['limit']) ? intval($args['limit']) : 30,
        ));
        return array('ok' => true, 'count' => count($list), 'data' => $list);
    }

    private function getAiCs($args)
    {
        if ($this->scope !== 'admin') return array('ok' => false, 'error' => '仅管理员可用');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $store = new Store($this->DB);
        $store->ensureSchema();
        $row = $store->getCsTicket($id);
        if (!$row) return array('ok' => false, 'error' => '工单不存在');
        return array('ok' => true, 'ticket' => $row);
    }

    private function replyAiCs($args)
    {
        if ($this->scope !== 'admin') return array('ok' => false, 'error' => '仅管理员可用');
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '回复需 confirm=true');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $reply = isset($args['reply']) ? trim(strval($args['reply'])) : '';
        if ($reply === '') return array('ok' => false, 'error' => '回复内容不能为空');
        $close = !empty($args['close']);
        $store = new Store($this->DB);
        $store->ensureSchema();
        $ok = $store->replyCsTicket($id, $reply, 'admin-ai', $close);
        return array('ok' => $ok, 'msg' => $ok ? ($close ? '已回复并完结' : '已回复') : '操作失败');
    }

    private function userMyBalance()
    {
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        $row = $this->DB->getRow("SELECT zid,user,qq,rmb,power,sitename,status FROM pre_site WHERE zid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '账号不存在');
        return array(
            'ok' => true,
            'zid' => intval($row['zid']),
            'user' => $row['user'],
            'rmb' => $row['rmb'],
            'power' => intval($row['power']),
            'sitename' => $row['sitename'],
            'status' => intval($row['status']),
        );
    }

    private function userMyOrders($args)
    {
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = "userid='$zid'";
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $st = is_numeric($args['status']) ? intval($args['status']) : $this->parseOrderStatus($args['status']);
            if ($st >= 0) $where .= ' AND status=' . $st;
        }
        if (!empty($args['keyword'])) {
            $kw = addslashes(trim($args['keyword']));
            $where .= " AND (input LIKE '%$kw%' OR tradeno LIKE '%$kw%' OR id='$kw')";
        }
        $rows = $this->DB->getAll("SELECT id,tid,input,value,status,money,addtime,tradeno FROM pre_orders WHERE $where ORDER BY id DESC LIMIT $limit");
        $data = array();
        foreach ($rows as $row) {
            $tool = $this->DB->getRow("SELECT name FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
            $row['goods_name'] = $tool ? $tool['name'] : '';
            $data[] = $this->decorateOrderRow($row);
        }
        return array('ok' => true, 'count' => count($data), 'data' => $data);
    }

    private function userGetOrder($args)
    {
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $row = $this->DB->getRow("SELECT id,tid,zid,userid,input,input2,input3,value,status,money,addtime,tradeno,result FROM pre_orders WHERE id='$id' AND userid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '订单不存在或不属于你');
        $tool = $this->DB->getRow("SELECT tid,name,alert,`desc` FROM pre_tools WHERE tid='{$row['tid']}' LIMIT 1");
        return array('ok' => true, 'order' => $this->decorateOrderRow($row), 'goods' => $tool);
    }

    private function userMyWorkorders($args)
    {
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        if (empty($this->conf['workorder_open'])) return array('ok' => false, 'error' => '站点未开启工单');
        $limit = isset($args['limit']) ? min(50, max(1, intval($args['limit']))) : 20;
        $where = "zid='$zid'";
        if (isset($args['status']) && $args['status'] !== '' && $args['status'] !== null) {
            $where .= ' AND status=' . intval($args['status']);
        }
        $rows = $this->DB->getAll("SELECT id,type,orderid,addtime,status FROM pre_workorder WHERE $where ORDER BY id DESC LIMIT $limit");
        return array('ok' => true, 'count' => count($rows), 'data' => $rows);
    }

    private function userGetWorkorder($args)
    {
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $row = $this->DB->getRow("SELECT * FROM pre_workorder WHERE id='$id' AND zid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '工单不存在');
        return array('ok' => true, 'workorder' => $row);
    }

    private function userCreateWorkorder($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '提交工单需 confirm=true');
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        if (empty($this->conf['workorder_open'])) return array('ok' => false, 'error' => '站点未开启工单');
        $content = isset($args['content']) ? trim(str_replace(array('*', '^', '|'), '', strip_tags(strval($args['content'])))) : '';
        if ($content === '') return array('ok' => false, 'error' => '描述不能为空');
        $type = isset($args['type']) ? intval($args['type']) : 0;
        $orderid = isset($args['orderid']) ? intval($args['orderid']) : 0;
        if ($orderid > 0) {
            $ord = $this->DB->getRow("SELECT id FROM pre_orders WHERE id='$orderid' AND userid='$zid' LIMIT 1");
            if (!$ord) return array('ok' => false, 'error' => '只能关联自己的订单');
            if ($this->DB->getRow("SELECT id FROM pre_workorder WHERE orderid='$orderid' AND status<2 ORDER BY id DESC LIMIT 1")) {
                return array('ok' => false, 'error' => '该订单已有未完结工单');
            }
        }
        $contentSql = addslashes($content);
        $ok = $this->DB->exec("INSERT INTO pre_workorder (zid,type,orderid,content,picurl,addtime,status) VALUES ('$zid','$type','$orderid','$contentSql','',NOW(),0)");
        if ($ok === false) return array('ok' => false, 'error' => '提交失败：' . $this->DB->error());
        return array('ok' => true, 'msg' => '工单已提交', 'id' => intval($this->DB->lastInsertId()));
    }

    private function userReplyWorkorder($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '回复工单需 confirm=true');
        $zid = $this->actorZid();
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        $id = intval(isset($args['id']) ? $args['id'] : 0);
        $content = isset($args['content']) ? trim(str_replace(array('*', '^', '|'), '', strip_tags(strval($args['content'])))) : '';
        if ($content === '') return array('ok' => false, 'error' => '回复内容不能为空');
        $row = $this->DB->getRow("SELECT * FROM pre_workorder WHERE id='$id' AND zid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '工单不存在');
        if (intval($row['status']) >= 2) return array('ok' => false, 'error' => '工单已完结');
        $contents = addslashes($row['content'] . '*' . $content);
        $ok = $this->DB->exec("UPDATE pre_workorder SET content='$contents',status=0 WHERE id='$id'");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '已回复' : $this->DB->error());
    }

    private function userSiteInfo()
    {
        $zid = $this->actorZid();
        $power = isset($this->actor['power']) ? intval($this->actor['power']) : 0;
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        if ($power <= 0) return array('ok' => false, 'error' => '普通用户无站点设置权限');
        $row = $this->DB->getRow("SELECT zid,sitename,title,keywords,description,kfqq,power FROM pre_site WHERE zid='$zid' LIMIT 1");
        if (!$row) return array('ok' => false, 'error' => '站点不存在');
        return array('ok' => true, 'site' => $row);
    }

    private function userUpdateSite($args)
    {
        if (empty($args['confirm'])) return array('ok' => false, 'error' => '更新站点信息需 confirm=true');
        $zid = $this->actorZid();
        $power = isset($this->actor['power']) ? intval($this->actor['power']) : 0;
        if ($zid <= 0) return array('ok' => false, 'error' => '未登录');
        if ($power <= 0) return array('ok' => false, 'error' => '普通用户无站点设置权限');
        $allow = array('sitename', 'title', 'keywords', 'description', 'kfqq');
        $sets = array();
        foreach ($allow as $k) {
            if (isset($args[$k])) {
                $sets[] = "`$k`='" . addslashes(trim(strval($args[$k]))) . "'";
            }
        }
        if (!$sets) return array('ok' => false, 'error' => '未提供可修改字段');
        $ok = $this->DB->exec("UPDATE pre_site SET " . implode(',', $sets) . " WHERE zid='$zid'");
        return array('ok' => $ok !== false, 'msg' => $ok !== false ? '站点信息已更新' : $this->DB->error());
    }
}

