<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Proses update status booking
if (isset($_POST['update_status'])) {
    $id_booking = $_POST['id_booking'];
    $status     = $_POST['status'];

    $stmt_update = $koneksi->prepare("UPDATE booking SET status=? WHERE id=?");
    if ($stmt_update) {
        $stmt_update->bind_param("si", $status, $id_booking);
        if ($stmt_update->execute()) {
            echo "<script>alert('Status booking berhasil diperbarui.'); window.location='kelola_booking.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui status booking: " . $stmt_update->error . "'); window.location='kelola_booking.php';</script>";
        }
        $stmt_update->close();
    } else {
        echo "<script>alert('Gagal menyiapkan statement update: " . $koneksi->error . "'); window.location='kelola_booking.php';</script>";
    }
    exit;
}

// Inisialisasi filter dan pencarian
$search_query = '';
$filter_status = '';

// Cek parameter pencarian
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
}

// Cek parameter filter status
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filter_status = $_GET['status'];
}

// Bangun query dinamis berdasarkan filter dan pencarian
// Menghapus JOIN dan kolom terkait 'paket'
$sql = "
    SELECT
        b.id, b.tanggal, b.jam, b.status, b.id_layanan,
        b.merk_mobil, b.tahun_mobil, b.transmisi, b.warna_mobil,
        u.nama AS nama_customer,
        l.nama_layanan
    FROM booking b
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    WHERE 1=1 "; // Kondisi dasar yang selalu true

$params = [];
$types = '';

if ($search_query) {
    // Menghapus pencarian berdasarkan p.nama_paket
    $sql .= " AND (u.nama LIKE ? OR l.nama_layanan LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($filter_status && $filter_status !== 'semua') {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql .= " ORDER BY b.tanggal DESC, b.jam ASC";

// Siapkan dan jalankan statement
$stmt_select = $koneksi->prepare($sql);

if ($params) {
    // Gunakan call_user_func_array untuk bind_param dengan array dinamis
    $bind_names = [$types];
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i]; // Reference required for bind_param
    }
    call_user_func_array([$stmt_select, 'bind_param'], $bind_names);
}

$stmt_select->execute();
$result = $stmt_select->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking - Bengkel XYZ Admin</title>
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

            /* Warna untuk status/notifikasi */
            --status-pending: #ffc107; /* Kuning */
            --status-diterima: #28a745; /* Hijau */
            --status-dibatalkan: #dc3545; /* Merah */
            --status-selesai: #17a2b8; /* Biru/Toska */
            --status-dikerjakan: #0dcaf0; /* Biru Cyan untuk status 'dikerjakan' */
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

        /* Filter and Search Forms */
        .filter-search-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
        }

        .filter-search-container form {
            display: flex;
            gap: 10px;
            flex-grow: 1;
        }

        .filter-search-container input[type="text"],
        .filter-search-container select {
            padding: 10px 15px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .filter-search-container input[type="text"]:focus,
        .filter-search-container select:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .filter-search-container button {
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

        .filter-search-container button:hover {
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
            vertical-align: top; /* Agar konten multi-baris sejajar di atas */
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

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            color: #ffffff;
            text-transform: capitalize;
        }

        .status-pending { background-color: var(--status-pending); }
        .status-diterima { background-color: var(--status-diterima); }
        .status-dibatalkan { background-color: var(--status-dibatalkan); }
        .status-selesai { background-color: var(--status-selesai); }
        .status-dikerjakan { background-color: var(--status-dikerjakan); color: var(--text-dark); /* Agar teks terlihat jelas di latar belakang cyan */ }


        /* Tombol Aksi "Ubah Status" */
        .action-button {
            padding: 8px 12px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            white-space: nowrap; /* Mencegah teks tombol pecah */
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .action-button:hover {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
        }

        /* Aksi Dropdown/Select */
        .action-select {
            padding: 8px 10px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            background-color: var(--secondary-bg);
            cursor: pointer;
            font-size: 0.9em;
            appearance: none; /* Menghilangkan default arrow di beberapa browser */
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23333" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 15px;
        }
        .action-select:focus {
            border-color: var(--accent-color);
            outline: none;
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

        .back-link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        /* Modal Styling */
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
            text-align: center;
            position: relative;
        }

        .modal-content h3 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .modal-content p {
            margin-bottom: 25px;
            color: var(--text-muted);
            font-size: 1.1em;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .modal-buttons button {
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .modal-buttons .btn-confirm {
            background-color: var(--status-diterima);
            color: white;
            border: none;
        }
        .modal-buttons .btn-confirm:hover {
            background-color: #218838;
        }

        .modal-buttons .btn-cancel {
            background-color: var(--accent-color);
            color: white;
            border: none;
        }
        .modal-buttons .btn-cancel:hover {
            background-color: var(--accent-hover);
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
            .filter-search-container {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-search-container form {
                flex-direction: column;
                width: 100%;
                gap: 5px;
            }
            .filter-search-container input[type="text"],
            .filter-search-container select,
            .filter-search-container button {
                width: 100%;
            }
            .data-table th, .data-table td {
                padding: 10px 8px; /* Sedikit perkecil padding */
                font-size: 0.85em; /* perkecil font */
            }
        }

        @media (max-width: 768px) {
            .data-table th:nth-child(1),
            .data-table td:nth-child(1) /* No */
            /* .data-table th:nth-child(3), -- Removed as 'Jenis' column is now implicitly gone or redundant*/
            /* .data-table td:nth-child(3) */
            {
                display: none;
            }
            /* Sesuaikan lebar kolom agar tidak terlalu padat */
            .data-table th, .data-table td {
                word-break: break-word; /* Memecah kata panjang */
            }
        }
        @media (max-width: 576px) {
            .data-table th:nth-child(4), /* Tanggal (previously 5th, shifted due to column removal) */
            .data-table td:nth-child(4),
            .data-table th:nth-child(5), /* Jam (previously 6th, shifted) */
            .data-table td:nth-child(5) {
                display: none;
            }
            .modal-content {
                padding: 20px;
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
                <li><a href="kelola_booking.php" class="active"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
                <li><a href="kelola_tagihan.php"><i class="fas fa-file-invoice"></i> Kelola Tagihan</a></li>
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
                <h1>Kelola Booking</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2>Daftar Booking</h2>

                <div class="filter-search-container">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Cari customer/layanan..." value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit"><i class="fas fa-search"></i> Cari</button>
                    </form>

                    <form method="GET" class="filter-form">
                        <select name="status" onchange="this.form.submit()">
                            <option value="semua" <?= ($filter_status == 'semua' || empty($filter_status)) ? 'selected' : '' ?>>Semua Status</option>
                            <option value="pending" <?= ($filter_status == 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="diterima" <?= ($filter_status == 'diterima') ? 'selected' : '' ?>>Diterima</option>
                            <option value="dikerjakan" <?= ($filter_status == 'dikerjakan') ? 'selected' : '' ?>>Dikerjakan</option>
                            <option value="dibatalkan" <?= ($filter_status == 'dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
                            <option value="selesai" <?= ($filter_status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                        </select>
                        <?php if ($search_query): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                        <?php endif; ?>
                    </form>
                </div>


                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>Detail Mobil</th>
                                    <th>Layanan</th> <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; while ($row = $result->fetch_assoc()) {
                                    // Menghapus logika untuk 'paket'
                                    $nama_layanan = htmlspecialchars($row['nama_layanan']);
                                    $status_class = strtolower(str_replace(' ', '-', $row['status']));
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nama_customer']) ?></td>
                                        <td>
                                            Merk: <?= htmlspecialchars($row['merk_mobil']) ?><br>
                                            Tahun: <?= htmlspecialchars($row['tahun_mobil']) ?><br>
                                            Transmisi: <?= htmlspecialchars(ucfirst($row['transmisi'])) ?><br>
                                            Warna: <?= htmlspecialchars($row['warna_mobil']) ?>
                                        </td>
                                        <td><?= $nama_layanan ?></td> <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                        <td><?= htmlspecialchars($row['jam']) ?></td>
                                        <td><span class="status-badge status-<?= $status_class ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                        <td>
                                            <?php if ($row['status'] === 'pending' || $row['status'] === 'diterima' || $row['status'] === 'dikerjakan') { ?>
                                                <button class="action-button"
                                                                data-id="<?= $row['id'] ?>"
                                                                data-customer="<?= htmlspecialchars($row['nama_customer']) ?>"
                                                                data-nama="<?= $nama_layanan ?>" data-tanggal="<?= htmlspecialchars($row['tanggal']) ?>"
                                                                data-jam="<?= htmlspecialchars($row['jam']) ?>"
                                                                data-current-status="<?= htmlspecialchars($row['status']) ?>">
                                                    Ubah Status
                                                </button>
                                            <?php } else { ?>
                                                <span class="text-muted">Tidak dapat diubah</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-muted);">
                        <?php if ($search_query || ($filter_status && $filter_status !== 'semua')): ?>
                            Tidak ditemukan booking sesuai kriteria.
                        <?php else: ?>
                            Belum ada data booking.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>

    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3>Ubah Status Booking</h3>
            <p>Anda akan mengubah status booking untuk:</p>
            <p>
                <strong>Customer:</strong> <span id="modalCustomerName"></span><br>
                <strong>Layanan:</strong> <span id="modalServiceName"></span><br> <strong>Tanggal & Jam:</strong> <span id="modalDateTime"></span><br>
                <strong>Status Saat Ini:</strong> <span id="modalCurrentStatus" class="status-badge"></span>
            </p>
            <form id="updateStatusForm" method="POST">
                <input type="hidden" name="id_booking" id="modalBookingId">
                <select name="status" id="modalNewStatusSelect" class="action-select" style="width: 100%; margin-bottom: 20px;">
                    </select>
                <input type="hidden" name="update_status" value="1">
                <div class="modal-buttons">
                    <button type="submit" class="btn-confirm">Konfirmasi</button>
                    <button type="button" class="btn-cancel" id="cancelModal">Batal</button>
                </div>
            </form>
        </div>
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

        // JavaScript untuk Modal Konfirmasi Status
        const statusModal = document.getElementById('statusModal');
        const cancelButton = document.getElementById('cancelModal');
        const actionButtons = document.querySelectorAll('.action-button');

        actionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bookingId = this.dataset.id;
                const customerName = this.dataset.customer;
                const serviceName = this.dataset.nama; // This now only gets 'nama_layanan'
                const dateTime = this.dataset.tanggal + ' ' + this.dataset.jam;
                const currentStatus = this.dataset.currentStatus;

                document.getElementById('modalBookingId').value = bookingId;
                document.getElementById('modalCustomerName').textContent = customerName;
                document.getElementById('modalServiceName').textContent = serviceName;
                document.getElementById('modalDateTime').textContent = dateTime;

                const modalCurrentStatusSpan = document.getElementById('modalCurrentStatus');
                modalCurrentStatusSpan.textContent = currentStatus;
                modalCurrentStatusSpan.className = 'status-badge status-' + currentStatus.toLowerCase().replace(' ', '-');

                // Set pilihan default di select box modal berdasarkan status saat ini
                const newStatusSelect = document.getElementById('modalNewStatusSelect');
                newStatusSelect.innerHTML = '<option value="">-- Pilih Status Baru --</option>'; // Reset opsi

                // Logika untuk menambahkan opsi status baru
                if (currentStatus === 'pending') {
                    newStatusSelect.innerHTML += '<option value="diterima">Diterima</option>';
                    newStatusSelect.innerHTML += '<option value="dibatalkan">Dibatalkan</option>';
                } else if (currentStatus === 'diterima') {
                    newStatusSelect.innerHTML += '<option value="dikerjakan">Dikerjakan</option>';
                    newStatusSelect.innerHTML += '<option value="dibatalkan">Dibatalkan</option>';
                } else if (currentStatus === 'dikerjakan') {
                    newStatusSelect.innerHTML += '<option value="selesai">Selesai</option>';
                    newStatusSelect.innerHTML += '<option value="dibatalkan">Dibatalkan</option>';
                }
                // Jika status sudah "dibatalkan" atau "selesai", tidak ada opsi ubah status

                statusModal.style.display = 'flex'; // Mengubah display menjadi flex untuk centering
            });
        });

        cancelButton.addEventListener('click', () => {
            statusModal.style.display = 'none';
        });

        // Tutup modal jika klik di luar area konten modal
        window.addEventListener('click', (event) => {
            if (event.target == statusModal) {
                statusModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
<?php
$stmt_select->close();
$koneksi->close();
?>