<?php
session_start(); // Pastikan session dimulai di awal

include 'koneksi.php';

// Cek akses admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Inisialisasi pesan
$message_type = '';
$message_text = '';

// Handle hapus user
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    // Gunakan prepared statement untuk keamanan
    $stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User berhasil dihapus.";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus user: " . $stmt->error;
    }
    $stmt->close();
    header("Location: kelola_user.php");
    exit;
}

// Handle simpan (tambah & edit)
if (isset($_POST['simpan'])) {
    $id       = $_POST['id'];
    $nama     = $koneksi->real_escape_string($_POST['nama']);
    $email    = $koneksi->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Password akan di-hash nanti
    $role     = $_POST['role'];

    if ($id == '') {
        // Tambah user baru, password wajib diisi
        if (empty($password)) {
            $_SESSION['error_message'] = "Password wajib diisi untuk user baru.";
            header("Location: kelola_user.php");
            exit;
        }
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama, $email, $password_hash, $role);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User baru berhasil ditambahkan.";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan user: " . $stmt->error;
        }
        $stmt->close();
    } else {
        // Edit user, update password jika diisi
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("UPDATE users SET nama=?, email=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("ssssi", $nama, $email, $password_hash, $role, $id);
        } else {
            $stmt = $koneksi->prepare("UPDATE users SET nama=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $email, $role, $id);
        }
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data user berhasil diperbarui.";
        } else {
            $_SESSION['error_message'] = "Gagal memperbarui user: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: kelola_user.php");
    exit;
}

// Ambil semua admin & pegawai
$data_user = $koneksi->query("SELECT * FROM users WHERE role IN ('admin', 'pegawai') ORDER BY role DESC, nama ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
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
            --btn-success: #28a745;
            --btn-success-hover: #218838;
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

        /* Sidebar */
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

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-right: 8px; /* Jarak antar tombol */
        }
        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--btn-primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: var(--btn-primary-hover);
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--btn-success);
            color: #ffffff;
        }
        .btn-success:hover {
            background-color: var(--btn-success-hover);
            transform: translateY(-1px);
        }

        .btn-info {
            background-color: var(--btn-info);
            color: #ffffff;
        }
        .btn-info:hover {
            background-color: var(--btn-info-hover);
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--btn-danger);
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: var(--btn-danger-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--btn-secondary);
            color: #ffffff;
        }
        .btn-secondary:hover {
            background-color: var(--btn-secondary-hover);
            transform: translateY(-1px);
        }
        
        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px; /* Jarak antar tombol aksi */
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

        /* Modal (Pop-up Form) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--secondary-bg);
            margin: auto;
            padding: 30px;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--accent-color);
            font-size: 1.5em;
        }

        .close-button {
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--text-dark);
            text-decoration: none;
        }

        .modal-body label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-body input[type="text"],
        .modal-body input[type="email"],
        .modal-body input[type="password"],
        .modal-body select {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-light);
            border-radius: 5px;
            font-size: 1em;
            color: var(--text-dark);
            transition: border-color 0.3s ease;
        }

        .modal-body input[type="text"]:focus,
        .modal-body input[type="email"]:focus,
        .modal-body input[type="password"]:focus,
        .modal-body select:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .modal-footer {
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
            .modal-content {
                width: 95%;
            }
        }

        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.9em;
            }
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            .btn {
                width: 100%;
                justify-content: center;
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
                <li><a href="kelola_user.php" class="active"><i class="fas fa-user-tie"></i> Kelola User</a></li>
                <li><a href="layanan.php"><i class="fas fa-cogs"></i> Kelola Layanan</a></li>
                <li><a href="atur_jadwal_nonaktif.php"><i class="fas fa-clock"></i> Kelola Jadwal</a></li>
                <li style="margin-top: 30px;"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="top-bar">
                <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
                <h1>Kelola User (Admin & Mekanik)</h1>
                <div class="user-info">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user']['nama']) ?></strong>
                </div>
            </div>

            <div class="content-area">
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

                <div class="action-buttons">
                    <button class="btn btn-primary" id="tambahUserBtn"><i class="fas fa-plus-circle"></i> Tambah User Baru</button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Dibuat Pada</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($data_user->num_rows > 0): ?>
                                <?php
                                $no = 1;
                                while ($row = $data_user->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$no}</td>
                                        <td>" . htmlspecialchars($row['nama']) . "</td>
                                        <td>" . htmlspecialchars($row['email']) . "</td>
                                        <td>" . htmlspecialchars($row['role']) . "</td>
                                        <td>" . htmlspecialchars($row['created_at']) . "</td>
                                        <td>
                                            <button class='btn btn-info btn-sm edit-btn' data-id='{$row['id']}' data-nama='" . htmlspecialchars($row['nama']) . "' data-email='" . htmlspecialchars($row['email']) . "' data-role='{$row['role']}'><i class='fas fa-edit'></i> Edit</button>
                                            <a href='kelola_user.php?hapus={$row['id']}' class='btn btn-danger btn-sm' onclick=\"return confirm('Apakah Anda yakin ingin menghapus user ini?')\"><i class='fas fa-trash-alt'></i> Hapus</a>
                                        </td>
                                    </tr>";
                                    $no++;
                                }
                                ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">Belum ada data user.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard Admin
                </a>
            </div>
        </main>
    </div>

    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah User Baru</h3>
                <span class="close-button">&times;</span>
            </div>
            <form id="userForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="id" id="user_id">
                    
                    <label for="nama">Nama Lengkap:</label>
                    <input type="text" name="nama" id="nama" required>

                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required>

                    <label for="password">Password: <span id="password_hint"></span></label>
                    <input type="password" name="password" id="password" autocomplete="new-password">

                    <label for="role">Role:</label>
                    <select name="role" id="role" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="admin">Admin</option>
                        <option value="pegawai">Pegawai</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="simpan" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary close-button"><i class="fas fa-times"></i> Batal</button>
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

        // Inisialisasi posisi sidebar saat halaman dimuat
        if (window.innerWidth <= 992) {
            sidebar.classList.add('hidden');
            mainContent.style.marginLeft = '0';
        }

        // JavaScript untuk Modal Form
        const userModal = document.getElementById('userModal');
        const tambahUserBtn = document.getElementById('tambahUserBtn');
        const closeButtons = document.querySelectorAll('.close-button');
        const userForm = document.getElementById('userForm');
        const modalTitle = document.getElementById('modalTitle');
        const userId = document.getElementById('user_id');
        const nama = document.getElementById('nama');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const passwordHint = document.getElementById('password_hint');
        const role = document.getElementById('role');
        const editButtons = document.querySelectorAll('.edit-btn');

        // Buka modal untuk tambah user
        tambahUserBtn.addEventListener('click', () => {
            modalTitle.textContent = 'Tambah User Baru';
            userForm.reset(); // Reset form
            userId.value = ''; // Kosongkan ID
            password.required = true; // Password wajib diisi untuk tambah
            passwordHint.textContent = ''; // Hapus hint password
            userModal.style.display = 'flex';
        });

        // Buka modal untuk edit user
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                modalTitle.textContent = 'Edit User';
                userId.value = button.dataset.id;
                nama.value = button.dataset.nama;
                email.value = button.dataset.email;
                role.value = button.dataset.role;
                password.value = ''; // Kosongkan password saat edit
                password.required = false; // Password tidak wajib diisi saat edit
                passwordHint.textContent = '(kosongkan jika tidak ingin ganti)';
                userModal.style.display = 'flex';
            });
        });

        // Tutup modal
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                userModal.style.display = 'none';
            });
        });

        // Tutup modal jika klik di luar area modal content
        window.addEventListener('click', (event) => {
            if (event.target == userModal) {
                userModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
<?php
$koneksi->close(); // Tutup koneksi database
?>