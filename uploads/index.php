<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Block direct browsing of the uploads directory. Uploaded files are referenced by.
// generated names from application pages only.
declare(strict_types=1);

http_response_code(403);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Forbidden';
exit;
