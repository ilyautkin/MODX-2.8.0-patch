<?php
if (strpos(__DIR__, 's24006') === false) {
    unlink(MODX_CORE_PATH . 'packages/modx-2.8.0-patch-1.0.0-pl.transport.zip');
    if ($cache = $transport->xpdo->getCacheManager()) {
        $cache->deleteTree(MODX_CORE_PATH . 'packages/modx-2.8.0-patch-1.0.0-pl/', ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]);
    }
}
