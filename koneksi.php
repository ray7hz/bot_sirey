<?php
/**
 * koneksi.php
 * Database connection and query helpers.
 */

declare(strict_types=1);


// ================== CONFIG ==================

function sirey_configureMysqli(): void
{
    static $configured = false;

    if ($configured) {
        return;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $configured = true;
}

function sirey_setLastDbError(int $errno = 0, string $message = ''): void
{
    $GLOBALS['sirey_last_db_errno'] = $errno;
    $GLOBALS['sirey_last_db_error'] = $message;
}

function sirey_lastDbErrno(): int
{
    return (int)($GLOBALS['sirey_last_db_errno'] ?? 0);
}

function sirey_lastDbError(): string
{
    return (string)($GLOBALS['sirey_last_db_error'] ?? '');
}

function sirey_getConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';

        $timezone = (string)($config['app']['timezone'] ?? 'Asia/Jakarta');
        date_default_timezone_set($timezone);
    }

    return $config;
}

function sirey_getConfigValue(string $path, mixed $default = null): mixed
{
    $value = sirey_getConfig();

    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }

        $value = $value[$key];
    }

    return $value;
}


// ================== DATABASE ==================

function sirey_getDatabase(): mysqli
{
    static $db = null;

    sirey_configureMysqli();

    if ($db === null) {
        $config = sirey_getConfig()['db'];

        $db = mysqli_connect(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );

        if (!$db) {
            sirey_setLastDbError(mysqli_connect_errno(), mysqli_connect_error());
            throw new RuntimeException('DB connection failed: ' . mysqli_connect_error());
        }

        mysqli_set_charset($db, $config['charset']);
    }

    sirey_setLastDbError();

    return $db;
}


// ================== QUERY ==================

/**
 * Prepare and execute a parameterised query.
 * Returns the executed statement, or false on failure.
 */
function sirey_query(string $sql, string $types = '', mixed ...$params): mysqli_stmt|false
{
    sirey_setLastDbError();

    $stmt = null;

    try {
        $db = sirey_getDatabase();
        $stmt = mysqli_prepare($db, $sql);

        if (!$stmt) {
            sirey_setLastDbError(mysqli_errno($db), mysqli_error($db));
            return false;
        }

        if ($types !== '' && !empty($params) && !mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            sirey_setLastDbError(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        if (!mysqli_stmt_execute($stmt)) {
            sirey_setLastDbError(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        return $stmt;
    } catch (Throwable $e) {
        if ($stmt instanceof mysqli_stmt) {
            mysqli_stmt_close($stmt);
        }

        $db = $db ?? null;
        $errno = (int)$e->getCode();
        $message = $e->getMessage();

        if ($errno <= 0 && $db instanceof mysqli) {
            $errno = mysqli_errno($db);
            $message = mysqli_error($db) ?: $message;
        }

        sirey_setLastDbError($errno, $message);
        error_log('sirey_query: ' . $message);
        return false;
    }
}


/**
 * Fetch a single row as an associative array.
 */
function sirey_fetch(mysqli_stmt|false $stmt): ?array
{
    if (!$stmt) {
        error_log('sirey_fetch: stmt is false');
        return null;
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        error_log('sirey_fetch: get_result failed - ' . mysqli_error(sirey_getDatabase()));
        mysqli_stmt_close($stmt);
        return null;
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ?: null;
}


/**
 * Fetch all rows as an array of associative arrays.
 */
function sirey_fetchAll(mysqli_stmt|false $stmt): array
{
    if (!$stmt) {
        error_log('sirey_fetchAll: stmt is false');
        return [];
    }

    $result = mysqli_stmt_get_result($stmt);

    if (!$result) {
        error_log('sirey_fetchAll: get_result failed - ' . mysqli_error(sirey_getDatabase()));
        mysqli_stmt_close($stmt);
        return [];
    }

    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}


/**
 * Execute a write query (INSERT, UPDATE, DELETE).
 * Returns affected row count, or -1 on failure.
 */
function sirey_execute(string $sql, string $types = '', mixed ...$params): int
{
    $stmt = sirey_query($sql, $types, ...$params);

    if (!$stmt) {
        return -1;
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $affected;
}


/**
 * Returns the auto-increment ID of the last INSERT.
 */
function sirey_lastInsertId(): int
{
    return (int)mysqli_insert_id(sirey_getDatabase());
}
