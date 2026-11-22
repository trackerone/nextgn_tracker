<?php

declare(strict_types=1);

return [
    // Maximum number of newest SQL-prefiltered torrent candidates examined per
    // RSS request. The feed builder enforces a hard cap of 2000 and minimum 100.
    'scan_budget' => 2000,
];
