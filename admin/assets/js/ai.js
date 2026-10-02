/**
 * 后台 AI 页：补默认配置后交给公共 ai-chat.js
 */
(function () {
    if (!window.AI_PAGE) window.AI_PAGE = {};
    if (!AI_PAGE.endpoint) AI_PAGE.endpoint = 'ajax_ai.php';
    if (!AI_PAGE.lsKey) AI_PAGE.lsKey = 'admin';
    if (typeof AI_PAGE.showLogs === 'undefined') AI_PAGE.showLogs = true;
})();
