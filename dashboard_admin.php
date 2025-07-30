<?php
session_start();
include 'koneksi.php';

// Verifikasi role admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Menggunakan prepared statements untuk query yang lebih aman dan efisien
$total_user = 0;
$stmt_user = $koneksi->prepare("SELECT COUNT(*) AS total FROM users WHERE role='customer'");
if ($stmt_user) {
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $total_user = $result_user->fetch_assoc()['total'];
    $stmt_user->close();
}

$total_pegawai = 0;
$stmt_pegawai = $koneksi->prepare("SELECT COUNT(*) AS total FROM users WHERE role='pegawai'");
if ($stmt_pegawai) {
    $stmt_pegawai->execute();
    $result_pegawai = $stmt_pegawai->get_result();
    $total_pegawai = $result_pegawai->fetch_assoc()['total'];
    $stmt_pegawai->close();
}

$total_booking = 0;
$stmt_booking = $koneksi->prepare("SELECT COUNT(*) AS total FROM booking");
if ($stmt_booking) {
    $stmt_booking->execute();
    $result_booking = $stmt_booking->get_result();
    $total_booking = $result_booking->fetch_assoc()['total'];
    $stmt_booking->close();
}

// Hitung jumlah booking pending
$pending = 0;
$stmt_pending = $koneksi->prepare("SELECT COUNT(*) AS jumlah FROM booking WHERE status = 'pending'");
if ($stmt_pending) {
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    $pending = $result_pending->fetch_assoc()['jumlah'];
    $stmt_pending->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bengkel XYZ</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            /* Warna netral dan lembut */
            --primary-bg: #f8f9fa; /* Latar belakang utama yang sangat terang, hampir putih */
            --secondary-bg: #ffffff; /* Latar belakang untuk panel/kartu */
            --sidebar-bg: #e9ecef; /* Latar belakang sidebar abu-abu terang */
            --sidebar-text: #495057; /* Teks gelap untuk sidebar */
            --sidebar-hover: #dee2e6; /* Hover sidebar lebih gelap sedikit */
            --accent-color: #6c757d; /* Abu-abu lembut sebagai aksen utama */
            --accent-hover: #5a6268; /* Hover untuk aksen */
            --text-dark: #343a40; /* Teks gelap pekat */
            --text-muted: #adb5bd; /* Teks abu-abu untuk deskripsi */
            --border-light: #e0e0e0; /* Border ringan */
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); /* Shadow yang sangat lembut */

            /* Warna untuk status/notifikasi (disesuaikan agar tidak terlalu mencolok) */
            --status-pending: #ffc107; /* Kuning agak gelap */
            --status-success: #28a745; /* Hijau standar */
            --status-info: #17a2b8;   /* Biru toska standar */
            --status-danger: #dc3545;  /* Merah standar */
        }

        /* Reset dan Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif; /* Font utama */
            background-color: var(--primary-bg);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Struktur Layout */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            position: fixed; /* Membuat sidebar tetap di tempatnya */
            height: 100%;
            overflow-y: auto; /* Scroll jika konten sidebar banyak */
            transition: transform 0.3s ease-in-out;
            transform: translateX(0);
            z-index: 1000; /* Pastikan di atas konten lain */
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
            color: var(--accent-color); /* Logo juga abu-abu aksen */
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
            color: var(--text-dark); /* Teks lebih gelap saat aktif/hover */
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            margin-left: 250px; /* Sesuaikan dengan lebar sidebar */
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
            border: 1px solid var(--border-light); /* Tambah border lembut */
        }

        .top-bar h1 {
            font-size: 2em;
            font-weight: 700;
            color: var(--accent-color);
        }

        .menu-toggle {
            display: none; /* Sembunyikan di desktop */
            font-size: 1.5em;
            color: var(--accent-color);
            cursor: pointer;
            margin-right: 20px;
        }

        /* Statistik Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: var(--secondary-bg);
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-light); /* Tambah border lembut */
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .stat-card .icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--accent-color); /* Ikon juga pakai aksen abu-abu */
        }

        .stat-card h3 {
            font-size: 1.1em;
            color: var(--text-muted);
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Warna ikon spesifik untuk status */
        .stat-card.pending-booking .icon { color: var(--status-pending); }
        .stat-card.customer .icon { color: var(--status-success); }
        .stat-card.employee .icon { color: var(--status-info); }
        .stat-card.total-booking .icon { color: var(--accent-color); } /* Total booking pakai aksen utama */

        /* Quick Actions / Menu Kelola Data */
        .section-header {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--accent-color);
            margin-bottom: 25px;
            border-bottom: 2px solid var(--border-light);
            padding-bottom: 10px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .action-card {
            background-color: var(--secondary-bg);
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 150px;
            border: 1px solid var(--border-light); /* Tambah border lembut */
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .action-card .action-icon {
            font-size: 2.8em;
            color: var(--accent-color);
            margin-bottom: 15px;
        }

        .action-card a {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--text-dark);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .action-card a:hover {
            color: var(--accent-hover); /* Aksen hover */
        }

        /* Logout Button */
        .logout-section {
            padding: 30px 0;
            text-align: center;
        }

        .btn-logout {
            display: inline-block;
            background-color: var(--status-danger);
            color: #ffffff;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-logout:hover {
            background-color: #c82333; /* Darker red on hover */
            transform: translateY(-2px);
        }


        /* Responsif */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px; /* Lebar sidebar di mobile */
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0; /* Full width di mobile */
                padding: 20px;
            }

            .main-content.expanded {
                margin-left: 0;
            }

            .menu-toggle {
                display: block; /* Tampilkan toggle di mobile */
            }

            .top-bar {
                padding: 15px 20px;
            }

            .top-bar h1 {
                font-size: 1.8em;
            }

            .stats-grid, .action-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Kolom lebih kecil di tablet */
                gap: 20px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid, .action-grid {
                grid-template-columns: 1fr; /* Satu kolom di mobile */
                gap: 15px;
            }

            .stat-card, .action-card {
                padding: 20px;
            }

            .sidebar .logo {
                font-size: 1.6em;
            }

            .sidebar ul li a {
                font-size: 0.95em;
                padding: 10px 12px;
            }

            .top-bar h1 {
                font-size: 1.5em;
            }

            .menu-toggle {
                font-size: 1.3em;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="logo">Bengkel SAC Admin</div>
            <ul class="sidebar-menu">
                <li><a href="dashboard_admin.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="kelola_booking.php"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
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
                <h1>Dashboard Admin</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card pending-booking">
                    <div class="icon"><i class="fas fa-bell"></i></div>
                    <h3>Booking Pending</h3>
                    <div class="value"><?= $pending ?></div>
                </div>
                <div class="stat-card customer">
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <h3>Total Customer</h3>
                    <div class="value"><?= $total_user ?></div>
                </div>
                <div class="stat-card employee">
                    <div class="icon"><i class="fas fa-user-friends"></i></div>
                    <h3>Total Mekanik</h3>
                    <div class="value"><?= $total_pegawai ?></div>
                </div>
                <div class="stat-card total-booking">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Total Booking</h3>
                    <div class="value"><?= $total_booking ?></div>
                </div>
            </div>

            <h2 class="section-header">Menu Cepat</h2>
            <div class="action-grid">
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <a href="kelola_booking.php">Kelola Booking</a>
                </div>
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-file-invoice"></i></div>
                    <a href="kelola_tagihan.php">Kelola Tagihan</a>
                </div>
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-users"></i></div>
                    <a href="datacustomer.php">Data Customer</a>
                </div>
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-user-tie"></i></div>
                    <a href="kelola_user.php">Kelola User (Admin/Mekanik)</a>
                </div>
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-cogs"></i></div>
                    <a href="layanan.php">Kelola Layanan</a>
                </div>
                <div class="action-card">
                    <div class="action-icon"><i class="fas fa-clock"></i></div>
                    <a href="atur_jadwal_nonaktif.php">Kelola Jadwal</a>
                </div>
            </div>

        </main>
    </div>

    <script>
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
    </script>
</body>
</html>
<?php
$koneksi->close();
?>