<?php
session_start();
include 'koneksi.php';

// Akses hanya admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// --- Proses UPDATE status bayar ---
if (isset($_POST['update_status_bayar'])) {
    $id_tagihan = $_POST['id_tagihan'];
    $new_status = $_POST['new_status']; // Akan selalu 'sudah'

    // Validasi input
    if ($new_status === 'sudah') {
        $stmt_update_status = $koneksi->prepare("UPDATE tagihan SET status_bayar = ?, tanggal_bayar = NOW() WHERE id = ? AND status_bayar = 'belum'");
        if ($stmt_update_status) {
            $stmt_update_status->bind_param("si", $new_status, $id_tagihan);
            if ($stmt_update_status->execute()) {
                echo "<script>alert('Status pembayaran berhasil diperbarui!'); window.location='kelola_tagihan.php';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui status pembayaran: " . $stmt_update_status->error . "');</script>";
            }
            $stmt_update_status->close();
        } else {
            echo "<script>alert('Gagal menyiapkan statement update: " . $koneksi->error . "');</script>";
        }
    } else {
        echo "<script>alert('Status tidak valid.');</script>";
    }
    exit; // Penting untuk menghentikan eksekusi setelah POST
}


// Proses simpan tagihan (kode yang sudah ada)
if (isset($_POST['buat_tagihan'])) {
    $id_booking = $_POST['id_booking'];
    $rincian    = $_POST['rincian'];
    $total_final = floatval($_POST['total_display_hidden']); // Ambil total dari hidden field

    // Cek apakah sudah ada tagihan untuk booking ini
    $stmt_cek = $koneksi->prepare("SELECT id FROM tagihan WHERE id_booking = ?");
    if ($stmt_cek) {
        $stmt_cek->bind_param("i", $id_booking);
        $stmt_cek->execute();
        $result_cek = $stmt_cek->get_result();
        if ($result_cek->num_rows > 0) {
            echo "<script>alert('Tagihan untuk booking ini sudah dibuat!');</script>";
        } else {
            // Masukkan tagihan baru
            $stmt_insert = $koneksi->prepare("INSERT INTO tagihan (id_booking, rincian, total, status_bayar, tanggal_tagihan) VALUES (?, ?, ?, 'belum', NOW())");
            if ($stmt_insert) {
                $stmt_insert->bind_param("isd", $id_booking, $rincian, $total_final);
                if ($stmt_insert->execute()) {
                    echo "<script>alert('Tagihan berhasil dibuat!'); window.location='kelola_tagihan.php';</script>";
                } else {
                    echo "<script>alert('Gagal membuat tagihan: " . $stmt_insert->error . "');</script>";
                }
                $stmt_insert->close();
            } else {
                echo "<script>alert('Gagal menyiapkan statement insert: " . $koneksi->error . "');</script>";
            }
        }
        $stmt_cek->close();
    } else {
        echo "<script>alert('Gagal menyiapkan statement cek: " . $koneksi->error . "');</script>";
    }
}

// --- Mengambil data untuk halaman ---

// Booking yang selesai dan belum ada tagihan
$booking_selesai_query = "
    SELECT
        b.id, b.tanggal, b.jam, b.id_layanan,
        u.nama AS nama_customer,
        l.nama_layanan, l.harga AS harga_layanan
    FROM booking b
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    WHERE b.status = 'selesai'
    AND b.id NOT IN (SELECT id_booking FROM tagihan)
    ORDER BY b.tanggal ASC, b.jam ASC
";
$booking_selesai_result = $koneksi->query($booking_selesai_query);

// Data untuk JavaScript (harga pokok per booking)
$booking_data_for_js = [];
// Simpan semua hasil ke array PHP, lalu kirim ke JS
$booking_selesai_rows = [];
if ($booking_selesai_result) {
    while ($row = $booking_selesai_result->fetch_assoc()) {
        $booking_selesai_rows[] = $row;
        $harga_pokok = $row['harga_layanan']; // Hanya harga layanan
        $booking_data_for_js[$row['id']] = $harga_pokok;
    }
    // Setel ulang pointer untuk iterasi tampilan
    $booking_selesai_result->data_seek(0);
}


// Filter dan Pencarian untuk Daftar Tagihan (kode yang sudah ada)
$search_tagihan_query = '';
if (isset($_GET['search_tagihan']) && !empty($_GET['search_tagihan'])) {
    $search_tagihan_query = trim($_GET['search_tagihan']);
}

$sql_tagihan = "
    SELECT
        t.*,
        u.nama AS nama_customer,
        b.id_layanan,
        l.nama_layanan
    FROM tagihan t
    JOIN booking b ON t.id_booking = b.id
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    WHERE 1=1
";

$params_tagihan = [];
$types_tagihan = '';

if ($search_tagihan_query) {
    $sql_tagihan .= " AND (u.nama LIKE ? OR t.rincian LIKE ? OR l.nama_layanan LIKE ?)";
    $search_param_tagihan = '%' . $search_tagihan_query . '%';
    $params_tagihan[] = $search_param_tagihan;
    $params_tagihan[] = $search_param_tagihan;
    $params_tagihan[] = $search_param_tagihan;
    $types_tagihan .= 'sss'; // Mengikuti 3 parameter
}

$sql_tagihan .= " ORDER BY t.tanggal_tagihan DESC";

$stmt_tagihan = $koneksi->prepare($sql_tagihan);

if ($params_tagihan) {
    $bind_names_tagihan = [$types_tagihan];
    for ($i = 0; $i < count($params_tagihan); $i++) {
        $bind_names_tagihan[] = &$params_tagihan[$i];
    }
    call_user_func_array([$stmt_tagihan, 'bind_param'], $bind_names_tagihan);
}

$stmt_tagihan->execute();
$data_tagihan_result = $stmt_tagihan->get_result();

// Untuk mempertahankan pilihan booking setelah submit form tanpa reload penuh
$selected_id_booking_post = $_POST['id_booking'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tagihan - Bengkel XYZ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

            --status-belum-bayar-bg: #ffc107;
            /* Kuning (background) */
            --status-belum-bayar-text: #343a40;
            /* Teks gelap agar terbaca di kuning */
            --status-sudah-bayar-bg: #28a745;
            /* Hijau (background) */
            --status-sudah-bayar-text: #ffffff;
            /* Teks putih agar terbaca di hijau */
        }

        /* Status Bayar Badges */
        .status-bayar-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
            /* Tambahkan border yang sama untuk semua badge */
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .status-belum {
            background-color: var(--status-belum-bayar-bg);
            color: var(--status-belum-bayar-text);
        }

        .status-sudah {
            background-color: var(--status-sudah-bayar-bg);
            color: var(--status-sudah-bayar-text);
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
            background-color: var(--primary-bg);
            /* Latar belakang input lebih terang */
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--accent-color);
            outline: none;
            background-color: var(--secondary-bg);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23333" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
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

        /* Detail Booking untuk Form */
        .booking-details {
            background-color: #f0f2f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-light);
            font-size: 0.95em;
            color: var(--text-dark);
        }

        .booking-details p {
            margin-bottom: 5px;
        }

        .booking-details strong {
            color: var(--accent-color);
        }


        /* Search Form for Tagihan List */
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: center;
        }

        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .search-form input[type="text"]:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .search-form button {
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .search-form button:hover {
            background-color: var(--accent-hover);
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

        /* Status Bayar Badges */
        .status-bayar-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            color: #ffffff;
            text-transform: capitalize;
        }

        .status-belum_bayar {
            background-color: var(--status-belum-bayar);
        }

        .status-sudah_bayar {
            background-color: var(--status-sudah-bayar);
        }

        /* Jika ada status lain, tambahkan CSS-nya */

        .action-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
            margin-right: 5px;
        }

        .action-button.paid {
            background-color: var(--status-sudah-bayar);
            /* Green for "Sudah Bayar" */
        }

        .action-button:hover {
            background-color: #0056b3;
        }

        .action-button.paid:hover {
            background-color: #218838;
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

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form button {
                width: 100%;
                margin-top: 10px;
            }

            .form-group select {
                background-position: right 8px center;
                background-size: 15px;
            }
        }

        @media (max-width: 768px) {

            .data-table th,
            .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }

            /* Sembunyikan kolom tertentu di layar kecil */
            .data-table th:nth-child(1),
            .data-table td:nth-child(1),
            /* ID */
            .data-table th:nth-child(3),
            .data-table td:nth-child(3),
            /* Jenis */
            .data-table th:nth-child(5),
            .data-table td:nth-child(5)

            /* Rincian */
                {
                display: none;
            }
        }

        @media (max-width: 576px) {

            .data-table th:nth-child(8),
            /* Tanggal */
            .data-table td:nth-child(8) {
                display: none;
            }

            /* Menyesuaikan aksi di layar kecil */
            .data-table th:last-child,
            .data-table td:last-child {
                text-align: center;
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
                <li><a href="kelola_tagihan.php" class="active"><i class="fas fa-file-invoice"></i> Kelola Tagihan</a></li>
                <li><a href="datacustomer.php"><i class="fas fa-users"></i> Data Customer</a></li>
                <li><a href="kelola_user.php"><i class="fas fa-user-tie"></i> Kelola User</a></li>
                <li><a href="layanan.php"><i class="fas fa-cogs"></i> Kelola Layanan</a></li>
                <li><a href="atur_jadwal_nonaktif.php"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Kelola Tagihan</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2>Buat Tagihan Baru</h2>

                <div class="form-container">
                    <form method="POST" id="formTagihan">
                        <div class="form-group">
                            <label for="id_booking">Pilih Booking (Status Selesai & Belum Ada Tagihan):</label>
                            <select name="id_booking" id="id_booking" required>
                                <option value="">-- Pilih Booking --</option>
                                <?php
                                // Menggunakan data yang sudah diambil dan disimpan di $booking_selesai_rows
                                if (!empty($booking_selesai_rows)) {
                                    foreach ($booking_selesai_rows as $row) {
                                        $jenis = "Layanan Biasa"; // Hanya Layanan Biasa
                                        $nama  = $row['nama_layanan']; // Langsung ambil nama layanan
                                        $harga_pokok = $row['harga_layanan']; // Langsung ambil harga layanan
                                        $selected = ($selected_id_booking_post == $row['id']) ? 'selected' : '';
                                        echo "<option value='{$row['id']}' data-harga='{$harga_pokok}' $selected>ID#{$row['id']} - {$row['nama_customer']} - [{$jenis}] {$nama} ({$row['tanggal']} {$row['jam']})</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled>Tidak ada booking selesai yang belum tertagih.</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div id="bookingDetails" class="booking-details" style="display: none;">
                            <p><strong>Customer:</strong> <span id="detailCustomer"></span></p>
                            <p><strong>Jenis:</strong> <span id="detailJenis"></span></p>
                            <p><strong>Layanan:</strong> <span id="detailLayananPaket"></span></p>
                            <p><strong>Harga Pokok:</strong> Rp<span id="detailHargaPokok"></span></p>
                        </div>

                        <div class="form-group">
                            <label for="tambahan_biaya">Tambahan Biaya (Rp):</label>
                            <input type="number" name="tambahan" id="tambahan_biaya" value="0" min="0" required oninput="updateBookingDetailsAndTotal()">
                        </div>

                        <div class="form-group">
                            <label for="rincian">Rincian Tambahan:</label>
                            <textarea name="rincian" id="rincian" rows="4" placeholder="Contoh: Penggantian oli, service busi, dll."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="total_display">Total Tagihan (Rp):</label>
                            <input type="text" id="total_display" value="Rp0" readonly>
                            <input type="hidden" name="total_display_hidden" id="total_display_hidden" value="0">
                        </div>

                        <button type="submit" name="buat_tagihan" class="btn-submit"><i class="fas fa-plus-circle"></i> Buat Tagihan</button>
                    </form>
                </div>

                <h2>Daftar Semua Tagihan</h2>

                <form action="export_pdf_tagihan.php" method="GET" class="row g-3 align-items-center mb-3">
                    <div class="col-auto">
                        <label for="bulan" class="col-form-label">Bulan:</label>
                    </div>
                    <div class="col-auto">
                        <select name="bulan" id="bulan" class="form-select" required>
                            <option value="">--Pilih Bulan--</option>
                            <?php
                            for ($i = 1; $i <= 12; $i++) {
                                echo "<option value='$i'>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-auto">
                        <label for="tahun" class="col-form-label">Tahun:</label>
                    </div>
                    <div class="col-auto">
                        <select name="tahun" id="tahun" class="form-select" required>
                            <option value="">--Pilih Tahun--</option>
                            <?php
                            $year = date('Y');
                            for ($i = $year; $i >= $year - 5; $i--) {
                                echo "<option value='$i'>$i</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-danger">Download PDF</button>
                    </div>
                </form>

                <form method="GET" class="search-form">
                    <input type="text" name="search_tagihan" placeholder="Cari customer/layanan/rincian..." value="<?= htmlspecialchars($search_tagihan_query) ?>">
                    <button type="submit"><i class="fas fa-search"></i> Cari</button>
                </form>

                <?php if ($data_tagihan_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Jenis</th>
                                    <th>Layanan</th>
                                    <th>Rincian</th>
                                    <th>Total</th>
                                    <th>Status Bayar</th>
                                    <th>Tanggal Tagihan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tagihan = $data_tagihan_result->fetch_assoc()) {
                                    $jenis = 'Layanan Biasa'; // Hanya Layanan Biasa
                                    $nama  = $tagihan['nama_layanan']; // Langsung ambil nama layanan
                                    $status_bayar_class = strtolower(str_replace('_', '-', $tagihan['status_bayar']));
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tagihan['id']) ?></td>
                                        <td><?= htmlspecialchars($tagihan['nama_customer']) ?></td>
                                        <td><?= $jenis ?></td>
                                        <td><?= htmlspecialchars($nama) ?></td>
                                        <td><?= nl2br(htmlspecialchars($tagihan['rincian'])) ?></td>
                                        <td>Rp<?= number_format($tagihan['total'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="status-bayar-badge status-<?= $status_bayar_class ?>">
                                                <?= ucfirst(str_replace('_', ' ', $tagihan['status_bayar'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($tagihan['tanggal_tagihan']) ?></td>
                                        <td>
                                            <?php if ($tagihan['status_bayar'] === 'belum'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="id_tagihan" value="<?= $tagihan['id'] ?>">
                                                    <input type="hidden" name="new_status" value="sudah">
                                                    <button type="submit" name="update_status_bayar" class="action-button">
                                                        <i class="fas fa-check"></i> Tandai Sudah Bayar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="action-button paid" disabled>
                                                    <i class="fas fa-check-double"></i> Sudah Bayar
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">
                        <?php if ($search_tagihan_query): ?>
                            Tidak ditemukan tagihan sesuai kriteria pencarian "<?= htmlspecialchars($search_tagihan_query) ?>".
                        <?php else: ?>
                            Belum ada data tagihan.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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

        // JavaScript untuk perhitungan total tagihan dan detail booking
        const idBookingSelect = document.getElementById('id_booking');
        const tambahanBiayaInput = document.getElementById('tambahan_biaya');
        const totalDisplay = document.getElementById('total_display');
        const totalDisplayHidden = document.getElementById('total_display_hidden');
        const bookingDetailsDiv = document.getElementById('bookingDetails');
        const detailCustomer = document.getElementById('detailCustomer');
        const detailJenis = document.getElementById('detailJenis');
        const detailLayananPaket = document.getElementById('detailLayananPaket'); // Nama variabel ini tetap, tapi isinya hanya layanan
        const detailHargaPokok = document.getElementById('detailHargaPokok');

        // Data harga booking yang diambil dari PHP
        const bookingDataJs = <?= json_encode($booking_data_for_js); ?>;
        // Data lengkap untuk dropdown untuk menampilkan detail di JS
        const bookingOptionsData = {};
        <?php
        // Iterasi lagi booking_selesai_rows untuk mengisi bookingOptionsData
        if (!empty($booking_selesai_rows)) {
            foreach ($booking_selesai_rows as $row) {
                $jenis = "Layanan Biasa"; // Hanya Layanan Biasa
                $nama  = $row['nama_layanan']; // Langsung ambil nama layanan
                $harga_pokok = $row['harga_layanan']; // Langsung ambil harga layanan
                echo "bookingOptionsData[{$row['id']}] = {
                    customer: '" . htmlspecialchars($row['nama_customer'], ENT_QUOTES) . "',
                    jenis: '{$jenis}',
                    layananPaket: '" . htmlspecialchars($nama, ENT_QUOTES) . "',
                    hargaPokok: {$harga_pokok}
                };";
            }
        }
        ?>

        function formatRupiah(angka) {
            let reverse = angka.toString().split('').reverse().join('');
            let ribuan = reverse.match(/\d{1,3}/g);
            let formatted = ribuan.join('.').split('').reverse().join('');
            return formatted;
        }

        function updateBookingDetailsAndTotal() {
            const selectedBookingId = idBookingSelect.value;
            const hargaTambahan = parseFloat(tambahanBiayaInput.value) || 0;

            let hargaPokok = 0;
            if (selectedBookingId && bookingDataJs[selectedBookingId]) {
                hargaPokok = parseFloat(bookingDataJs[selectedBookingId]);
                const details = bookingOptionsData[selectedBookingId];
                detailCustomer.textContent = details.customer;
                detailJenis.textContent = details.jenis;
                detailLayananPaket.textContent = details.layananPaket;
                detailHargaPokok.textContent = formatRupiah(details.hargaPokok);
                bookingDetailsDiv.style.display = 'block'; // Tampilkan detail
            } else {
                bookingDetailsDiv.style.display = 'none'; // Sembunyikan jika tidak ada pilihan
            }

            const total = hargaPokok + hargaTambahan;
            totalDisplay.value = 'Rp' + formatRupiah(total);
            totalDisplayHidden.value = total; // Simpan nilai total numerik
        }

        // Panggil saat halaman dimuat
        document.addEventListener('DOMContentLoaded', () => {
            // Jika ada nilai yang dipilih dari POST saat load (misal setelah submit form pertama kali gagal)
            if (idBookingSelect.value) {
                updateBookingDetailsAndTotal();
            }
        });

        idBookingSelect.addEventListener('change', updateBookingDetailsAndTotal);
        tambahanBiayaInput.addEventListener('input', updateBookingDetailsAndTotal);
    </script>
</body>

</html>
<?php
$stmt_tagihan->close();
//booking_selesai_result tidak perlu close jika sudah di fetch_assoc()
$koneksi->close();
?>