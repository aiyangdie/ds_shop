# Deobfuscation workspace

This directory contains reproducible outputs from the source-cleaning tools in
`tools/deobfuscate/`. Generated stage files are intentionally ignored by Git;
the scripts and manifests are the reviewable source of truth.

## Current findings

- `includes/core.func.php` and `includes/ajax.func.php` were nested-eval VMs
  (24-mode core vs 17 Ajax helpers). Readable drop-ins live under
  `recovered/` and are now installed as the live copies via
  `tools/deobfuscate/install_recovered.php`.
- Fifteen files used heavy `goto` flattening. Recovered copies exist for
  all of them (`admin/*.php` listed in `recovered/README.md` plus
  `recovered/common.php`) and are installed as the live admin/common files.
- Runtime inspection in an isolated PHP server confirmed three nested eval
  layers. The innermost layer registers 24 business functions, including
  `processorder`, `changeusermoney`, `pay_api`, `third_call`, and
  `shequ_get_curl`.
- PHPDBG inspection showed that those public functions are thin wrappers over
  one shared virtualized dispatcher of roughly 860 opcodes.
- Runtime reflection successfully exported the dispatcher's 225 KB static
  payload. It contains all 24 wrapper signatures plus readable SQL, messages,
  configuration keys, and encoded instruction data. This is the first complete
  recoverable representation of the protected business layer.
- The protector derives part of its decoder state from the original execution
  context. Moving an eval into a helper changes that scope, so payload capture
  must preserve both byte offsets and eval scope.
- Fifteen files use heavy control-flow flattening with generated `goto` labels,
  global string tables, indirect calls, and opaque arithmetic.
- The original Git history and sampled public forks already contain the same
  protection; a clean historical copy has not been found.

## Safe first-stage extraction

Run with PHP 7.4 from the repository root:

```text
php tools/deobfuscate/extract_literal_payloads.php includes/core.func.php deobfuscated/stages/core-literals
php tools/deobfuscate/expand_literal_layers.php includes/core.func.php deobfuscated/stages/core-stage1.php
php tools/deobfuscate/decode_static_pack_tables.php includes/common.php deobfuscated/stages/common-stage1.php
php tools/deobfuscate/intercept_decoder_eval.php includes/core.func.php deobfuscated/stages/core-intercept.php
php tools/deobfuscate/patch_cli_guard.php includes/core.func.php deobfuscated/stages/core-cli.php
php tools/deobfuscate/inspect_serialized_static.php deobfuscated/stages/function-static.bin
php tools/deobfuscate/extract_static_string.php deobfuscated/stages/function-static.bin deobfuscated/stages/core-dispatcher-payload.bin
php tools/deobfuscate/assemble_core_func.php
php tools/deobfuscate/smoke_recovered_core.php
php tools/deobfuscate/decode_goto_string_tables.php admin/classlist.php deobfuscated/stages/goto/classlist-strings.json
php tools/deobfuscate/switch_recovered_core.php status
php tools/deobfuscate/switch_recovered_ajax.php status
php tools/deobfuscate/coverage_goto_recovery.php
```

Goto-flattened pages store HTML/SQL in a hex `explode` table plus `pack(H*, …)`
lookups. Decode the table first, then rewrite the page. The first recovered
example is `deobfuscated/recovered/admin/classlist.php`.

These tools decode literals only. They do not evaluate or include protected
code and therefore do not trigger its network, database, or anti-debug paths.

