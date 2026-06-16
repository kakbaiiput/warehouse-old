-- Schema for rt_warehouse database (mirrors Google Sheet structure)
-- Run once: mysql -u rt_warehouse -p rt_warehouse < schema.sql

CREATE TABLE IF NOT EXISTS master_produk (
    kode VARCHAR(50) PRIMARY KEY,
    product_id VARCHAR(50) NULL,
    nama_produk VARCHAR(255) NOT NULL,
    kemasan VARCHAR(100),
    harga_ref DECIMAL(15,2) DEFAULT 0,
    min_stok INT DEFAULT 0,
    status VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS master_cabang (
    kode VARCHAR(50) PRIMARY KEY,
    nama_cabang VARCHAR(255) NOT NULL,
    pic VARCHAR(255),
    no_hp VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang_masuk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME,
    no_invoice VARCHAR(100),
    supplier VARCHAR(255),
    kode_produk VARCHAR(50),
    qty DECIMAL(15,2),
    harga DECIMAL(15,2),
    total DECIMAL(15,2),
    user VARCHAR(100),
    INDEX idx_tanggal (tanggal),
    INDEX idx_kode_produk (kode_produk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang_keluar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME,
    no_do VARCHAR(100),
    kode_cabang VARCHAR(50),
    kode_produk VARCHAR(50),
    qty DECIMAL(15,2),
    harga DECIMAL(15,2),
    total DECIMAL(15,2),
    user VARCHAR(100),
    INDEX idx_tanggal (tanggal),
    INDEX idx_kode_produk (kode_produk)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS keuangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME,
    keterangan VARCHAR(500),
    tipe VARCHAR(20),
    jumlah DECIMAL(15,2),
    saldo DECIMAL(15,2),
    ref_no VARCHAR(100),
    INDEX idx_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stok_real (
    kode VARCHAR(50) PRIMARY KEY,
    nama_produk VARCHAR(255),
    masuk DECIMAL(15,2) DEFAULT 0,
    keluar DECIMAL(15,2) DEFAULT 0,
    sisa DECIMAL(15,2) DEFAULT 0,
    min_stok INT DEFAULT 0,
    last_update DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    rows_produk INT DEFAULT 0,
    rows_cabang INT DEFAULT 0,
    rows_masuk INT DEFAULT 0,
    rows_keluar INT DEFAULT 0,
    rows_keuangan INT DEFAULT 0,
    rows_stok INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
