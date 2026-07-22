<?php
/**
 * CustomCore — Block direct browsing of product upload storage.
 */
declare(strict_types=1);

http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Forbidden';
exit;
