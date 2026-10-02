SET NAMES utf8mb4;
SELECT cid, name, active FROM shua_class ORDER BY sort;
SELECT tid, cid, name, price, is_curl, active FROM shua_tools ORDER BY tid;
SELECT tid, COUNT(*) AS card_stock FROM shua_faka WHERE orderid=0 GROUP BY tid;
SELECT COUNT(*) AS class_total FROM shua_class;
SELECT COUNT(*) AS tools_total FROM shua_tools;
SELECT COUNT(*) AS cards_total FROM shua_faka WHERE orderid=0;
SELECT k, v FROM shua_config WHERE k IN ('sitename','keywords');
DELETE FROM shua_cache WHERE k='config';
