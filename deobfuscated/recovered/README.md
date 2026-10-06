# Recovered source

Files in this directory are readable replacements reconstructed from the
protected runtime payload. They are not loaded by the application yet.

Each function must satisfy two checks before replacing the protected core:

1. PHP 7.4 syntax validation.
2. Behavioral parity against the original function with recording database,
   cache, filesystem, and network doubles.

`core-simple.php` is the first verified batch. It contains modes 5, 6, 10, 12-15,
17, 18, and 20-24: configuration access and permission-aware site merging,
balance/point/log recording, SQL batches,
plugin dispatch, recursive directory removal, payment availability, and order-name
template expansion, and configured payment API selection.

`core-network.php` contains the recovered generic GET and supplier HTTP clients
(modes 1 and 2), including their original request headers and proxy support,
plus the Wxpusher/ServerChan notification dispatcher (mode 4), and the
placeholder URL builder `do_curl` (mode 9) used by order docking callbacks.

`core-notify.php` contains mail sending (mode 3: Sendcloud / Aliyun / PHPMailer),
the HTML `sysmsg` page renderer (mode 16), and the admin security checklist
`sec_check` (mode 19), including weak-password, archive, and PHP version checks.

`core-order.php` contains the order fulfillment batch:

- `do_goods` (mode 11): automatic card delivery, community plugin docking via
  `third_call`, and the is_curl=1 URL visitor path.
- `doOrder` (mode 7): create `pre_orders`, update sales/stock, then fulfill via
  faka / shequ / curl, optionally writing site profit and buy/fail notifications.
- `processOrder` (mode 8): paid-trade dispatcher for recharge (`tid=-1`), site
  open/upgrade (`tid=-2`), cart checkout (`tid=-3`), and ordinary goods orders,
  including invite-reward follow-up when configured.

All 24 protected core modes are now present as readable PHP in this directory.
`core.func.php` is the assembled drop-in (see `tools/deobfuscate/assemble_core_func.php`
and `switch_recovered_core.php`). The live `includes/core.func.php` stays protected
until you run the switch tool.

## Recovered admin pages

Goto-flattened admin pages rewritten from decoded hex string tables:

`classlist.php`, `orderjk.php`, `shoplist.php`, `pricejk.php`, `fakalist.php`,
`userlist.php`, `invite.php`, `article.php`, `account.php`, `shequlist.php`,
`sitelist.php`, `clone.php`, `shopedit.php`, `set.php`. Recovered bootstrap:
`includes/common.php` (as `recovered/common.php`).

Goto-flattened admin pages and `includes/common.php` now have recovered copies.
The live files stay protected until an explicit switch.

`ajax.func.php` is the assembled drop-in for all 17 nested-eval ajax helpers
(8 in `ajax-basic.php`, 9 in `ajax-rest.php`). Reconstruction uses the captured
dispatcher string tables plus call sites. Live `includes/ajax.func.php` stays
protected until `tools/deobfuscate/switch_recovered_ajax.php` is run.

