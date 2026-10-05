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

`core-order.php` contains `do_goods` (mode 11): automatic card delivery, community
plugin docking via `third_call`, and the is_curl=1 URL visitor path.

