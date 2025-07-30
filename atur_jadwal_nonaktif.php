<?php
// atur_jadwal_nonaktif.php
session_start();
include 'koneksi.php';

// Akses hanya untuk admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Daftar jam kerja bengkel (HARUS SAMA DENGAN form_booking.php)
$jam_kerja = ["08:00", "09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00"];

// --- Proses Hapus Slot Nonaktif (Dipindahkan keluar dari blok POST) ---
if (isset($_GET['hapus_id'])) { 
    $id_nonaktif = (int)$_GET['hapus_id'];
    $stmt_delete = $koneksi->prepare("DELETE FROM jadwal_nonaktif WHERE id=?");
    $stmt_delete->bind_param("i", $id_nonaktif);
    if ($stmt_delete->execute()) {
        $_SESSION['success_message'] = "Slot nonaktif berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus slot nonaktif: " . $stmt_delete->error;
    }
    $stmt_delete->close();
    header("Location: atur_jadwal_nonaktif.php"); // Redirect setelah operasi
    exit;
}
// --- Akhir Proses Hapus ---


// Proses simpan slot nonaktif (Ini hanya akan dieksekusi jika request method adalah POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal = $_POST['tanggal'];
    
    // Inisialisasi alasan berdasarkan tombol yang ditekan
    $alasan_libur = isset($_POST['alasan_libur']) ? $koneksi->real_escape_string($_POST['alasan_libur']) : '';
    $alasan_single = isset($_POST['alasan']) ? $koneksi->real_escape_string($_POST['alasan']) : '';

    if (isset($_POST['nonaktifkan_hari_libur'])) { // Jika tombol "Nonaktifkan Seluruh Jam (Hari Libur)" ditekan
        $sukses_nonaktif = 0;
        $gagal_nonaktif = 0;
        foreach ($jam_kerja as $jam) {
            // Cek apakah sudah ada
            $stmt_check = $koneksi->prepare("SELECT COUNT(*) FROM jadwal_nonaktif WHERE tanggal=? AND jam=?");
            $stmt_check->bind_param("ss", $tanggal, $jam);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $count = $result_check->fetch_row()[0];
            $stmt_check->close();

            if ($count > 0) {
                $gagal_nonaktif++;
            } else {
                $stmt_insert = $koneksi->prepare("INSERT INTO jadwal_nonaktif (tanggal, jam, alasan) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("sss", $tanggal, $jam, $alasan_libur);
                if ($stmt_insert->execute()) {
                    $sukses_nonaktif++;
                }
                $stmt_insert->close();
            }
        }
        $_SESSION['success_message'] = "Berhasil menonaktifkan {$sukses_nonaktif} slot. {$gagal_nonaktif} slot sudah dinonaktifkan sebelumnya.";
    } elseif (isset($_POST['nonaktifkan_slot_ini'])) { // Proses normal untuk satu jam
        $jam = $_POST['jam'];
        // Cek apakah sudah ada
        $stmt_check = $koneksi->prepare("SELECT COUNT(*) FROM jadwal_nonaktif WHERE tanggal=? AND jam=?");
        $stmt_check->bind_param("ss", $tanggal, $jam);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $count = $result_check->fetch_row()[0];
        $stmt_check->close();

        if ($count > 0) {
            $_SESSION['error_message'] = "Slot ini sudah dinonaktifkan!";
        } else {
            $stmt_insert = $koneksi->prepare("INSERT INTO jadwal_nonaktif (tanggal, jam, alasan) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $tanggal, $jam, $alasan_single);
            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Slot berhasil dinonaktifkan.";
            } else {
                $_SESSION['error_message'] = "Gagal menonaktifkan slot: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
    header("Location: atur_jadwal_nonaktif.php"); // Redirect setelah operasi POST
    exit;
}

// Ambil data untuk ditampilkan
$data = $koneksi->query("SELECT * FROM jadwal_nonaktif ORDER BY tanggal ASC, jam ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jam Nonaktif - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna, konsisten dengan tema sebelumnya */
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

            /* Warna tambahan untuk tombol/alert */
            --btn-primary: #007bff;
            --btn-primary-hover: #0056b3;
            --btn-info: #17a2b8;
            --btn-info-hover: #138496;
            --btn-danger: #dc3545;
            --btn-danger-hover: #c82333;
            --btn-secondary: #6c757d;
            --btn-secondary-hover: #5a6268;
            --alert-success-bg: #d4edda;
            --alert-success-text: #155724;
            --alert-error-bg: #f8d7da;
            --alert-error-text: #721c24;
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

        /* Sidebar (sama seperti kelola_booking.php) */
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
            font-size: 1.5em;
            color: var(--text-dark);
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        /* Alert Messages */
        .alert {
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.95em;
            text-align: center;
            border: 1px solid transparent;
        }

        .alert.success {
            background-color: var(--alert-success-bg);
            color: var(--alert-success-text);
            border-color: darken(var(--alert-success-bg), 5%);
        }

        .alert.error {
            background-color: var(--alert-error-bg);
            color: var(--alert-error-text);
            border-color: darken(var(--alert-error-bg), 5%);
        }

        /* Form Styling */
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            background-color: var(--primary-bg);
        }

        .form-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-section input[type="date"],
        .form-section input[type="text"],
        .form-section select {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .form-section input[type="date"]:focus,
        .form-section input[type="text"]:focus,
        .form-section select:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .form-section button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: var(--btn-danger);
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        .form-section button i {
            margin-right: 8px;
        }

        .form-section button:hover {
            background-color: var(--btn-danger-hover);
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
        }

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

        .data-table td .btn-danger {
            padding: 6px 12px;
            font-size: 0.85em;
        }
        .data-table td .btn-danger i {
            margin-right: 5px;
        }

        /* Back Link */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link i {
            margin-right: 5px;
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
        }
        @media (max-width: 576px) {
            .form-section button {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
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
                <li><a href="atur_jadwal_nonaktif.php" class="active"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Kelola Jadwal</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2>Atur Slot Booking Nonaktif</h2>

                <?php
                // Tampilkan pesan sukses atau error dari session
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert success">' . $_SESSION['success_message'] . '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert error">' . $_SESSION['error_message'] . '</div>';
                    unset($_SESSION['error_message']);
                }
                ?>

                <div class="form-section">
                    <form method="POST">
                        <label for="tanggal">Tanggal:</label>
                        <input type="date" name="tanggal" id="tanggal" required value="<?= isset($_POST['tanggal']) ? htmlspecialchars($_POST['tanggal']) : date('Y-m-d') ?>">
                        
                        <h3>Nonaktifkan Slot Jam Tertentu</h3>
                        <label for="jam">Jam:</label>
                        <select name="jam" id="jam">
                            <option value="">-- Pilih Jam --</option>
                            <?php
                            foreach ($jam_kerja as $j) {
                                echo "<option value='".htmlspecialchars($j)."'>".htmlspecialchars($j)."</option>";
                            }
                            ?>
                        </select>
                        <label for="alasan">Alasan (opsional):</label>
                        <input type="text" name="alasan" id="alasan" placeholder="Contoh: Istirahat, Maintenance">
                        <button type="submit" name="nonaktifkan_slot_ini"><i class="fas fa-times-circle"></i> Nonaktifkan Slot Ini</button>
                    </form>
                </div>

                <div class="form-section" style="margin-top: 20px;">
                    <form method="POST">
                        <label for="tanggal_libur">Tanggal Hari Libur:</label>
                        <input type="date" name="tanggal" id="tanggal_libur" required value="<?= isset($_POST['tanggal']) ? htmlspecialchars($_POST['tanggal']) : date('Y-m-d') ?>">
                        <label for="alasan_libur">Alasan Hari Libur (opsional):</label>
                        <input type="text" name="alasan_libur" id="alasan_libur" placeholder="Contoh: Libur Nasional, Acara Bengkel">
                        <button type="submit" name="nonaktifkan_hari_libur"><i class="fas fa-calendar-times"></i> Nonaktifkan Seluruh Jam (Hari Libur)</button>
                    </form>
                </div>


                <h3>Daftar Slot Nonaktif Saat Ini</h3>
                <?php if ($data->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Alasan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($row = $data->fetch_assoc()) {
                                    echo "<tr>
                                            <td>{$no}</td>
                                            <td>" . htmlspecialchars($row['tanggal']) . "</td>
                                            <td>" . htmlspecialchars($row['jam']) . "</td>
                                            <td>" . htmlspecialchars($row['alasan']) . "</td>
                                            <td>
                                                <a href='atur_jadwal_nonaktif.php?hapus_id={$row['id']}' class='btn btn-danger' onclick=\"return confirm('Apakah Anda yakin ingin mengaktifkan kembali slot ini?')\"><i class='fas fa-trash-alt'></i> Hapus</a>
                                            </td>
                                        </tr>";
                                    $no++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">Belum ada slot jam nonaktif yang diatur.</p>
                <?php endif; ?>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>

    <script>
        // JavaScript untuk Toggle Sidebar di Mobile (sama persis)
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

        // Inisialisasi posisi sidebar saat halaman dimuat
        if (window.innerWidth <= 992) {
            sidebar.classList.add('hidden');
            mainContent.style.marginLeft = '0';
        }
    </script>
</body>
</html>
<?php
$koneksi->close();
?>