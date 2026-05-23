<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message']]);
    }
});

session_start();
ob_end_clean();

$root     = dirname(__DIR__);
$dbConfig = $root . '/config/database.php';
if (!file_exists($dbConfig)) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'config/database.php not found.']);
    exit;
}
require_once $dbConfig;
$__ah = $root . '/config/audit_helper.php';
if (file_exists($__ah)) { require_once $__ah; }
elseif (!function_exists('audit_log')) { function audit_log() {} }

$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$isCron  = ($action === 'auto_backup' && php_sapi_name() === 'cli');

$isAdmin = isset($_SESSION['admin_id']) && (
    !isset($_SESSION['role']) ||
    in_array($_SESSION['role'], ['owner','admin','superadmin'])
);

if ($action === 'download' || $action === 'export_sales') {
    if (!$isAdmin) { http_response_code(403); exit('Unauthorized'); }
} else {
    header('Content-Type: application/json');
    if (!$isAdmin && !$isCron) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Unauthorized.']);
        exit;
    }
}

$db   = new Database();
$conn = $db->connect();

$admin_id  = (int)($_SESSION['admin_id'] ?? 0);
$backupDir = $root . '/backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$indexFile = $backupDir . '/index.json';

// ── DB credentials ────────────────────────────────────────
function getDbCreds(string $root): array {
    $src = file_get_contents($root . '/config/database.php');
    preg_match('/\$host\s*=\s*["\']([^"\']+)["\']/',     $src, $h);
    preg_match('/\$dbname\s*=\s*["\']([^"\']+)["\']/',   $src, $d);
    preg_match('/\$username\s*=\s*["\']([^"\']+)["\']/', $src, $u);
    preg_match('/\$password\s*=\s*["\']([^"\']*)["\']/s', $src, $p);
    return [
        'host'   => $h[1] ?? 'localhost',
        'dbname' => $d[1] ?? 'ipos_db',
        'user'   => $u[1] ?? 'root',
        'pass'   => $p[1] ?? '',
    ];
}

// ── Full SQL dump ─────────────────────────────────────────
function dumpDatabase(PDO $conn, array $creds): string {
    $sql  = "-- iPOS SQL Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Database: {$creds['dbname']}\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n";
        $rows = $conn->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $cols   = '`' . implode('`, `', array_keys($rows[0])) . '`';
            foreach (array_chunk($rows, 500) as $chunk) {
                $vals = [];
                foreach ($chunk as $row) {
                    $esc    = array_map(fn($v) => $v === null ? 'NULL' : $conn->quote((string)$v), array_values($row));
                    $vals[] = '(' . implode(', ', $esc) . ')';
                }
                $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n";
            }
            $sql .= "\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ── Index helpers ─────────────────────────────────────────
function readIndex(string $f): array {
    if (!file_exists($f)) return [];
    return json_decode(file_get_contents($f), true) ?? [];
}
function saveIndex(string $f, array $idx): void {
    usort($idx, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
    file_put_contents($f, json_encode($idx, JSON_PRETTY_PRINT));
}

// ══════════════════════════════════════════════════════════
//  PURE-PHP ZIP BUILDER (no ZipArchive extension required)
// ══════════════════════════════════════════════════════════
function buildPureZip(array $files): string {
    // $files = [ 'filename.xml' => 'content string', ... ]
    $localData  = '';
    $centralDir = '';
    $offset     = 0;
    $count      = 0;
    $dosTime    = 0x4A210000; // fixed timestamp (avoids date extension dependency)

    foreach ($files as $name => $data) {
        $nameBytes = $name;
        $nameLen   = strlen($nameBytes);
        $dataLen   = strlen($data);
        $crc       = crc32($data);

        $dosTimeL = $dosTime & 0xFFFF;        // low 16 bits = time
        $dosTimeH = ($dosTime >> 16) & 0xFFFF; // high 16 bits = date

        // Local file header (30 bytes fixed) + filename + data
        $lh = pack('VvvvvvVVVvv',
            0x04034b50, // signature
            20,          // version needed
            0,           // general flags
            0,           // compression method (stored)
            $dosTimeL,   // last mod file time (2 bytes)
            $dosTimeH,   // last mod file date (2 bytes)
            $crc,        // crc-32
            $dataLen,    // compressed size
            $dataLen,    // uncompressed size
            $nameLen,    // filename length
            0            // extra field length
        ) . $nameBytes . $data;

        // Central directory entry (46 bytes fixed) + filename
        $cd = pack('VvvvvvvVVVvvvvvVV',
            0x02014b50, // signature
            0x031E,     // version made by
            20,         // version needed
            0,          // general flags
            0,          // compression
            $dosTimeL,  // last mod file time (2 bytes)
            $dosTimeH,  // last mod file date (2 bytes)
            $crc,
            $dataLen,   // compressed size
            $dataLen,   // uncompressed size
            $nameLen,   // filename length
            0,          // extra field length
            0,          // file comment length
            0,          // disk number start
            0,          // internal file attributes
            0,          // external file attributes (4 bytes)
            $offset     // offset of local header (4 bytes)
        ) . $nameBytes;

        $localData  .= $lh;
        $centralDir .= $cd;
        $offset     += strlen($lh);
        $count++;
    }

    // End of central directory record
    $eocd = pack('VvvvvVVv',
        0x06054b50, // signature
        0,          // disk number
        0,          // disk with central dir
        $count,     // entries on this disk
        $count,     // total entries
        strlen($centralDir),
        $offset,    // offset of central dir
        0           // comment length
    );

    return $localData . $centralDir . $eocd;
}

// ══════════════════════════════════════════════════════════
//  XLSX WRITER (native PHP, no ZipArchive, no library)
// ══════════════════════════════════════════════════════════
function buildXlsx(array $sheets): string {
    // Each sheet: ['name'=>string, 'headers'=>[], 'rows'=>[[],...]

    $sharedStrings = [];
    $sharedIdx     = [];

    $worksheetXmls = [];
    $sheetNames    = [];

    foreach ($sheets as $sheet) {
        $name    = $sheet['name'];
        $headers = $sheet['headers'] ?? [];
        $rows    = $sheet['rows']    ?? [];
        $sheetNames[] = $name;

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Header row (style 1 = bold)
        if ($headers) {
            $xml .= '<row r="1">';
            foreach ($headers as $ci => $h) {
                $col  = xlsxColLetter($ci);
                $cell = $col . '1';
                $si   = xlsxSharedStr((string)$h, $sharedStrings, $sharedIdx);
                $xml .= "<c r=\"{$cell}\" t=\"s\" s=\"1\"><v>{$si}</v></c>";
            }
            $xml .= '</row>';
        }

        // Data rows
        foreach ($rows as $ri => $row) {
            $rowNum = $ri + 2;
            $xml .= "<row r=\"{$rowNum}\">";
            $vals = array_values($row);
            foreach ($vals as $ci => $v) {
                $col  = xlsxColLetter($ci);
                $cell = $col . $rowNum;
                if ($v === null || $v === '') {
                    $xml .= "<c r=\"{$cell}\"/>";
                } elseif (is_numeric($v) && !preg_match('/^0\d/', (string)$v)) {
                    $xml .= "<c r=\"{$cell}\"><v>" . htmlspecialchars((string)$v, ENT_XML1) . "</v></c>";
                } else {
                    $si   = xlsxSharedStr((string)$v, $sharedStrings, $sharedIdx);
                    $xml .= "<c r=\"{$cell}\" t=\"s\"><v>{$si}</v></c>";
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        $worksheetXmls[] = $xml;
    }

    // Shared strings XML
    $ssXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
    }
    $ssXml .= '</sst>';

    // Styles XML
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>';

    // Workbook XML
    $wbXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $wbXml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    foreach ($sheetNames as $i => $sn) {
        $wbXml .= '<sheet name="' . htmlspecialchars($sn, ENT_XML1) . '" sheetId="' . ($i+1) . '" r:id="rId' . ($i+1) . '"/>';
    }
    $wbXml .= '</sheets></workbook>';

    // Workbook rels
    $wbRels  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sheetNames as $i => $sn) {
        $wbRels .= '<Relationship Id="rId' . ($i+1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i+1) . '.xml"/>';
    }
    $wbRels .= '<Relationship Id="rId' . (count($sheetNames)+1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
    $wbRels .= '<Relationship Id="rId' . (count($sheetNames)+2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $wbRels .= '</Relationships>';

    // [Content_Types].xml
    $ctXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $ctXml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $ctXml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $ctXml .= '<Default Extension="xml" ContentType="application/xml"/>';
    $ctXml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    foreach ($sheetNames as $i => $sn) {
        $ctXml .= '<Override PartName="/xl/worksheets/sheet' . ($i+1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $ctXml .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
    $ctXml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $ctXml .= '</Types>';

    // _rels/.rels
    $relsXml  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
    $relsXml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $relsXml .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
    $relsXml .= '</Relationships>';

    // Assemble all files for the ZIP
    $zipFiles = [
        '[Content_Types].xml'       => $ctXml,
        '_rels/.rels'               => $relsXml,
        'xl/workbook.xml'           => $wbXml,
        'xl/_rels/workbook.xml.rels'=> $wbRels,
        'xl/sharedStrings.xml'      => $ssXml,
        'xl/styles.xml'             => $stylesXml,
    ];
    foreach ($worksheetXmls as $i => $wsXml) {
        $zipFiles['xl/worksheets/sheet' . ($i+1) . '.xml'] = $wsXml;
    }

    return buildPureZip($zipFiles);
}

function xlsxSharedStr(string $s, array &$sharedStrings, array &$sharedIdx): int {
    if (!isset($sharedIdx[$s])) {
        $sharedIdx[$s] = count($sharedStrings);
        $sharedStrings[] = $s;
    }
    return $sharedIdx[$s];
}

function xlsxColLetter(int $n): string {
    $s = '';
    $n++;
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

function tableExists(PDO $conn, string $table): bool {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table");
        $stmt->execute([':table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function moneyValue($value): string {
    return number_format((float)$value, 2, '.', '');
}

function yesNo($value): string {
    return ((int)$value === 1) ? 'Yes' : 'No';
}

function friendlyDate($value): string {
    if (!$value) return '';
    $ts = strtotime((string)$value);
    return $ts ? date('Y-m-d h:i A', $ts) : (string)$value;
}

function fetchRows(PDO $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addErrorSheet(array &$sheets, string $name, Exception $e): void {
    $sheets[] = ['name' => $name, 'headers' => ['Notice'], 'rows' => [[$e->getMessage()]]];
}

function buildOwnerBackupSheets(PDO $conn, int $admin_id, string $label, string $backupType, ?string $from = null, ?string $to = null, ?array $sections = null): array {
    $sections = $sections ?: ['store_info','staff','inventory','orders','order_items','payments','revenue','deleted_items','activity'];
    $has = fn(string $section): bool => in_array($section, $sections, true);
    $dateFilter = ($from && $to) ? " AND DATE(o.created_at) BETWEEN :from AND :to" : "";
    $dateParams = ($from && $to) ? [':from' => $from, ':to' => $to] : [];
    $sheets = [];

    try {
        $storeRows = fetchRows($conn, "SELECT admin_id, fastfood_name, fullname, username, email, created_at, last_login FROM admins WHERE admin_id = :admin_id", [':admin_id' => $admin_id]);
        $store = $storeRows[0] ?? [];
        $menuCount = fetchRows($conn, "SELECT COUNT(*) AS c FROM menu_items WHERE admin_id = :admin_id", [':admin_id' => $admin_id])[0]['c'] ?? 0;
        $staffCount = fetchRows($conn, "SELECT COUNT(*) AS c FROM staffs WHERE admin_id = :admin_id", [':admin_id' => $admin_id])[0]['c'] ?? 0;
        $orderCount = fetchRows($conn, "SELECT COUNT(*) AS c FROM orders WHERE admin_id = :admin_id", [':admin_id' => $admin_id])[0]['c'] ?? 0;
        $sheets[] = [
            'name' => 'Backup Summary',
            'headers' => ['Field', 'Value'],
            'rows' => [
                ['Store Name', $store['fastfood_name'] ?? ''],
                ['Owner Name', $store['fullname'] ?? ''],
                ['Username', $store['username'] ?? ''],
                ['Email', $store['email'] ?? ''],
                ['Backup Label', $label],
                ['Backup Type', $backupType],
                ['Generated At', date('Y-m-d h:i A')],
                ['Date Range', ($from && $to) ? "{$from} to {$to}" : 'All dates'],
                ['Menu Items Included', $menuCount],
                ['Staff Members Included', $staffCount],
                ['Orders Included', $orderCount],
                ['Privacy Note', 'This workbook contains only this store owner account data. Other iPOS owners are excluded.'],
            ],
        ];
    } catch (Exception $e) {
        addErrorSheet($sheets, 'Backup Summary', $e);
    }

    if ($has('store_info')) {
        try {
            $rows = fetchRows($conn, "SELECT fastfood_name, fullname, username, email, created_at, last_login FROM admins WHERE admin_id = :admin_id", [':admin_id' => $admin_id]);
            $store = $rows[0] ?? [];
            $sheets[] = [
                'name' => 'Store Profile',
                'headers' => ['Field', 'Value'],
                'rows' => [
                    ['Store Name', $store['fastfood_name'] ?? ''],
                    ['Owner Name', $store['fullname'] ?? ''],
                    ['Login Username', $store['username'] ?? ''],
                    ['Email Address', $store['email'] ?? ''],
                    ['Account Created', friendlyDate($store['created_at'] ?? '')],
                    ['Last Owner Login', friendlyDate($store['last_login'] ?? '')],
                ],
            ];
        } catch (Exception $e) { addErrorSheet($sheets, 'Store Profile', $e); }
    }

    if ($has('staff')) {
        try {
            $employmentCol = columnExists($conn, 'staffs', 'employment_type') ? 'employment_type' : "'Full-time' AS employment_type";
            $onlineCol = columnExists($conn, 'staffs', 'is_online') ? 'is_online' : "0 AS is_online";
            $lastLoginCol = columnExists($conn, 'staffs', 'last_login_at') ? 'last_login_at' : "NULL AS last_login_at";
           $staffCodeCol = columnExists($conn, 'staffs', 'staff_code') ? 'staff_code' : "'' AS staff_code";
$staffRows = fetchRows($conn, "SELECT staff_code, fullname, role, status, {$employmentCol}, shift_start, shift_end, {$lastLoginCol}, {$onlineCol}, created_at FROM staffs WHERE admin_id = :admin_id ORDER BY fullname", [':admin_id' => $admin_id]);
            $rows = [];
            foreach ($staffRows as $r) {
                $rows[] = [$r['staff_code'] ?? '—', $r['fullname'] ?? '', $r['role'] ?? '', $r['status'] ?? '', $r['employment_type'] ?? 'Full-time', $r['shift_start'] ?? '', $r['shift_end'] ?? '', yesNo($r['is_online'] ?? 0), friendlyDate($r['last_login_at'] ?? ''), friendlyDate($r['created_at'] ?? '')];
            }
            $sheets[] = ['name' => 'Staff Directory', 'headers' => ['Staff Code','Staff Name','Role','Status','Employment Type','Shift Start','Shift End','Currently Online','Last Login','Added On'], 'rows' => $rows];

            if (tableExists($conn, 'staff_sessions')) {
                $hasLate = columnExists($conn, 'staff_sessions', 'late_minutes');
                $lateCol = $hasLate ? 'ss.late_minutes' : '0';
                $sessionRows = fetchRows($conn, "
                    SELECT s.fullname, s.role, ss.login_at, ss.logout_at, ss.duration_minutes, {$lateCol} AS late_minutes
                    FROM staff_sessions ss
                    JOIN staffs s ON s.staff_id = ss.staff_id
                    WHERE ss.admin_id = :admin_id
                    ORDER BY ss.login_at DESC
                    LIMIT 1000
                ", [':admin_id' => $admin_id]);
                $rows = [];
                foreach ($sessionRows as $r) {
                    $rows[] = [$r['fullname'], $r['role'], friendlyDate($r['login_at']), friendlyDate($r['logout_at']), $r['duration_minutes'] ?? '', $r['late_minutes'] ?? 0];
                }
                $sheets[] = ['name' => 'Staff Attendance', 'headers' => ['Staff Name','Role','Login Time','Logout Time','Minutes Worked','Minutes Late'], 'rows' => $rows];
            }
        } catch (Exception $e) { addErrorSheet($sheets, 'Staff Directory', $e); }
    }

    if ($has('inventory')) {
        try {
            $where = "WHERE admin_id = :admin_id";
            if (columnExists($conn, 'menu_items', 'deleted_at')) $where .= " AND deleted_at IS NULL";
            $menuRows = fetchRows($conn, "SELECT item_name, description, category, price, stock_quantity, is_available, image_url, created_at, updated_at FROM menu_items {$where} ORDER BY category, item_name", [':admin_id' => $admin_id]);
            $rows = [];
            foreach ($menuRows as $r) {
                $rows[] = [$r['item_name'], $r['description'], $r['category'], moneyValue($r['price']), $r['stock_quantity'], yesNo($r['is_available']), $r['image_url'], friendlyDate($r['created_at']), friendlyDate($r['updated_at'])];
            }
            $sheets[] = ['name' => 'Menu Inventory', 'headers' => ['Item Name','Description','Category','Price','Stock Quantity','Available to Sell','Image Path','Created On','Last Updated'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Menu Inventory', $e); }
    }

    if ($has('orders')) {
        try {
            $cashierCol = columnExists($conn, 'orders', 'cashier_name') ? 'o.cashier_name' : "NULL AS cashier_name";
            $kitchenCol = columnExists($conn, 'orders', 'kitchen_name') ? 'o.kitchen_name' : "NULL AS kitchen_name";
            $orderRows = fetchRows($conn, "
                SELECT o.order_id, o.queue_number, o.order_status, o.total_amount, {$cashierCol}, {$kitchenCol},
                       c.name AS customer_name, c.table_number,
                       p.payment_method, p.amount_paid, p.change_given, p.receipt_number, p.payment_status,
                       GROUP_CONCAT(CONCAT(oi.item_name, ' x', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') AS items,
                       o.created_at
                FROM orders o
                LEFT JOIN customers c ON c.customer_id = o.customer_id
                LEFT JOIN payments p ON p.order_id = o.order_id
                LEFT JOIN order_items oi ON oi.order_id = o.order_id
                WHERE o.admin_id = :admin_id {$dateFilter}
                GROUP BY o.order_id
                ORDER BY o.created_at DESC
            ", array_merge([':admin_id' => $admin_id], $dateParams));
            $rows = [];
            foreach ($orderRows as $r) {
                $rows[] = [$r['queue_number'], friendlyDate($r['created_at']), $r['order_status'], $r['customer_name'] ?: 'Walk-in', $r['table_number'] ?: '', $r['items'] ?: '', moneyValue($r['total_amount']), moneyValue($r['amount_paid']), moneyValue($r['change_given']), $r['payment_method'] ?: '', $r['payment_status'] ?: '', $r['receipt_number'] ?: '', $r['cashier_name'] ?: 'Before tracking', in_array($r['order_status'], ['Served', 'Completed'], true) ? ($r['kitchen_name'] ?: 'Before tracking') : 'Not served yet'];
            }
            $sheets[] = ['name' => 'Orders', 'headers' => ['Queue Number','Date and Time','Status','Customer','Table','Items Ordered','Order Total','Cash Paid','Change Given','Payment Method','Payment Status','Receipt Number','Cashier','Kitchen Manager'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Orders', $e); }
    }

    if ($has('order_items')) {
        try {
            $itemRows = fetchRows($conn, "
                SELECT o.queue_number, o.created_at, oi.item_name, oi.price, oi.quantity, oi.subtotal
                FROM order_items oi
                JOIN orders o ON o.order_id = oi.order_id
                WHERE o.admin_id = :admin_id {$dateFilter}
                ORDER BY o.created_at DESC, oi.order_item_id ASC
            ", array_merge([':admin_id' => $admin_id], $dateParams));
            $rows = [];
            foreach ($itemRows as $r) {
                $rows[] = [$r['queue_number'], friendlyDate($r['created_at']), $r['item_name'], moneyValue($r['price']), $r['quantity'], moneyValue($r['subtotal'])];
            }
            $sheets[] = ['name' => 'Order Items', 'headers' => ['Queue Number','Order Date','Item Name','Item Price','Quantity','Line Total'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Order Items', $e); }
    }

    if ($has('payments')) {
        try {
            $paymentRows = fetchRows($conn, "
                SELECT o.queue_number, o.created_at, p.payment_method, p.amount_paid, p.change_given, p.receipt_number, p.payment_status, p.payment_date
                FROM payments p
                JOIN orders o ON o.order_id = p.order_id
                WHERE o.admin_id = :admin_id {$dateFilter}
                ORDER BY p.payment_date DESC
            ", array_merge([':admin_id' => $admin_id], $dateParams));
            $rows = [];
            foreach ($paymentRows as $r) {
                $rows[] = [$r['queue_number'], friendlyDate($r['created_at']), $r['payment_method'], moneyValue($r['amount_paid']), moneyValue($r['change_given']), $r['receipt_number'], $r['payment_status'], friendlyDate($r['payment_date'])];
            }
            $sheets[] = ['name' => 'Payments', 'headers' => ['Queue Number','Order Date','Payment Method','Amount Paid','Change Given','Receipt Number','Payment Status','Payment Date'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Payments', $e); }
    }

    if ($has('revenue')) {
        try {
            $revenueRows = fetchRows($conn, "
                SELECT DATE(o.created_at) AS sale_date,
                       COUNT(DISTINCT o.order_id) AS total_orders,
                       COUNT(DISTINCT CASE WHEN o.order_status IN ('Served','Completed') THEN o.order_id END) AS completed_orders,
                       COUNT(DISTINCT CASE WHEN o.order_status='Cancelled' THEN o.order_id END) AS cancelled_orders,
                       COALESCE(SUM(CASE WHEN o.order_status IN ('Served','Completed') THEN o.total_amount END),0) AS net_sales,
                       COALESCE(SUM(CASE WHEN o.order_status IN ('Served','Completed') THEN p.amount_paid END),0) AS cash_received,
                       COALESCE(SUM(CASE WHEN o.order_status IN ('Served','Completed') THEN p.change_given END),0) AS change_given
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.order_id
                WHERE o.admin_id = :admin_id {$dateFilter}
                GROUP BY DATE(o.created_at)
                ORDER BY sale_date DESC
            ", array_merge([':admin_id' => $admin_id], $dateParams));
            $rows = [];
            foreach ($revenueRows as $r) {
                $rows[] = [$r['sale_date'], $r['total_orders'], $r['completed_orders'], $r['cancelled_orders'], moneyValue($r['net_sales']), moneyValue($r['cash_received']), moneyValue($r['change_given'])];
            }
            $sheets[] = ['name' => 'Revenue by Day', 'headers' => ['Date','Total Orders','Served Orders','Cancelled Orders','Net Sales','Cash Received','Change Given'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Revenue by Day', $e); }
    }

    if ($has('deleted_items')) {
        try {
            $rows = [];
            if (tableExists($conn, 'deleted_menu_items')) {
                $items = fetchRows($conn, "SELECT item_name, category, price, stock_quantity, deleted_by, deleted_at FROM deleted_menu_items WHERE admin_id = :admin_id ORDER BY deleted_at DESC", [':admin_id' => $admin_id]);
                foreach ($items as $r) {
                    $rows[] = ['Menu Item', $r['item_name'], $r['category'], moneyValue($r['price']), $r['stock_quantity'], $r['deleted_by'], friendlyDate($r['deleted_at'])];
                }
            }
            if (tableExists($conn, 'deleted_staffs')) {
                $staff = fetchRows($conn, "SELECT fullname, role, status, deleted_by, deleted_at FROM deleted_staffs WHERE admin_id = :admin_id ORDER BY deleted_at DESC", [':admin_id' => $admin_id]);
                foreach ($staff as $r) {
                    $rows[] = ['Staff', $r['fullname'], $r['role'], '', $r['status'], $r['deleted_by'], friendlyDate($r['deleted_at'])];
                }
            }
            $sheets[] = ['name' => 'Deleted Records', 'headers' => ['Record Type','Name','Category or Role','Price','Stock or Status','Deleted By','Deleted On'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Deleted Records', $e); }
    }

    if ($has('activity') && tableExists($conn, 'audit_log')) {
        try {
            $activity = fetchRows($conn, "SELECT actor_name, action, target_type, target_label, detail, created_at FROM audit_log WHERE admin_id = :admin_id ORDER BY created_at DESC LIMIT 1000", [':admin_id' => $admin_id]);
            $rows = [];
            foreach ($activity as $r) {
                $rows[] = [$r['actor_name'], $r['action'], $r['target_type'], $r['target_label'], $r['detail'], friendlyDate($r['created_at'])];
            }
            $sheets[] = ['name' => 'Activity Log', 'headers' => ['Actor','Action','Record Type','Record Name','Details','Date and Time'], 'rows' => $rows];
        } catch (Exception $e) { addErrorSheet($sheets, 'Activity Log', $e); }
    }

    return $sheets;
}

// ══════════════════════════════════════════════════════════
//  CREATE BACKUP
// ══════════════════════════════════════════════════════════
if ($action === 'create' || $action === 'auto_backup') {
    try {
        $label = trim($_POST['label'] ?? ($action === 'auto_backup' ? 'auto' : 'manual'));
        $label = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $label) ?: 'backup';
        $slug  = preg_replace('/\s+/', '_', $label);
        $isSuperadmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';

        if ($isSuperadmin) {
            $creds    = getDbCreds($root);
            $sqlDump  = dumpDatabase($conn, $creds);
            $filename = 'backup_' . $slug . '_' . date('Ymd_His') . '.sql';
            $filepath = $backupDir . '/' . $filename;
            file_put_contents($filepath, $sqlDump);
            $tables = count($conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
            $type   = 'sql';
            $scope  = 'full database';
        } else {
            $sheets = buildOwnerBackupSheets($conn, $admin_id, $label, $action === 'auto_backup' ? 'Auto' : 'Manual');
            $xlsx     = buildXlsx($sheets);
            $filename = 'backup_' . $slug . '_' . date('Ymd_His') . '.xlsx';
            $filepath = $backupDir . '/' . $filename;
            file_put_contents($filepath, $xlsx);
            $tables = count($sheets);
            $type   = $action === 'auto_backup' ? 'auto' : 'manual';
            $scope  = 'current admin only';
        }

        $idx   = readIndex($indexFile);
        $idx[] = [
            'filename'   => $filename,
            'label'      => $label,
            'type'       => $type,
            'admin_id'   => $admin_id,
            'created_at' => date('Y-m-d H:i:s'),
            'size'       => filesize($filepath),
            'tables'     => $tables,
            'created_by' => $_SESSION['username'] ?? ($isCron ? 'cron' : 'admin'),
        ];
        saveIndex($indexFile, $idx);

        if ($action === 'auto_backup') {
            file_put_contents($backupDir . '/.last_auto', date('Y-m-d H:i:s'));
        }

        audit_log($conn, $_SESSION, 'backup_created', 'backup', null, $filename,
            "Label: {$label}, Type: " . ($action==='auto_backup'?'auto':'manual') . ", Scope: {$scope}");
        echo json_encode(['success'=>true,'filename'=>$filename,'message'=>"Backup \"{$label}\" created."]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>'Backup failed: '.$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
//  CHECK / TRIGGER AUTO-BACKUP
// ══════════════════════════════════════════════════════════
if ($action === 'check_auto') {
    $lastFile = $backupDir . '/.last_auto';
    $lastAuto = file_exists($lastFile) ? file_get_contents($lastFile) : null;
    $shouldRun = !$lastAuto || (time() - strtotime($lastAuto)) >= 86400;
    if ($shouldRun) {
        try {
            $idx = readIndex($indexFile);
            $isSuperadmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
            if ($isSuperadmin) {
                $creds    = getDbCreds($root);
                $sqlDump  = dumpDatabase($conn, $creds);
                $filename = 'backup_auto_' . date('Ymd_His') . '.sql';
                $filepath = $backupDir . '/' . $filename;
                file_put_contents($filepath, $sqlDump);
                $tables   = count($conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
                $idx[]    = ['filename'=>$filename,'label'=>'auto','type'=>'sql','admin_id'=>$admin_id,'created_at'=>date('Y-m-d H:i:s'),'size'=>filesize($filepath),'tables'=>$tables,'created_by'=>'system'];
            } else {
                $sheets = buildOwnerBackupSheets($conn, $admin_id, 'auto', 'Auto');
                $xlsx     = buildXlsx($sheets);
                $filename = 'backup_auto_' . date('Ymd_His') . '.xlsx';
                $filepath = $backupDir . '/' . $filename;
                file_put_contents($filepath, $xlsx);
                $idx   = readIndex($indexFile);
                $idx[] = ['filename'=>$filename,'label'=>'auto','type'=>'auto','admin_id'=>$admin_id,'created_at'=>date('Y-m-d H:i:s'),'size'=>filesize($filepath),'tables'=>count($sheets),'created_by'=>'system'];
            }
            saveIndex($indexFile, $idx);
            file_put_contents($backupDir . '/.last_auto', date('Y-m-d H:i:s'));
            echo json_encode(['success'=>true,'triggered'=>true,'filename'=>$filename,'message'=>'Auto-backup created.']);
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'triggered'=>false,'message'=>'Auto-backup failed: '.$e->getMessage()]);
        }
    } else {
        $nextRun = date('Y-m-d H:i:s', strtotime($lastAuto) + 86400);
        echo json_encode(['success'=>true,'triggered'=>false,'last_auto'=>$lastAuto,'next_auto'=>$nextRun]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
//  LIST BACKUPS
// ══════════════════════════════════════════════════════════
if ($action === 'list') {
    $period = $_GET['period'] ?? 'all';
    $idx    = array_values(array_filter(readIndex($indexFile), fn($b) => isset($b['admin_id']) && (int)$b['admin_id'] === $admin_id));
    $now    = time();
    $filtered = array_filter($idx, function($b) use ($period, $now) {
        if ($period === 'all') return true;
        $ts = strtotime($b['created_at']);
        return match($period) {
            'today'   => date('Y-m-d', $ts) === date('Y-m-d'),
            'week'    => $ts >= strtotime('-7 days'),
            'month'   => $ts >= strtotime('-30 days'),
            '6months' => $ts >= strtotime('-6 months'),
            'year'    => $ts >= strtotime('-1 year'),
            default   => true,
        };
    });
    foreach ($filtered as &$b) {
        $b['exists'] = file_exists($backupDir . '/' . $b['filename']);
        if ($b['exists']) $b['size'] = filesize($backupDir . '/' . $b['filename']);
    }
    unset($b);
    $all       = $idx;
    $allExist  = array_filter($all, fn($b) => file_exists($backupDir . '/' . $b['filename']));
    $totalSize = array_sum(array_map(fn($b) => $b['size'] ?? 0, $allExist));
    $latest    = $all ? $all[0]['created_at'] : null;
    $lastAuto  = file_exists($backupDir . '/.last_auto') ? file_get_contents($backupDir . '/.last_auto') : null;
    $nextAuto  = $lastAuto ? date('Y-m-d H:i:s', strtotime($lastAuto) + 86400) : null;
    echo json_encode([
        'success'    => true,
        'backups'    => array_values($filtered),
        'total'      => count($all),
        'total_size' => $totalSize,
        'latest'     => $latest,
        'last_auto'  => $lastAuto,
        'next_auto'  => $nextAuto,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════
//  DOWNLOAD BACKUP (SQL or XLSX files)
// ══════════════════════════════════════════════════════════
if ($action === 'download') {
    $filename = basename($_GET['file'] ?? '');
    $filepath = $backupDir . '/' . $filename;
    $owned = false;
    foreach (readIndex($indexFile) as $b) {
        if (($b['filename'] ?? '') === $filename && isset($b['admin_id']) && (int)$b['admin_id'] === $admin_id) {
            $owned = true;
            break;
        }
    }
    if (!$owned) {
        header('Content-Type: text/plain'); http_response_code(403); echo 'Unauthorized backup file.'; exit;
    }
    if (!$filename || !file_exists($filepath)) {
        header('Content-Type: text/plain'); http_response_code(404); echo 'File not found.'; exit;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = $ext === 'xlsx'
        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
//  RESTORE BACKUP
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'restore') {
    try {
        $sql = '';
        if (!empty($_POST['file'])) {
            $filename = basename($_POST['file']);
            $filepath = $backupDir . '/' . $filename;
            if (!file_exists($filepath) || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'sql') {
                throw new Exception('Backup file not found or not a SQL file.');
            }
            $sql = file_get_contents($filepath);
            $restoreSource = $filename;
        } elseif (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $filename = $_FILES['backup_file']['name'];
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'sql') {
                throw new Exception('Only .sql files are accepted.');
            }
            $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
            $restoreSource = $filename;
        } else {
            throw new Exception('No backup source provided.');
        }

        if (trim($sql) === '') {
            throw new Exception('Backup file is empty.');
        }

        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)), fn($s) => $s !== '');

        $conn->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            if (stripos($stmt, 'INSERT INTO `audit_log`') === 0) {
                $stmt = preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $stmt);
            }
            $conn->exec($stmt);
        }
        $conn->exec('SET FOREIGN_KEY_CHECKS=1');

        audit_log($conn, $_SESSION, 'backup_restored', 'backup', null, $restoreSource, "Database restored from {$restoreSource}");

        echo json_encode(['success' => true, 'message' => 'Restore completed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════
//  RENAME BACKUP FILE
// ══════════════════════════════════════════════════════════════════════════
if ($action === 'rename') {
    try {
        $filename = basename($_POST['file'] ?? '');
        $newName  = trim($_POST['new_name'] ?? '');

        if ($filename === '' || $newName === '') {
            throw new Exception('Invalid source or target filename.');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $newName = preg_replace('/\.(' . preg_quote($ext, '/') . ')$/i', '', $newName);
        $newName = preg_replace('/[^a-zA-Z0-9_\- ]/', '_', $newName);
        if ($newName === '') {
            throw new Exception('Invalid new filename.');
        }

        $newFilename = $newName . '.' . $ext;
        $currentPath = $backupDir . '/' . $filename;
        $newPath     = $backupDir . '/' . $newFilename;

        if (!file_exists($currentPath)) {
            throw new Exception('Original file does not exist.');
        }
        if ($newFilename !== $filename && file_exists($newPath)) {
            throw new Exception('A file with the new name already exists.');
        }

        if (!rename($currentPath, $newPath)) {
            throw new Exception('Unable to rename file.');
        }

        $idx = readIndex($indexFile);
        $updated = false;
        foreach ($idx as &$entry) {
            if (($entry['filename'] ?? '') === $filename && isset($entry['admin_id']) && (int)$entry['admin_id'] === $admin_id) {
                $entry['filename'] = $newFilename;
                $entry['label']    = $newName;
                $updated = true;
                break;
            }
        }
        unset($entry);

        if (!$updated) {
            throw new Exception('Unauthorized backup file.');
        }

        saveIndex($indexFile, $idx);

        echo json_encode(['success' => true, 'new_filename' => $newFilename, 'new_label' => $newName, 'message' => 'Backup renamed successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
//  DELETE BACKUP FILE
// ══════════════════════════════════════════════════════════
if ($action === 'delete') {
    $filename = basename($_POST['file'] ?? '');
    $filepath = $backupDir . '/' . $filename;
    if (!$filename) { echo json_encode(['success'=>false,'message'=>'Invalid file.']); exit; }
    $idx = readIndex($indexFile);
    $owned = false;
    foreach ($idx as $b) {
        if (($b['filename'] ?? '') === $filename && isset($b['admin_id']) && (int)$b['admin_id'] === $admin_id) {
            $owned = true;
            break;
        }
    }
    if (!$owned) { echo json_encode(['success'=>false,'message'=>'Unauthorized backup file.']); exit; }
    if (file_exists($filepath)) unlink($filepath);
    $idx = array_values(array_filter($idx, fn($b) => $b['filename'] !== $filename));
    saveIndex($indexFile, $idx);
    audit_log($conn, $_SESSION, 'backup_deleted', 'backup', null, $filename, "Deleted: {$filename}");
    echo json_encode(['success'=>true,'message'=>"Backup \"{$filename}\" deleted."]);
    exit;
}

// ══════════════════════════════════════════════════════════
//  ORDER STATS (Sales Summary tab)
// ══════════════════════════════════════════════════════════
if ($action === 'order_stats') {
    $period   = $_GET['period'] ?? 'all';
    $dateWhere = match($period) {
        'today'   => "AND DATE(o.created_at) = CURDATE()",
        'week'    => "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month'   => "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '6months' => "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)",
        'year'    => "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
        default   => '',
    };
    try {
        $row = $conn->query("
            SELECT
                COUNT(DISTINCT o.order_id) AS total_orders,
                COUNT(DISTINCT CASE WHEN o.order_status='Served'    THEN o.order_id END) AS completed,
                COUNT(DISTINCT CASE WHEN o.order_status='Cancelled' THEN o.order_id END) AS cancelled,
                COALESCE(SUM(CASE WHEN o.order_status='Served' THEN p.amount_paid END),0)    AS gross_revenue,
                COALESCE(SUM(CASE WHEN o.order_status='Served' THEN o.total_amount END),0)   AS net_sales,
                COALESCE(AVG(CASE WHEN o.order_status='Served' THEN o.total_amount END),0)   AS avg_order,
                COALESCE(SUM(CASE WHEN o.order_status='Served' THEN p.change_given END),0)   AS total_change
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.order_id
            WHERE o.admin_id = {$admin_id} {$dateWhere}
        ")->fetch(PDO::FETCH_ASSOC);

        $topItems = $conn->query("
            SELECT oi.item_name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS revenue
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id
            WHERE o.admin_id = {$admin_id} AND o.order_status='Served' {$dateWhere}
            GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        $byDay = $conn->query("
            SELECT DATE(o.created_at) AS day,
                   COALESCE(SUM(p.amount_paid),0) AS revenue,
                   COUNT(o.order_id) AS orders
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.order_id
            WHERE o.admin_id={$admin_id} AND o.order_status='Served' {$dateWhere}
            GROUP BY DATE(o.created_at) ORDER BY day DESC LIMIT 7
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>true,'stats'=>$row,'top_items'=>$topItems,'by_day'=>array_reverse($byDay)]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════
//  EXPORT SALES — Real .xlsx download (no ZipArchive needed)
// ══════════════════════════════════════════════════════════
if ($action === 'export_sales') {
    $types     = isset($_GET['types']) ? array_filter(explode(',', $_GET['types'])) : [];
    $date_from = $_GET['date_from'] ?? '2000-01-01';
    $date_to   = $_GET['date_to']   ?? date('Y-m-d');
    $from      = date('Y-m-d', strtotime($date_from));
    $to        = date('Y-m-d', strtotime($date_to));
    $qFrom     = $conn->quote($from);
    $qTo       = $conn->quote($to);

    $sections = [];
    foreach ($types as $type) {
        if (in_array($type, ['store_info','staff','inventory','orders','revenue','deleted_items'], true)) {
            $sections[] = $type;
        }
    }
    if (in_array('orders', $sections, true)) {
        $sections[] = 'order_items';
        $sections[] = 'payments';
    }
    $sections = array_values(array_unique($sections));
    $sheets = buildOwnerBackupSheets($conn, $admin_id, 'selected export', 'Download', $from, $to, $sections);

    if (empty($sheets)) {
        $sheets[] = ['name'=>'Export','headers'=>['Note'],'rows'=>[['No sections selected.']]];
    }

    $xlsx     = buildXlsx($sheets);
    $filename = 'iPOS_Backup_' . date('Ymd_His') . '.xlsx';

    // Save to backups folder and log
    $savePath = $backupDir . '/' . $filename;
    file_put_contents($savePath, $xlsx);
    $idx   = readIndex($indexFile);
    $idx[] = [
        'filename'   => $filename,
        'label'      => 'Excel Export (' . implode(', ', $types) . ')',
        'type'       => 'excel',
        'admin_id'   => $admin_id,
        'created_at' => date('Y-m-d H:i:s'),
        'size'       => strlen($xlsx),
        'tables'     => count($sheets),
        'created_by' => $_SESSION['username'] ?? 'admin',
    ];
    saveIndex($indexFile, $idx);
    audit_log($conn, $_SESSION, 'excel_export', 'backup', null, $filename,
        "Sections: " . implode(',', $types) . " | Range: {$from} to {$to}");

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('Cache-Control: no-cache');
    echo $xlsx;
    exit;
}

http_response_code(400);
echo json_encode(['success'=>false,'message'=>"Unknown action: \"{$action}\"."]);
