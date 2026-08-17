<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$portal = (string)($_GET['portal'] ?? '');
if (!in_array($portal, ['admin', 'manager', 'staff'], true)) {
    http_response_code(404);
    exit('Not found');
}

function projectBasePath(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/backend/portal.php'));
    $pos = strrpos($script, '/backend/');
    return $pos === false ? '' : rtrim(substr($script, 0, $pos), '/');
}

function portalLocation(string $portal, string $page): string
{
    return projectBasePath() . '/' . rawurlencode($portal) . '/' . $page;
}

$auth = (new AdminAuthService())->checkAuth();
if (empty($auth['authenticated'])) {
    header('Location: ' . portalLocation($portal, 'login.html'), true, 302);
    exit;
}

$role = (string)($auth['data']['role'] ?? '');
if (!InternalAccess::roleCanUsePortal($role, $portal)) {
    $target = InternalAccess::primaryPortalForRole($role);
    header('Location: ' . portalLocation($target, 'index.html'), true, 302);
    exit;
}

$file = dirname(__DIR__) . '/' . $portal . '/index.html';
if (!is_file($file)) {
    http_response_code(404);
    exit('Portal file not found');
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
readfile($file);
