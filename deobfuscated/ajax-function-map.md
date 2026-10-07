# Recovered Ajax function map

The protected `includes/ajax.func.php` exposes 17 wrappers over a shared
139,000-byte virtualized dispatcher. The isolated PHP 7.4 web harness revealed
these stable modes:

| Mode | Function |
|---:|---|
| 1 | `getDatePoint` |
| 2 | `getFakaInput` |
| 3 | `uploadimg` |
| 4 | `setToolSort` |
| 5 | `setClassSort` |
| 6 | `getshareid` |
| 7 | `validate_qzone` |
| 8 | `getshuoshuo` |
| 9 | `getrizhi` |
| 10 | `get_app_token` |
| 11 | `processInvite` |
| 12 | `fanghongdwz` |
| 13 | `qrcodelogin` |
| 14 | `vaptcha_verify` |
| 15 | `display_third_title` |
| 16 | `article_url` |
| 17 | `adminpermission` |

The live file remains untouched. Readable reconstructions of all 17 wrappers
are in `deobfuscated/recovered/ajax.func.php` (`ajax-basic.php` 8 + `ajax-rest.php` 9).
