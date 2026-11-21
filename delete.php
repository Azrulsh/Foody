<?php

declare(strict_types=1);
require_once __DIR__ . '/auth_functions.php';
authRequireUser();

// Delete in the existing UI resets the form; this legacy write endpoint is retired.
header('Allow: GET');
http_response_code(410);
exit('This endpoint is no longer available.');
