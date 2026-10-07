# Recovered core function map

The protected core exposes 24 named wrappers over one shared dispatcher. The
runtime payload preserves the following stable mode mapping:

| Mode | Function |
|---:|---|
| 1 | `curl_get` |
| 2 | `shequ_get_curl` |
| 3 | `send_mail` |
| 4 | `send_wechat` |
| 5 | `getSetting` |
| 6 | `saveSetting` |
| 7 | `doOrder` |
| 8 | `processOrder` |
| 9 | `do_curl` |
| 10 | `third_call` |
| 11 | `do_goods` |
| 12 | `changeUserMoney` |
| 13 | `addPointRecord` |
| 14 | `rollbackPoint` |
| 15 | `log_result` |
| 16 | `sysmsg` |
| 17 | `batchSql` |
| 18 | `rm_dir` |
| 19 | `sec_check` |
| 20 | `epay_check` |
| 21 | `pay_api` |
| 22 | `get_pay_api` |
| 23 | `merge_site_conf` |
| 24 | `ordername_replace` |

Use `extract_wrapper_manifest.php` for exact protected parameter signatures and
`split_dispatcher_payload.php` to produce one independently inspectable binary
segment per function.

