<?php
session_start();
include 'koneksi.php';

// Cek akses admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Handle hapus layanan
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Periksa apakah layanan ini digunakan dalam booking sebelum dihapus
    // Bagian paket_layanan dihapus karena tabel sudah tidak ada
    $check_booking = $koneksi->query("SELECT COUNT(*) FROM booking WHERE id_layanan = $id AND id_layanan IS NOT NULL"); // Pastikan id_layanan tidak null
    $in_booking = $check_booking->fetch_row()[0];

    if ($in_booking > 0) {
        $_SESSION['error_message'] = "Layanan tidak bisa dihapus karena masih terhubung dengan booking.";
    } else {
        $koneksi->query("DELETE FROM layanan WHERE id = $id");
        $_SESSION['success_message'] = "Layanan berhasil dihapus.";
    }
    header("Location: layanan.php");
    exit;
}

// Handle simpan layanan (tambah & edit)
if (isset($_POST['simpan'])) {
    $id = $_POST['id'];
    $nama_layanan = $koneksi->real_escape_string($_POST['nama_layanan']);
    $harga = $koneksi->real_escape_string($_POST['harga']);
    $deskripsi = $koneksi->real_escape_string($_POST['deskripsi']);

    if ($id == '') {
        // Tambah layanan baru
        $stmt = $koneksi->prepare("INSERT INTO layanan (nama_layanan, deskripsi, harga) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $nama_layanan, $deskripsi, $harga);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Layanan baru berhasil ditambahkan.";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan layanan: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Edit layanan
        $stmt = $koneksi->prepare("UPDATE layanan SET nama_layanan=?, deskripsi=?, harga=? WHERE id=?");
        $stmt->bind_param("ssdi", $nama_layanan, $deskripsi, $harga, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Layanan berhasil diperbarui.";
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui layanan: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: layanan.php");
    exit;
}

// Ambil semua layanan
$data_layanan = $koneksi->query("SELECT * FROM layanan ORDER BY nama_layanan ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Layanan - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna, konsisten dengan kelola_booking.php */
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

        /* Button Group (untuk tombol "Tambah Layanan") */
        .button-group {
            margin-bottom: 25px;
            display: flex;
            justify-content: flex-end; /* Posisikan ke kanan */
        }

        .btn {
            display: inline-flex; /* Untuk icon dan teks sejajar */
            align-items: center;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, color 0.3s ease;
            cursor: pointer;
            border: none;
            text-align: center;
            font-size: 0.95em;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--btn-primary);
            color: var(--secondary-bg);
        }

        .btn-primary:hover {
            background-color: var(--btn-primary-hover);
        }

        .btn-info {
            background-color: var(--btn-info);
            color: var(--secondary-bg);
        }

        .btn-info:hover {
            background-color: var(--btn-info-hover);
        }

        .btn-danger {
            background-color: var(--btn-danger);
            color: var(--secondary-bg);
        }

        .btn-danger:hover {
            background-color: var(--btn-danger-hover);
        }

        .btn-secondary {
            background-color: var(--btn-secondary);
            color: var(--secondary-bg);
        }

        .btn-secondary:hover {
            background-color: var(--btn-secondary-hover);
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

        .data-table td .btn {
            padding: 6px 12px;
            font-size: 0.85em;
            margin-right: 5px; /* Jarak antar tombol di dalam tabel */
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

        /* Modal Styles (untuk form tambah/edit layanan) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 2000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--secondary-bg);
            margin: auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-button {
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--text-dark);
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content h3 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 1.5em;
            text-align: center;
        }

        .modal-content form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-content form input[type="text"],
        .modal-content form input[type="number"],
        .modal-content form textarea {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }
        .modal-content form input[type="text"]:focus,
        .modal-content form input[type="number"]:focus,
        .modal-content form textarea:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .modal-content form textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-content .button-group {
            justify-content: flex-start; /* Meng override justify-content: flex-end dari button-group umum */
            margin-top: 20px;
            gap: 10px; /* Jarak antar tombol dalam modal */
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
            .button-group {
                justify-content: center; /* Posisikan tombol tambah di tengah pada mobile */
            }
        }

        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }
            /* Sembunyikan kolom deskripsi di layar kecil */
            .data-table th:nth-child(3),
            .data-table td:nth-child(3) {
                display: none;
            }
        }
        @media (max-width: 576px) {
            .data-table td .btn {
                display: block; /* Tombol aksi jadi satu kolom */
                width: 100%;
                margin-bottom: 5px;
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
                <li><a href="layanan.php" class="active"><i class="fas fa-cogs"></i> Kelola Layanan</a></li>
                <li><a href="atur_jadwal_nonaktif.php"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Kelola Layanan</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2>Daftar Layanan</h2>

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

                <div class="button-group">
                    <button class="btn btn-primary" onclick="openModal('add')"><i class="fas fa-plus"></i> Tambah Layanan</button>
                </div>

                <?php if ($data_layanan->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Layanan</th>
                                    <th>Deskripsi</th>
                                    <th>Harga (Rp)</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($row = $data_layanan->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$no}</td>
                                        <td>" . htmlspecialchars($row['nama_layanan']) . "</td>
                                        <td>" . htmlspecialchars($row['deskripsi']) . "</td>
                                        <td>" . number_format($row['harga'], 0, ',', '.') . "</td>
                                        <td>
                                            <button class='btn btn-info' onclick=\"openModal('edit', " . htmlspecialchars(json_encode($row)) . ")\"><i class='fas fa-edit'></i> Edit</button>
                                            <a href='layanan.php?hapus={$row['id']}' class='btn btn-danger' onclick=\"return confirm('Apakah Anda yakin ingin menghapus layanan ini? Tindakan ini tidak dapat dibatalkan jika layanan ini sudah terhubung dengan booking.')\"><i class='fas fa-trash-alt'></i> Hapus</a>
                                        </td>
                                    </tr>";
                                    $no++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">Belum ada data layanan.</p>
                <?php endif; ?>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>

    <div id="layananModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Tambah Layanan Baru</h3>
            <form id="layananForm" method="POST" action="layanan.php">
                <input type="hidden" name="id" id="layananId">

                <label for="nama_layanan">Nama Layanan:</label>
                <input type="text" name="nama_layanan" id="nama_layanan" required>

                <label for="deskripsi">Deskripsi:</label>
                <textarea name="deskripsi" id="deskripsi"></textarea>

                <label for="harga">Harga (Rp):</label>
                <input type="number" name="harga" id="harga" required min="0">

                <div class="button-group" style="justify-content: flex-start; margin-top: 20px;">
                    <button type="submit" name="simpan" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // JavaScript untuk Toggle Sidebar di Mobile (sama seperti kelola_booking.php)
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


        // JavaScript untuk Modal Tambah/Edit Layanan
        var layananModal = document.getElementById("layananModal");

        function openModal(mode, data = null) {
            document.getElementById('layananForm').reset(); // Reset form setiap kali dibuka
            document.getElementById('layananId').value = '';
            document.getElementById('nama_layanan').value = '';
            document.getElementById('deskripsi').value = '';
            document.getElementById('harga').value = '';

            if (mode === 'add') {
                document.getElementById('modalTitle').innerText = 'Tambah Layanan Baru';
            } else if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = 'Edit Layanan';
                document.getElementById('layananId').value = data.id;
                document.getElementById('nama_layanan').value = data.nama_layanan;
                document.getElementById('deskripsi').value = data.deskripsi;
                document.getElementById('harga').value = data.harga;
            }
            layananModal.style.display = "flex"; // Mengubah display menjadi flex agar center
        }

        function closeModal() {
            layananModal.style.display = "none";
        }

        // Tutup modal jika klik di luar area konten modal
        window.onclick = function(event) {
            if (event.target == layananModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
<?php
$koneksi->close();
?>