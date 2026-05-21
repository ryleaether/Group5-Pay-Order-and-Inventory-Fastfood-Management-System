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

// ══════════════════════════════════════════════════════════
//  CREATE BACKUP
// ══════════════════════════════════════════════════════════
if ($action === 'create' || $action === 'auto_backup') {
    try {
        $label = trim($_POST['label'] ?? ($action === 'auto_backup' ? 'auto' : 'manual'));
        $label = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $label) ?: 'backup';
        $slug  = preg_replace('/\s+/', '_', $label);

        // Build all-tables Excel backup
        $tables  = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sheets  = [];

        // Summary sheet
        $row = $conn->query("SELECT fastfood_name, username, email FROM admins WHERE admin_id={$admin_id}")->fetch(PDO::FETCH_ASSOC);
        $sheets[] = [
            'name'    => 'Summary',
            'headers' => ['Field', 'Value'],
            'rows'    => [
                ['Store Name',   $row['fastfood_name'] ?? ''],
                ['Admin',        $row['username']      ?? ''],
                ['Email',        $row['email']         ?? ''],
                ['Backup Label', $label],
                ['Backup Type',  $action === 'auto_backup' ? 'Auto' : 'Manual'],
                ['Generated At', date('Y-m-d H:i:s')],
                ['Tables',       count($tables)],
            ],
        ];

        // One sheet per table
        foreach ($tables as $table) {
            try {
                $rows = $conn->query("SELECT * FROM `{$table}` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
                $headers = $rows ? array_keys($rows[0]) : [];
                $sheetName = strlen($table) > 31 ? substr($table, 0, 31) : $table;
                $sheets[] = ['name' => $sheetName, 'headers' => $headers, 'rows' => $rows];
            } catch (Exception $te) {}
        }

        $xlsx     = buildXlsx($sheets);
        $filename = 'backup_' . $slug . '_' . date('Ymd_His') . '.xlsx';
        $filepath = $backupDir . '/' . $filename;
        file_put_contents($filepath, $xlsx);

        $idx   = readIndex($indexFile);
        $idx[] = [
            'filename'   => $filename,
            'label'      => $label,
            'type'       => $action === 'auto_backup' ? 'auto' : 'manual',
            'created_at' => date('Y-m-d H:i:s'),
            'size'       => filesize($filepath),
            'tables'     => count($tables),
            'created_by' => $_SESSION['username'] ?? ($isCron ? 'cron' : 'admin'),
        ];
        saveIndex($indexFile, $idx);

        if ($action === 'auto_backup') {
            file_put_contents($backupDir . '/.last_auto', date('Y-m-d H:i:s'));
        }

        audit_log($conn, $_SESSION, 'backup_created', 'backup', null, $filename,
            "Label: {$label}, Type: " . ($action==='auto_backup'?'auto':'manual') . ", Tables: " . count($tables));
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
            $tables   = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sheets   = [];
            $row = $conn->query("SELECT fastfood_name, username, email FROM admins WHERE admin_id={$admin_id}")->fetch(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Summary',
                'headers' => ['Field', 'Value'],
                'rows'    => [
                    ['Store Name', $row['fastfood_name'] ?? ''],
                    ['Admin',      $row['username']      ?? ''],
                    ['Backup Type','Auto'],
                    ['Generated',  date('Y-m-d H:i:s')],
                    ['Tables',     count($tables)],
                ],
            ];
            foreach ($tables as $table) {
                try {
                    $rows    = $conn->query("SELECT * FROM `{$table}` LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC);
                    $headers = $rows ? array_keys($rows[0]) : [];
                    $sheetName = strlen($table) > 31 ? substr($table, 0, 31) : $table;
                    $sheets[] = ['name' => $sheetName, 'headers' => $headers, 'rows' => $rows];
                } catch (Exception $te) {}
            }
            $xlsx     = buildXlsx($sheets);
            $filename = 'backup_auto_' . date('Ymd_His') . '.xlsx';
            $filepath = $backupDir . '/' . $filename;
            file_put_contents($filepath, $xlsx);
            $idx   = readIndex($indexFile);
            $idx[] = ['filename'=>$filename,'label'=>'auto','type'=>'auto','created_at'=>date('Y-m-d H:i:s'),'size'=>filesize($filepath),'tables'=>count($tables),'created_by'=>'system'];
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
    $idx    = readIndex($indexFile);
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
    $all       = readIndex($indexFile);
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

// ══════════════════════════════════════════════════════════
//  DELETE BACKUP FILE
// ══════════════════════════════════════════════════════════
if ($action === 'delete') {
    $filename = basename($_POST['file'] ?? '');
    $filepath = $backupDir . '/' . $filename;
    if (!$filename) { echo json_encode(['success'=>false,'message'=>'Invalid file.']); exit; }
    if (file_exists($filepath)) unlink($filepath);
    $idx = array_values(array_filter(readIndex($indexFile), fn($b) => $b['filename'] !== $filename));
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

    $sheets = [];

    // 1. Store Info
    if (in_array('store_info', $types)) {
        try {
            $row = $conn->query("SELECT fastfood_name, username, email, fullname FROM admins WHERE admin_id={$admin_id}")->fetch(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Store Info',
                'headers' => ['Field','Value'],
                'rows'    => [
                    ['Store Name',    $row['fastfood_name'] ?? ''],
                    ['Admin Username',$row['username'] ?? ''],
                    ['Admin Email',   $row['email'] ?? ''],
                    ['Admin Name',    $row['fullname'] ?? ''],
                    ['Export Date',   date('Y-m-d H:i:s')],
                    ['Date Range',    "$from to $to"],
                ],
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Store Info','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

    // 2. Staff
    if (in_array('staff', $types)) {
        try {
            $rows = $conn->query("SELECT staff_id, fullname, role, status, shift_start, shift_end, created_at FROM staffs WHERE admin_id={$admin_id} ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Staff',
                'headers' => ['Staff ID','Full Name','Role','Status','Shift Start','Shift End','Created At'],
                'rows'    => $rows,
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Staff','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

    // 3. Inventory
    if (in_array('inventory', $types)) {
        try {
            $rows = $conn->query("SELECT menu_item_id, item_name, category, price, stock_quantity, is_available, created_at, updated_at FROM menu_items WHERE admin_id={$admin_id} AND is_deleted=0 ORDER BY category, item_name")->fetchAll(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Inventory',
                'headers' => ['Item ID','Item Name','Category','Price','Stock Qty','Available','Created At','Updated At'],
                'rows'    => $rows,
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Inventory','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

    // 4. Orders
    if (in_array('orders', $types)) {
        try {
            $rows = $conn->query("
                SELECT o.order_id, o.queue_number, o.order_status, o.total_amount,
                       p.amount_paid, p.change_given, p.payment_method, p.receipt_number,
                       o.created_at AS order_date
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.order_id
                WHERE o.admin_id = {$admin_id} AND DATE(o.created_at) BETWEEN {$qFrom} AND {$qTo}
                ORDER BY o.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Orders',
                'headers' => ['Order ID','Queue #','Status','Total Amount','Amount Paid','Change Given','Payment Method','Receipt #','Order Date'],
                'rows'    => $rows,
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Orders','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

    // 5. Revenue
    if (in_array('revenue', $types)) {
        try {
            $rows = $conn->query("
                SELECT DATE(o.created_at) AS sale_date,
                       COUNT(DISTINCT o.order_id) AS total_orders,
                       COUNT(DISTINCT CASE WHEN o.order_status='Served' THEN o.order_id END) AS completed,
                       COUNT(DISTINCT CASE WHEN o.order_status='Cancelled' THEN o.order_id END) AS cancelled,
                       COALESCE(SUM(CASE WHEN o.order_status='Served' THEN p.amount_paid END),0) AS gross_revenue,
                       COALESCE(SUM(CASE WHEN o.order_status='Served' THEN o.total_amount END),0) AS net_sales,
                       COALESCE(AVG(CASE WHEN o.order_status='Served' THEN o.total_amount END),0) AS avg_order_value
                FROM orders o LEFT JOIN payments p ON p.order_id = o.order_id
                WHERE o.admin_id = {$admin_id} AND DATE(o.created_at) BETWEEN {$qFrom} AND {$qTo}
                GROUP BY DATE(o.created_at) ORDER BY sale_date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            $sheets[] = [
                'name'    => 'Revenue',
                'headers' => ['Date','Total Orders','Completed','Cancelled','Gross Revenue','Net Sales','Avg Order Value'],
                'rows'    => $rows,
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Revenue','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

    // 6. Deleted Items
    if (in_array('deleted_items', $types)) {
        try {
            $rows = [];
            try { $rows = $conn->query("SELECT item_name, category, price, stock_quantity, deleted_by, deleted_at FROM deleted_menu_items WHERE admin_id={$admin_id} ORDER BY deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e2) {}
            $sheets[] = [
                'name'    => 'Deleted Items',
                'headers' => ['Item Name','Category','Price','Stock Qty','Deleted By','Deleted At'],
                'rows'    => $rows,
            ];
        } catch(Exception $e) { $sheets[] = ['name'=>'Deleted Items','headers'=>['Error'],'rows'=>[[$e->getMessage()]]]; }
    }

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
