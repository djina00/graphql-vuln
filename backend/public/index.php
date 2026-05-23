<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\GraphQL\Schema;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;

$config = require __DIR__ . '/../config.php';

// CORS for the dev frontend running on a different port.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $config['frontend_origin']) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'samesite' => 'Lax',
    'httponly' => true,
]);
session_start();

header('Content-Type: application/json');

$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$method = $_SERVER['REQUEST_METHOD'];

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    if ($path === '/auth/register' && $method === 'POST') {
        $in = read_json_body();
        echo json_encode(Auth::register(
            (string)($in['email'] ?? ''),
            (string)($in['password'] ?? ''),
            (string)($in['displayName'] ?? '')
        ));
        exit;
    }

    if ($path === '/auth/login' && $method === 'POST') {
        $in = read_json_body();
        echo json_encode(Auth::login(
            (string)($in['email'] ?? ''),
            (string)($in['password'] ?? '')
        ));
        exit;
    }

    if ($path === '/auth/logout' && $method === 'POST') {
        Auth::logout();
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($path === '/auth/me' && $method === 'GET') {
        echo json_encode(['user' => Auth::currentUser()]);
        exit;
    }

    if ($path === '/graphql' && $method === 'POST') {
        $in = read_json_body();
        $query = (string)($in['query'] ?? '');
        $variables = $in['variables'] ?? null;
        $operationName = $in['operationName'] ?? null;

        // VULN: info-leak — introspection enabled (no DisableIntrospection rule)
        // VULN: query-depth — no QueryDepth/QueryComplexity rule registered
        // VULN: alias-overload — no MaxAliases rule registered
        $result = GraphQL::executeQuery(
            Schema::build(),
            $query,
            null,
            ['userId' => Auth::currentUserId()],
            is_array($variables) ? $variables : null,
            is_string($operationName) ? $operationName : null
        );

        // VULN: info-leak — debug flags leak stack traces and field suggestions
        $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
        echo json_encode($result->toArray($debug));
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
