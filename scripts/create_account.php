<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/auth_functions.php';
$database = null;
try {
    $input = json_decode(stream_get_contents(STDIN, 8192), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid setup input.');
    $database = serviceDatabase();
    authCreateInitialUser($database, $input);
    unset($input);
    echo "Account created. Open http://localhost/Foody/login.php to sign in." . PHP_EOL;
} catch (InvalidArgumentException | DomainException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    error_log('Foody initial account: ' . $exception->getMessage());
    fwrite(STDERR, 'Account setup failed. Check the local PHP log and database configuration.' . PHP_EOL);
    exit(1);
} finally {
    if ($database instanceof mysqli) {
        $database->close();
    }
}
