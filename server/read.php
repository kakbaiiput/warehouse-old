<?php
// Read-only API for index.html — serves data from MySQL (kept in sync from
// Google Sheets) instead of hitting the Apps Script web app on every load.
// Mirrors the JSON shapes returned by the Apps Script doGet() actions so
// index.html only needs to change CONFIG.API_URL.

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'none';
$callback = $_GET['callback'] ?? null;

function fmt_date($dt) {
    return $dt ? date('Y-m-d H:i:s', strtotime($dt)) : '';
}

function send($response, $callback) {
    header('Access-Control-Allow-Origin: *');
    if ($callback) {
        header('Content-Type: application/javascript; charset=utf-8');
        echo preg_replace('/[^a-zA-Z0-9_]/', '', $callback) . '(' . json_encode($response) . ')';
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    }
    exit;
}

function masuk_row($r) {
    return [fmt_date($r['tanggal']), $r['no_invoice'], $r['supplier'], $r['kode_produk'], (float)$r['qty'], (float)$r['harga'], (float)$r['total'], $r['user']];
}

function keluar_row($r) {
    return [fmt_date($r['tanggal']), $r['no_do'], $r['kode_cabang'], $r['kode_produk'], (float)$r['qty'], (float)$r['harga'], (float)$r['total'], $r['user']];
}

function keuangan_row($r) {
    return [fmt_date($r['tanggal']), $r['keterangan'], $r['tipe'], (float)$r['jumlah'], (float)$r['saldo'], $r['ref_no']];
}

function stok_row($r) {
    return [$r['kode'], $r['nama_produk'], (float)$r['masuk'], (float)$r['keluar'], (float)$r['sisa'], (int)$r['min_stok'], fmt_date($r['last_update'])];
}

try {
    $pdo = get_db();
    $response = null;

    switch ($action) {
        case 'test':
            $response = ['success' => true, 'message' => 'DB-backed API is working!', 'timestamp' => date('c')];
            break;

        case 'getMasterData': {
            $produk = $pdo->query('SELECT * FROM master_produk ORDER BY kode')->fetchAll();
            $cabang = $pdo->query('SELECT * FROM master_cabang ORDER BY kode')->fetchAll();
            $hasProductID = false;
            foreach ($produk as $p) {
                if (!empty($p['product_id'])) { $hasProductID = true; break; }
            }
            $produkRows = array_map(function ($p) use ($hasProductID) {
                if ($hasProductID) {
                    return [$p['product_id'], $p['kode'], $p['nama_produk'], $p['kemasan'], (float)$p['harga_ref'], (int)$p['min_stok'], $p['status']];
                }
                return [$p['kode'], $p['nama_produk'], $p['kemasan'], (float)$p['harga_ref'], (int)$p['min_stok'], $p['status']];
            }, $produk);
            $cabangRows = array_map(function ($c) {
                return [$c['kode'], $c['nama_cabang'], $c['pic'], $c['no_hp']];
            }, $cabang);
            $response = [
                'success' => true,
                'data' => ['produk' => $produkRows, 'cabang' => $cabangRows],
                'hasProductID' => $hasProductID,
            ];
            break;
        }

        case 'getDashboardData': {
            $stok = $pdo->query('SELECT * FROM stok_real ORDER BY kode')->fetchAll();
            $keuangan = $pdo->query('SELECT * FROM keuangan ORDER BY tanggal ASC')->fetchAll();
            $recentMasuk = $pdo->query('SELECT * FROM barang_masuk ORDER BY tanggal DESC LIMIT 10')->fetchAll();
            $recentKeluar = $pdo->query('SELECT * FROM barang_keluar ORDER BY tanggal DESC LIMIT 10')->fetchAll();
            $response = [
                'success' => true,
                'data' => [
                    'stok' => array_map('stok_row', $stok),
                    'keuangan' => array_map('keuangan_row', $keuangan),
                    'recentMasuk' => array_map('masuk_row', $recentMasuk),
                    'recentKeluar' => array_map('keluar_row', $recentKeluar),
                ],
            ];
            break;
        }

        case 'getFullMasukData': {
            $rows = $pdo->query('SELECT * FROM barang_masuk ORDER BY tanggal DESC')->fetchAll();
            $response = ['success' => true, 'data' => array_map('masuk_row', $rows)];
            break;
        }

        case 'getFullKeluarData': {
            $rows = $pdo->query('SELECT * FROM barang_keluar ORDER BY tanggal DESC')->fetchAll();
            $response = ['success' => true, 'data' => array_map('keluar_row', $rows)];
            break;
        }

        case 'getAverageCosts': {
            $produk = $pdo->query('SELECT * FROM master_produk')->fetchAll();
            $mapping = [];
            $hasProductID = false;
            foreach ($produk as $p) {
                if (!empty($p['product_id'])) {
                    $mapping[$p['product_id']] = $p['kode'];
                    $hasProductID = true;
                }
                $mapping[$p['kode']] = $p['kode'];
            }

            $rows = $pdo->query('SELECT tanggal, no_invoice, supplier, kode_produk, qty, harga FROM barang_masuk')->fetchAll();
            $costData = [];
            foreach ($rows as $r) {
                $qty = (float)$r['qty'];
                $harga = (float)$r['harga'];
                if (!$r['kode_produk'] || $qty <= 0 || $harga <= 0) continue;
                $displayCode = $mapping[$r['kode_produk']] ?? $r['kode_produk'];
                if (!isset($costData[$displayCode])) {
                    $costData[$displayCode] = ['totalValue' => 0, 'totalQty' => 0, 'avgCost' => 0, 'transactions' => [], 'lastPrice' => 0, 'priceCount' => 0];
                }
                $costData[$displayCode]['totalValue'] += $qty * $harga;
                $costData[$displayCode]['totalQty'] += $qty;
                $costData[$displayCode]['priceCount']++;
                $costData[$displayCode]['lastPrice'] = $harga;
                $costData[$displayCode]['transactions'][] = [
                    'tanggal' => fmt_date($r['tanggal']),
                    'qty' => $qty,
                    'harga' => $harga,
                    'supplier' => $r['supplier'],
                    'invoice' => $r['no_invoice'],
                ];
            }
            foreach ($costData as $kode => &$d) {
                if ($d['totalQty'] > 0) $d['avgCost'] = round($d['totalValue'] / $d['totalQty']);
            }
            $response = ['success' => true, 'data' => $costData, 'hasProductID' => $hasProductID];
            break;
        }

        default:
            $response = ['success' => false, 'message' => 'Invalid action: ' . $action];
    }

    send($response, $callback);
} catch (Throwable $e) {
    send(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], $callback);
}
