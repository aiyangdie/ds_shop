<?php

/**
 * First manually recovered functions from includes/core.func.php.
 *
 * These implementations are intentionally kept outside the live include path
 * until the complete core replacement passes parity tests.
 */

function getSetting($key, $fromDatabase = false)
{
    global $CACHE, $DB;

    if ($fromDatabase) {
        return $DB->getColumn(
            'SELECT v FROM pre_config WHERE k=:key limit 1',
            [':key' => $key]
        );
    }

    // The protected implementation invokes the cache getter but does not
    // propagate its return value. Keep that observed behavior for parity.
    $CACHE->get($key);
    return null;
}

function saveSetting($key, $value)
{
    global $DB;

    return $DB->exec(
        'REPLACE INTO pre_config SET v=:value,k=:key',
        [':key' => $key, ':value' => $value]
    );
}

function epay_check($unused)
{
    return true;
}

