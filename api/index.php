<?php
declare(strict_types=1);

/**
 * LOC-VOI - Vercel PHP Front Controller
 *
 * All PHP requests are routed through this file.
 * The requested PHP file is then loaded from the project root.
 */

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    http_response_code(500);
    exit('Application root not found.');
}

$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

/**
 * Get requested path.
 */
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

if (!is_string($path) || $path === '') {
    $path = '/';
}

$path = rawurldecode($path);
$path = '/' . ltrim($path, '/');

/**
 * Normalize path.
 */
$path = preg_replace('#/+#', '/', $path) ?? '/';

/**
 * Security: block sensitive/internal paths.
 */
$blockedPatterns = [
    '#^/includes(?:/|$)#i',
    '#^/\.git(?:/|$)#i',
    '#^/\.vercel(?:/|$)#i',
    '#^/\.env(?:$|[.])#i',
    '#^/vendor(?:/|$)#i',
    '#^/node_modules(?:/|$)#i',
    '#^/database\.sql$#i',
    '#^/README(?:\.md)?$#i',
    '#^/\.htaccess$#i',
    '#^/composer\.(json|lock)$#i',
    '#^/package(?:\.json|-lock\.json)$#i',
    '#^/vercel\.json$#i',
];

/**
 * Never allow the router to require itself.
 */
if ($path === '/api/index.php') {
    http_response_code(404);
    exit('Not Found');
}

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $path)) {
        http_response_code(404);
        exit('Not Found');
    }
}

/**
 * Remove leading slash.
 */
$relativePath = ltrim($path, '/');

/**
 * Root request -> index.php
 */
if ($relativePath === '') {
    $relativePath = 'index.php';
}

/**
 * Remove trailing slash except root.
 */
$relativePath = rtrim($relativePath, '/');

/**
 * If the requested path points to a directory,
 * use its index.php.
 */
$initialPath = $root . DIRECTORY_SEPARATOR . str_replace(
    '/',
    DIRECTORY_SEPARATOR,
    $relativePath
);

if (is_dir($initialPath)) {
    $initialPath = rtrim($initialPath, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'index.php';
}

/**
 * If no extension was supplied, try .php.
 *
 * Example:
 * /login -> /login.php
 * /cars -> /cars.php
 * /admin -> /admin/index.php
 */
if (!is_file($initialPath) && pathinfo($initialPath, PATHINFO_EXTENSION) === '') {
    $phpCandidate = $initialPath . '.php';

    if (is_file($phpCandidate)) {
        $initialPath = $phpCandidate;
    }
}

/**
 * Resolve real path.
 */
$realPath = realpath($initialPath);

/**
 * File must exist.
 */
if ($realPath === false || !is_file($realPath)) {
    http_response_code(404);
    exit('Page Not Found');
}

/**
 * Prevent directory traversal / escaping project root.
 */
if (
    $realPath !== $root &&
    strpos($realPath, $rootPrefix) !== 0
) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Never expose protected files even through a strange path.
 */
$relativeRealPath = ltrim(
    str_replace('\\', '/', substr($realPath, strlen($root))),
    '/'
);

if (
    str_starts_with($relativeRealPath, 'includes/') ||
    str_starts_with($relativeRealPath, '.git/') ||
    str_starts_with($relativeRealPath, '.vercel/') ||
    $relativeRealPath === 'database.sql' ||
    $relativeRealPath === 'README.md' ||
    $relativeRealPath === '.htaccess' ||
    $relativeRealPath === 'vercel.json'
) {
    http_response_code(404);
    exit('Not Found');
}

/**
 * PHP files.
 */
if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) === 'php') {

    /**
     * Important for scripts using relative includes.
     *
     * Example:
     * admin/login.php
     *
     * require_once '../includes/config.php';
     */
    chdir(dirname($realPath));

    $_SERVER['SCRIPT_FILENAME'] = $realPath;
    $_SERVER['SCRIPT_NAME'] = '/' . $relativeRealPath;
    $_SERVER['PHP_SELF'] = '/' . $relativeRealPath;

    /**
     * Execute the requested PHP file.
     */
    require $realPath;
    exit;
}

/**
 * Static files.
 */
$mimeTypes = [
    'css'   => 'text/css; charset=UTF-8',
    'js'    => 'application/javascript; charset=UTF-8',
    'json'  => 'application/json; charset=UTF-8',
    'xml'   => 'application/xml; charset=UTF-8',
    'txt'   => 'text/plain; charset=UTF-8',
    'html'  => 'text/html; charset=UTF-8',
    'htm'   => 'text/html; charset=UTF-8',

    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',

    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',

    'pdf'   => 'application/pdf',
];

$extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

$contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);

if ($extension === 'pdf') {
    header('Content-Disposition: inline');
}

readfile($realPath);
exit;
