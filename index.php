<?php
declare(strict_types=1);

// Canonical production entrypoint: root → /customer/
header('Location: /customer/', true, 301);
exit;
