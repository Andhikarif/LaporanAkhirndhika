<?php
session_start();
include 'koneksi.php';

// Akses hanya pegawai
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pegawai') {
    header("Location: login.php");
    exit;
}

// Mengambil data untuk cards
// Total Booking Diterima (siap dikerjakan)
$query_diterima = "SELECT COUNT(*) AS total FROM booking WHERE status = 'diterima'";
$result_diterima = $koneksi->query($query_diterima);
$total_diterima = $result_diterima->fetch_assoc()['total'];

// Total Booking Dikerjakan
$query_dikerjakan = "SELECT COUNT(*) AS total FROM booking WHERE status = 'dikerjakan'";
$result_dikerjakan = $koneksi->query($query_dikerjakan);
$total_dikerjakan = $result_dikerjakan->fetch_assoc()['total'];

// Total Booking Selesai Hari Ini
$today = date('Y-m-d');
$query_selesai_hari_ini = "SELECT COUNT(*) AS total FROM booking WHERE status = 'selesai' AND tanggal_selesai = '$today'";
$result_selesai_hari_ini = $koneksi->query($query_selesai_hari_ini);
$total_selesai_hari_ini = $result_selesai_hari_ini->fetch_assoc()['total'];

// Total Booking Pending (Menunggu Konfirmasi Admin, tapi tetap ditampilkan sebagai informasi)
// Ini biasanya booking baru yang belum direspon admin, tapi penting juga untuk pegawai tahu ada apa saja
$query_pending = "SELECT COUNT(*) AS total FROM booking WHERE status = 'pending'";
$result_pending = $koneksi->query($query_pending);
$total_pending = $result_pending->fetch_assoc()['total'];


// Mengambil daftar booking yang statusnya "diterima" atau "dikerjakan" untuk tabel utama
$result_bookings = $koneksi->query("
    SELECT b.*, u.nama AS nama_customer, 
           l.nama_layanan
    FROM booking b
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    WHERE b.status IN ('diterima', 'dikerjakan')
    ORDER BY b.tanggal ASC, b.jam ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pegawai</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk konsistensi tema */
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

            /* Warna tambahan untuk cards/tombol */
            --card-blue: #007bff;
            --card-green: #28a745;
            --card-orange: #fd7e14;
            --card-red: #dc3545;
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

        /* Sidebar (disederhanakan untuk Pegawai) */
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

        /* Content Area */
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

        /* Cards Statistik */
        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: var(--secondary-bg);
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-light);
        }

        .card-icon {
            font-size: 2.5em;
            color: white;
            padding: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
        }
        .card-icon.blue { background-color: var(--card-blue); }
        .card-icon.green { background-color: var(--card-green); }
        .card-icon.orange { background-color: var(--card-orange); }
        .card-icon.red { background-color: var(--card-red); }

        .card-content h3 {
            margin: 0;
            font-size: 1.1em;
            color: var(--text-muted);
            font-weight: 500;
        }

        .card-content p {
            margin: 5px 0 0;
            font-size: 2.2em;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Tabel Styling */
        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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

        .data-table button {
            background-color: var(--btn-primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }
        .data-table button:hover {
            background-color: var(--btn-primary-hover);
        }
        .data-table button.finish-btn {
            background-color: var(--card-green); /* Warna hijau untuk Selesai */
        }
        .data-table button.finish-btn:hover {
            background-color: #1e7e34; /* Darker green */
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
            .card-container {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }
            .card {
                flex-direction: column;
                text-align: center;
            }
            .card-icon {
                margin-bottom: 10px;
            }
        }
        @media (max-width: 576px) {
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-info {
                margin-top: 10px;
            }
            .card-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">Bengkel SAC</div>
            <ul class="sidebar-menu">
                <li><a href="dashboard_pegawai.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Dashboard Mekanik</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>!
                </div>
            </div>

            <div class="content-area">
                <h2>Ringkasan Booking</h2>
                <div class="card-container">
                    <div class="card">
                        <div class="card-icon blue"><i class="fas fa-handshake"></i></div>
                        <div class="card-content">
                            <h3>Booking Diterima</h3>
                            <p><?= $total_diterima ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon orange"><i class="fas fa-tools"></i></div>
                        <div class="card-content">
                            <h3>Booking Dikerjakan</h3>
                            <p><?= $total_dikerjakan ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="card-content">
                            <h3>Selesai Hari Ini</h3>
                            <p><?= $total_selesai_hari_ini ?></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="card-content">
                            <h3>Booking Pending</h3>
                            <p><?= $total_pending ?></p>
                        </div>
                    </div>
                </div>

                <h2>Daftar Booking Terbaru (Diterima / Dikerjakan)</h2>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Customer</th>
                                <th>Layanan</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_bookings->num_rows > 0): ?>
                                <?php
                                $no = 1;
                                while ($row = $result_bookings->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_customer']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_layanan']) ?></td>
                                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                    <td><?= htmlspecialchars($row['jam']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                            <?= htmlspecialchars(ucwords($row['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'diterima' || $row['status'] === 'dikerjakan') : ?>
                                            <form method="POST" action="kelola_booking_pegawai.php" style="display:inline;">
                                                <input type="hidden" name="id_booking" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="current_status" value="<?= $row['status'] ?>">
                                                <button type="submit" class="<?= $row['status'] === 'diterima' ? 'start-btn' : 'finish-btn' ?>">
                                                    <i class="fas <?= $row['status'] === 'diterima' ? 'fa-play-circle' : 'fa-check-circle' ?>"></i> 
                                                    <?= $row['status'] === 'diterima' ? 'Mulai Kerjakan' : 'Selesaikan' ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted);">Tidak ada booking dengan status Diterima atau Dikerjakan saat ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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