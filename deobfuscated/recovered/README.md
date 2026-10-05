# Recovered source

Files in this directory are readable replacements reconstructed from the
protected runtime payload. They are not loaded by the application yet.

Each function must satisfy two checks before replacing the protected core:

1. PHP 7.4 syntax validation.
2. Behavioral parity against the original function with recording database,
   cache, filesystem, and network doubles.

`core-simple.php` is the first verified batch. It contains modes 5, 6, 10, 12-15,
17, 18, and 20: configuration access, balance/point/log recording, SQL batches,
plugin dispatch, recursive directory removal, and the payment availability check.

