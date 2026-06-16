<?php
// Receives a full data push from the Apps Script backend and replaces
// the contents of each table. Called either right after a save in
// input.html, on every sheet edit (onEdit), or by the time-driven trigger.

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload) || !isset($payload['secret']) || !hash_equals(SYNC_SECRET, (string)$payload['secret'])) {
    http_response_code(403);
    json_response(['success' => false, 'message' => 'Invalid secret']);
}

function to_datetime($value) {
    if ($value === null || $value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function to_num($value) {
    if ($value === null || $value === '') return 0;
    return is_numeric($value) ? $value : 0;
}

$pdo = get_db();
$counts = ['produk' => 0, 'cabang' => 0, 'masuk' => 0, 'keluar' => 0, 'keuangan' => 0, 'stok' => 0];

try {
    $pdo->beginTransaction();

    if (isset($payload['masterProduk']) && is_array($payload['masterProduk'])) {
        $pdo->exec('TRUNCATE TABLE master_produk');
        $stmt = $pdo->prepare('INSERT INTO master_produk (product_id, kode, nama_produk, kemasan, harga_ref, min_stok, status) VALUES (?,?,?,?,?,?,?)');
        foreach ($payload['masterProduk'] as $row) {
            // row is either [ProductID, Kode, Nama, Kemasan, HargaRef, MinStok, Status] or [Kode, Nama, Kemasan, HargaRef, MinStok, Status]
            if (count($row) === 7) {
                [$productId, $kode, $nama, $kemasan, $harga, $minStok, $status] = $row;
            } else {
                $productId = null;
                [$kode, $nama, $kemasan, $harga, $minStok, $status] = $row;
            }
            if (!$kode) continue;
            $stmt->execute([$productId ?: null, $kode, $nama, $kemasan, to_num($harga), to_num($minStok), $status]);
            $counts['produk']++;
        }
    }

    if (isset($payload['masterCabang']) && is_array($payload['masterCabang'])) {
        $pdo->exec('TRUNCATE TABLE master_cabang');
        $stmt = $pdo->prepare('INSERT INTO master_cabang (kode, nama_cabang, pic, no_hp) VALUES (?,?,?,?)');
        foreach ($payload['masterCabang'] as $row) {
            [$kode, $nama, $pic, $hp] = array_pad($row, 4, null);
            if (!$kode) continue;
            $stmt->execute([$kode, $nama, $pic, $hp]);
            $counts['cabang']++;
        }
    }

    if (isset($payload['barangMasuk']) && is_array($payload['barangMasuk'])) {
        $pdo->exec('TRUNCATE TABLE barang_masuk');
        $stmt = $pdo->prepare('INSERT INTO barang_masuk (tanggal, no_invoice, supplier, kode_produk, qty, harga, total, user) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($payload['barangMasuk'] as $row) {
            [$tanggal, $noInv, $supplier, $kodeProduk, $qty, $harga, $total, $user] = array_pad($row, 8, null);
            if (!$tanggal || !$kodeProduk) continue;
            $stmt->execute([to_datetime($tanggal), $noInv, $supplier, $kodeProduk, to_num($qty), to_num($harga), to_num($total), $user]);
            $counts['masuk']++;
        }
    }

    if (isset($payload['barangKeluar']) && is_array($payload['barangKeluar'])) {
        $pdo->exec('TRUNCATE TABLE barang_keluar');
        $stmt = $pdo->prepare('INSERT INTO barang_keluar (tanggal, no_do, kode_cabang, kode_produk, qty, harga, total, user) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($payload['barangKeluar'] as $row) {
            [$tanggal, $noDo, $kodeCabang, $kodeProduk, $qty, $harga, $total, $user] = array_pad($row, 8, null);
            if (!$tanggal || !$kodeProduk) continue;
            $stmt->execute([to_datetime($tanggal), $noDo, $kodeCabang, $kodeProduk, to_num($qty), to_num($harga), to_num($total), $user]);
            $counts['keluar']++;
        }
    }

    if (isset($payload['keuangan']) && is_array($payload['keuangan'])) {
        $pdo->exec('TRUNCATE TABLE keuangan');
        $stmt = $pdo->prepare('INSERT INTO keuangan (tanggal, keterangan, tipe, jumlah, saldo, ref_no) VALUES (?,?,?,?,?,?)');
        foreach ($payload['keuangan'] as $row) {
            [$tanggal, $ket, $tipe, $jumlah, $saldo, $refNo] = array_pad($row, 6, null);
            if (!$tanggal) continue;
            $stmt->execute([to_datetime($tanggal), $ket, $tipe, to_num($jumlah), to_num($saldo), $refNo]);
            $counts['keuangan']++;
        }
    }

    if (isset($payload['stokReal']) && is_array($payload['stokReal'])) {
        $pdo->exec('TRUNCATE TABLE stok_real');
        $stmt = $pdo->prepare('INSERT INTO stok_real (kode, nama_produk, masuk, keluar, sisa, min_stok, last_update) VALUES (?,?,?,?,?,?,?)');
        foreach ($payload['stokReal'] as $row) {
            [$kode, $nama, $masuk, $keluar, $sisa, $minStok, $lastUpdate] = array_pad($row, 7, null);
            if (!$kode) continue;
            $stmt->execute([$kode, $nama, to_num($masuk), to_num($keluar), to_num($sisa), to_num($minStok), to_datetime($lastUpdate)]);
            $counts['stok']++;
        }
    }

    $pdo->prepare('INSERT INTO sync_log (rows_produk, rows_cabang, rows_masuk, rows_keluar, rows_keuangan, rows_stok) VALUES (?,?,?,?,?,?)')
        ->execute([$counts['produk'], $counts['cabang'], $counts['masuk'], $counts['keluar'], $counts['keuangan'], $counts['stok']]);

    $pdo->commit();

    json_response(['success' => true, 'counts' => $counts]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    json_response(['success' => false, 'message' => $e->getMessage()]);
}
