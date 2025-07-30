<?php
session_start();
include 'koneksi.php';

// Akses hanya untuk admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Proses tambah paket
if (isset($_POST['simpan'])) {
    $nama_paket = trim($_POST['nama_paket']);
    $deskripsi = trim($_POST['deskripsi_otomatis']); // Ambil dari hidden field
    $harga_total_promo = floatval($_POST['harga_promo_hidden']); // Ambil harga promo dari hidden field
    $layanan_terpilih = $_POST['layanan'] ?? [];

    if (empty($nama_paket) || $harga_total_promo <= 0 || count($layanan_terpilih) == 0) {
        echo "<script>alert('Harap lengkapi semua data: Nama Paket, pilih minimal satu layanan, dan pastikan Harga Promo lebih dari 0.'); window.location='kelola_paket.php';</script>";
        exit;
    }

    $koneksi->begin_transaction(); // Mulai transaksi

    try {
        // Insert ke tabel paket
        $stmt = $koneksi->prepare("INSERT INTO paket (nama_paket, deskripsi, harga_total) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Gagal menyiapkan statement paket: " . $koneksi->error);
        }
        $stmt->bind_param("ssd", $nama_paket, $deskripsi, $harga_total_promo);
        $stmt->execute();
        $id_paket = $stmt->insert_id;
        $stmt->close();

        // Insert ke tabel paket_layanan
        $stmt_layanan = $koneksi->prepare("INSERT INTO paket_layanan (id_paket, id_layanan) VALUES (?, ?)");
        if (!$stmt_layanan) {
            throw new Exception("Gagal menyiapkan statement paket_layanan: " . $koneksi->error);
        }
        foreach ($layanan_terpilih as $id_layanan) {
            $stmt_layanan->bind_param("ii", $id_paket, $id_layanan);
            $stmt_layanan->execute();
        }
        $stmt_layanan->close();

        $koneksi->commit(); // Commit transaksi jika semua berhasil
        echo "<script>alert('Paket berhasil ditambahkan!'); window.location='kelola_paket.php';</script>";
    } catch (Exception $e) {
        $koneksi->rollback(); // Rollback jika ada error
        echo "<script>alert('Gagal menambahkan paket: " . $e->getMessage() . "'); window.location='kelola_paket.php';</script>";
    }
    exit;
}

// --- LOGIKA UNTUK MENGHAPUS PAKET ---
if (isset($_GET['hapus_id'])) {
    $id_paket_hapus = intval($_GET['hapus_id']); // Pastikan ini integer

    if ($id_paket_hapus > 0) {
        $koneksi->begin_transaction(); // Mulai transaksi

        try {
            // Hapus dari tabel paket_layanan terlebih dahulu
            $stmt_delete_layanan = $koneksi->prepare("DELETE FROM paket_layanan WHERE id_paket = ?");
            if (!$stmt_delete_layanan) {
                throw new Exception("Gagal menyiapkan statement hapus paket_layanan: " . $koneksi->error);
            }
            $stmt_delete_layanan->bind_param("i", $id_paket_hapus);
            $stmt_delete_layanan->execute();
            $stmt_delete_layanan->close();

            // Kemudian hapus dari tabel paket
            $stmt_delete_paket = $koneksi->prepare("DELETE FROM paket WHERE id = ?");
            if (!$stmt_delete_paket) {
                throw new Exception("Gagal menyiapkan statement hapus paket: " . $koneksi->error);
            }
            $stmt_delete_paket->bind_param("i", $id_paket_hapus);
            $stmt_delete_paket->execute();
            $stmt_delete_paket->close();

            $koneksi->commit(); // Commit transaksi jika semua berhasil
            echo "<script>alert('Paket berhasil dihapus!'); window.location='kelola_paket.php';</script>";
        } catch (Exception $e) {
            $koneksi->rollback(); // Rollback jika ada error
            echo "<script>alert('Gagal menghapus paket: " . $e->getMessage() . "'); window.location='kelola_paket.php';</script>";
        }
    } else {
        echo "<script>alert('ID Paket tidak valid.'); window.location='kelola_paket.php';</script>";
    }
    exit;
}
// --- AKHIR LOGIKA PENGHAPUSAN ---


// Ambil semua layanan untuk ditampilkan di form
// Gunakan prepared statement jika ada potensi filter atau search di masa depan
$all_layanan_stmt = $koneksi->prepare("SELECT id, nama_layanan, harga FROM layanan ORDER BY nama_layanan ASC");
$all_layanan_stmt->execute();
$all_layanan_result = $all_layanan_stmt->get_result();
$all_layanan_data = [];
while ($l = $all_layanan_result->fetch_assoc()) {
    $all_layanan_data[] = $l;
}
$all_layanan_stmt->close();


// Ambil semua paket beserta layanan yang termasuk di dalamnya
// Menggunakan Prepared Statement untuk keamanan
$paket_query_sql = "
    SELECT
        p.id, p.nama_paket, p.deskripsi, p.harga_total,
        GROUP_CONCAT(l.nama_layanan ORDER BY l.nama_layanan SEPARATOR ', ') AS rincian_layanan
    FROM paket p
    LEFT JOIN paket_layanan pl ON p.id = pl.id_paket
    LEFT JOIN layanan l ON pl.id_layanan = l.id
    GROUP BY p.id, p.nama_paket, p.deskripsi, p.harga_total
    ORDER BY p.id DESC
";
$stmt_paket = $koneksi->prepare($paket_query_sql);
$stmt_paket->execute();
$paket_result = $stmt_paket->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Paket Hemat - Bengkel XYZ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna, konsisten dengan dashboard_admin.php */
        :root {
            --primary-bg: #f8f9fa;
            --secondary-bg: #ffffff;
            --sidebar-bg: #e9ecef;
            --sidebar-text: #495057;
            --sidebar-hover: #dee2e6;
            --accent-color: #6c757d;
            --accent-hover: #5a6268;
            --text-dark: #343a40;
            --text-muted: #adb5bd;
            --border-light: #e0e0e0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);

            /* Warna tambahan untuk checkbox */
            --checkbox-border: #ced4da;
            --checkbox-checked: var(--accent-color);
            --danger-color: #dc3545; /* Tambahan warna untuk tombol hapus */
            --danger-hover: #c82333;
        }

        /* Reset dan Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar (sama seperti dashboard_admin.php) */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            height: 100%;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
            transform: translateX(0);
            z-index: 1000;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        /* Menyembunyikan scrollbar sidebar secara visual */
        .sidebar::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }
        .sidebar {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar .logo {
            font-size: 1.8em;
            font-weight: 700;
            margin-bottom: 40px;
            text-align: center;
            color: var(--accent-color);
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            margin-bottom: 10px;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
            font-weight: 500;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            font-size: 1.1em;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: var(--sidebar-hover);
            color: var(--text-dark);
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            margin-left: 250px;
            padding: 30px;
            transition: margin-left 0.3s ease-in-out;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Bar */
        .top-bar {
            background-color: var(--secondary-bg);
            padding: 20px 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-light);
        }

        .top-bar h1 {
            font-size: 2em;
            font-weight: 700;
            color: var(--accent-color);
        }

        .menu-toggle {
            display: none;
            font-size: 1.5em;
            color: var(--accent-color);
            cursor: pointer;
            margin-right: 20px;
        }

        /* Content Area for Table */
        .content-area {
            background-color: var(--secondary-bg);
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border: 1px solid var(--border-light);
        }

        .content-area h2 {
            font-size: 1.8em;
            color: var(--accent-color);
            margin-bottom: 25px;
            font-weight: 600;
            border-bottom: 2px solid var(--border-light);
            padding-bottom: 10px;
        }

        .content-area h3 {
            font-size: 1.4em;
            color: var(--text-dark);
            margin-top: 30px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Form Styling */
        .form-container {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--border-light);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
            background-color: var(--primary-bg); /* Latar belakang input lebih terang */
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus {
            border-color: var(--accent-color);
            outline: none;
            background-color: var(--secondary-bg);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-submit {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--accent-color);
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: var(--accent-hover);
        }

        /* Checkbox Container */
        .checkbox-container {
            border: 1px solid var(--border-light);
            border-radius: 5px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
            background-color: var(--primary-bg);
            margin-bottom: 20px;
        }

        .checkbox-container::-webkit-scrollbar {
            width: 8px;
        }

        .checkbox-container::-webkit-scrollbar-thumb {
            background-color: var(--text-muted);
            border-radius: 4px;
        }

        .checkbox-container label {
            display: flex; /* Untuk aligment checkbox dan teks */
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
            font-weight: 400;
            color: var(--text-dark);
        }
        
        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            border: 1px solid var(--checkbox-border);
            border-radius: 3px;
            appearance: none; /* Sembunyikan checkbox bawaan */
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
            position: relative;
            outline: none;
            flex-shrink: 0; /* Mencegah checkbox mengecil */
        }

        .checkbox-container input[type="checkbox"]:checked {
            background-color: var(--checkbox-checked);
            border-color: var(--checkbox-checked);
        }

        .checkbox-container input[type="checkbox"]::before {
            content: '\2713'; /* Tanda centang unicode */
            font-size: 14px;
            color: #ffffff; /* Menggunakan putih langsung, bukan variabel */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            transition: transform 0.2s ease-in-out;
        }

        .checkbox-container input[type="checkbox"]:checked::before {
            transform: translate(-50%, -50%) scale(1);
        }

        /* Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table thead th {
            background-color: var(--accent-color);
            color: #ffffff;
            font-weight: 600;
            text-transform: uppercase;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .data-table tbody tr:hover {
            background-color: #e9ecef;
        }

        /* Action Buttons in Table */
        .action-buttons a {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: #ffffff;
            font-size: 0.9em;
            margin-right: 5px;
        }

        .action-buttons .btn-delete {
            background-color: var(--danger-color);
            transition: background-color 0.3s ease;
        }

        .action-buttons .btn-delete:hover {
            background-color: var(--danger-hover);
        }


        /* Link Kembali */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        /* Responsif */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .main-content.expanded {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
            .top-bar {
                padding: 15px 20px;
            }
            .top-bar h1 {
                font-size: 1.8em;
            }
            .content-area {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }
            /* Sembunyikan kolom tertentu di layar kecil */
            .data-table th:nth-child(2), /* Deskripsi */
            .data-table td:nth-child(2) {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">Bengkel SAC Admin</div>
            <ul class="sidebar-menu">
                <li><a href="dashboard_admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="kelola_booking.php"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
                <li><a href="kelola_tagihan.php"><i class="fas fa-file-invoice"></i> Kelola Tagihan</a></li>
                <li><a href="datacustomer.php"><i class="fas fa-users"></i> Data Customer</a></li>
                <li><a href="kelola_user.php"><i class="fas fa-user-tie"></i> Kelola User</a></li>
                <li><a href="layanan.php"><i class="fas fa-cogs"></i> Kelola Layanan</a></li>
                <li><a href="kelola_paket.php" class="active"><i class="fas fa-box-open"></i> Kelola Paket</a></li>
                <li><a href="atur_jadwal_nonaktif.php"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Kelola Paket Hemat</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2><i class="fas fa-plus-circle"></i> Tambah Paket Baru</h2>

                <div class="form-container">
                    <form method="POST" id="formPaket">
                        <div class="form-group">
                            <label for="nama_paket">Nama Paket:</label>
                            <input type="text" id="nama_paket" name="nama_paket" placeholder="Contoh: Paket Service Lengkap" required>
                        </div>

                        <label>Pilih Layanan yang Termasuk dalam Paket:</label>
                        <div class="checkbox-container" id="layanan_checkboxes">
                            <?php if (!empty($all_layanan_data)): ?>
                                <?php foreach ($all_layanan_data as $l) : ?>
                                    <label>
                                        <input type="checkbox" name="layanan[]" value="<?= htmlspecialchars($l['id']) ?>" 
                                            data-nama-layanan="<?= htmlspecialchars($l['nama_layanan']) ?>" 
                                            data-harga="<?= htmlspecialchars($l['harga']) ?>">
                                        <?= htmlspecialchars($l['nama_layanan']) ?> (Rp<?= number_format($l['harga'], 0, ',', '.') ?>)
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: var(--text-muted);">Belum ada layanan tersedia. Harap tambahkan layanan terlebih dahulu.</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="deskripsi_otomatis">Deskripsi Paket (Otomatis dibuat):</label>
                            <textarea id="deskripsi_otomatis_display" rows="3" readonly style="background-color: var(--primary-bg);"></textarea>
                            <input type="hidden" name="deskripsi_otomatis" id="deskripsi_otomatis_hidden">
                        </div>

                        <div class="form-group">
                            <label for="harga_layanan_terpilih_display">Total Harga Layanan Terpilih (Dasar):</label>
                            <input type="text" id="harga_layanan_terpilih_display" value="Rp0" readonly style="background-color: var(--primary-bg);">
                            <input type="hidden" name="harga_layanan_terpilih_hidden" id="harga_layanan_terpilih_hidden" value="0">
                        </div>

                        <div class="form-group">
                            <label for="diskon_persen">Diskon (%):</label>
                            <input type="number" id="diskon_persen" value="0" min="0" max="100" oninput="hitungHargaPromo()" placeholder="Masukkan persentase diskon">
                        </div>

                        <div class="form-group">
                            <label for="harga_promo_display">Harga Promo (Final Paket):</label>
                            <input type="text" id="harga_promo_display" value="Rp0" readonly style="background-color: var(--primary-bg);">
                            <input type="hidden" name="harga_promo_hidden" id="harga_promo_hidden" value="0">
                        </div>

                        <button type="submit" name="simpan" class="btn-submit"><i class="fas fa-save"></i> Simpan Paket</button>
                    </form>
                </div>

                <h3><i class="fas fa-list-alt"></i> Daftar Paket Hemat Tersedia</h3>
                <?php if ($paket_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama Paket</th>
                                    <th>Deskripsi (Layanan Termasuk)</th>
                                    <th>Harga Promo</th>
                                    <th>Aksi</th> </tr>
                            </thead>
                            <tbody>
                                <?php while ($p = $paket_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['nama_paket']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($p['deskripsi'])) ?></td>
                                        <td>Rp<?= number_format($p['harga_total'], 0, ',', '.') ?></td>
                                        <td class="action-buttons">
                                            <a href="kelola_paket.php?hapus_id=<?= htmlspecialchars($p['id']) ?>" 
                                               class="btn-delete" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus paket ini? Tindakan ini tidak dapat dibatalkan.');">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">Belum ada paket hemat yang ditambahkan.</p>
                <?php endif; ?>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>

    <script>
        // JavaScript untuk Toggle Sidebar di Mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('expanded');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('expanded');
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('hidden');
                sidebar.classList.remove('active');
                mainContent.style.marginLeft = '250px';
                mainContent.classList.remove('expanded');
            } else {
                sidebar.classList.add('hidden');
                mainContent.style.marginLeft = '0';
            }
        });

        if (window.innerWidth <= 992) {
            sidebar.classList.add('hidden');
            mainContent.style.marginLeft = '0';
        }


        // JavaScript untuk perhitungan dan deskripsi otomatis
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('#layanan_checkboxes input[type="checkbox"]');
            const deskripsiOtomatisDisplay = document.getElementById('deskripsi_otomatis_display');
            const deskripsiOtomatisHidden = document.getElementById('deskripsi_otomatis_hidden');
            const hargaLayananTerpilihDisplay = document.getElementById('harga_layanan_terpilih_display');
            const hargaLayananTerpilihHidden = document.getElementById('harga_layanan_terpilih_hidden');
            const diskonPersenInput = document.getElementById('diskon_persen');
            const hargaPromoDisplay = document.getElementById('harga_promo_display');
            const hargaPromoHidden = document.getElementById('harga_promo_hidden');

            function formatRupiah(angka) {
                if (isNaN(angka) || angka === null) return '0';
                let reverse = Math.round(angka).toString().split('').reverse().join('');
                let ribuan = reverse.match(/\d{1,3}/g);
                let formatted = ribuan.join('.').split('').reverse().join('');
                return formatted;
            }

            function updateDeskripsiDanHarga() {
                let selectedServicesNames = [];
                let totalHargaLayanan = 0;

                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        selectedServicesNames.push(checkbox.dataset.namaLayanan);
                        totalHargaLayanan += parseFloat(checkbox.dataset.harga);
                    }
                });

                // Update Deskripsi
                if (selectedServicesNames.length > 0) {
                    deskripsiOtomatisDisplay.value = 'Meliputi: ' + selectedServicesNames.join(', ') + '.';
                    deskripsiOtomatisHidden.value = 'Meliputi: ' + selectedServicesNames.join(', ') + '.';
                } else {
                    deskripsiOtomatisDisplay.value = '';
                    deskripsiOtomatisHidden.value = '';
                }

                // Update Harga Layanan Terpilih
                hargaLayananTerpilihDisplay.value = 'Rp' + formatRupiah(totalHargaLayanan);
                hargaLayananTerpilihHidden.value = totalHargaLayanan;

                // Panggil hitungHargaPromo untuk memperbarui harga promo
                hitungHargaPromo();
            }

            function hitungHargaPromo() {
                const hargaDasar = parseFloat(hargaLayananTerpilihHidden.value) || 0;
                let diskonPersen = parseFloat(diskonPersenInput.value) || 0;

                // Validasi diskon agar tidak di luar 0-100
                if (diskonPersen < 0) diskonPersen = 0;
                if (diskonPersen > 100) diskonPersen = 100;
                diskonPersenInput.value = diskonPersen; // Update input jika ada perubahan

                let hargaPromo = hargaDasar;
                if (diskonPersen > 0) {
                    const nilaiDiskon = (diskonPersen / 100) * hargaDasar;
                    hargaPromo = hargaDasar - nilaiDiskon;
                }
                
                // Pastikan harga promo tidak negatif
                if (hargaPromo < 0) hargaPromo = 0;

                hargaPromoDisplay.value = 'Rp' + formatRupiah(hargaPromo);
                hargaPromoHidden.value = hargaPromo;
            }

            // Tambahkan event listener ke setiap checkbox
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateDeskripsiDanHarga);
            });

            // Tambahkan event listener untuk input diskon
            diskonPersenInput.addEventListener('input', hitungHargaPromo);

            // Panggil saat halaman dimuat untuk memastikan nilai awal benar
            updateDeskripsiDanHarga(); // Ini akan memanggil hitungHargaPromo juga
        });
    </script>
</body>
</html>
<?php
$stmt_paket->close();
$koneksi->close();
?>