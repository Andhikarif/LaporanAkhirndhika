<?php
session_start();
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Inisialisasi variabel pencarian
$search_query = '';
$search_param = '';

// Cek apakah ada parameter pencarian
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    // Tambahkan wildcard untuk pencarian LIKE
    $search_param = '%' . $search_query . '%';
    $sql = "SELECT id, nama, email, telepon FROM users WHERE role = 'customer' AND nama LIKE ? ORDER BY nama ASC";
} else {
    $sql = "SELECT id, nama, email, telepon FROM users WHERE role = 'customer' ORDER BY nama ASC";
}

// Mengambil data customer menggunakan prepared statement
$stmt = $koneksi->prepare($sql);

if ($search_param) {
    $stmt->bind_param("s", $search_param);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Customer - Bengkel XYZ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna, sama dengan dashboard_admin.php */
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
            font-family: 'Roboto', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Struktur Layout Mirip Dashboard */
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

        /* Search Form Styling */
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
            border-color: var(--accent-color); /* Fokus pakai warna aksen */
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
            background-color: var(--accent-color); /* Header tabel pakai warna aksen */
            color: #ffffff;
            font-weight: 600;
            text-transform: uppercase;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8f9fa; /* Warna selang-seling */
        }

        .data-table tbody tr:hover {
            background-color: #e9ecef;
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
        }

        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }
            /* Sembunyikan kolom tertentu di layar kecil jika terlalu sempit */
            .data-table th:nth-child(1),
            .data-table td:nth-child(1) {
                display: none; /* Sembunyikan kolom No. */
            }
        }
        @media (max-width: 576px) {
             .data-table th:nth-child(4), /* Kolom Telepon */
             .data-table td:nth-child(4) {
                 display: none; /* Sembunyikan kolom Telepon di layar sangat kecil */
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
                <li><a href="datacustomer.php" class="active"><i class="fas fa-users"></i> Data Customer</a></li>
                <li><a href="kelola_user.php"><i class="fas fa-user-tie"></i> Kelola User</a></li>
                <li><a href="layanan.php"><i class="fas fa-cogs"></i> Kelola Layanan</a></li>
                <li><a href="atur_jadwal_nonaktif.php"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Data Customer</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
                <h2>Daftar Pelanggan</h2>

                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Cari nama customer..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit"><i class="fas fa-search"></i> Cari</button>
                </form>

                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>{$no}</td>";
                                    echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['telepon']) . "</td>";
                                    echo "</tr>";
                                    $no++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-light-muted);">
                        <?php if ($search_query): ?>
                            Tidak ditemukan customer dengan nama "<?= htmlspecialchars($search_query) ?>".
                        <?php else: ?>
                            Belum ada data customer.
                        <?php endif; ?>
                    </p>
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
    </script>
</body>
</html>
<?php
$stmt->close();
$koneksi->close();
?>