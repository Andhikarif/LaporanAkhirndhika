<?php
include 'koneksi.php'; // Pastikan file koneksi.php sudah benar

$error_message = '';
$success_message = '';

// Inisialisasi variabel untuk mempertahankan input
$nama = $_POST['nama'] ?? '';
$email = $_POST['email'] ?? '';
$telepon = $_POST['telepon'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $telepon = trim($_POST['telepon']); // Ambil nomor telepon
    $password = $_POST['password'];

    // Validasi input
    if (empty($nama) || empty($email) || empty($telepon) || empty($password)) {
        $error_message = 'Semua kolom harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid.';
    } elseif (!preg_match("/^[0-9]{10,15}$/", $telepon)) { // Validasi nomor telepon: 10-15 digit angka
        $error_message = 'Nomor telepon tidak valid. Masukkan hanya angka (min 10, max 15 digit).';
    } elseif (strlen($password) < 6) { // Contoh: password minimal 6 karakter
        $error_message = 'Password minimal 6 karakter.';
    } else {
        // Cek apakah email sudah digunakan menggunakan prepared statement
        $stmt_check = $koneksi->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = 'Email sudah terdaftar. Silakan gunakan email lain atau login.';
        } else {
            // Hash password sebelum disimpan
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Masukkan data user baru termasuk nomor telepon menggunakan prepared statement
            $stmt_insert = $koneksi->prepare("INSERT INTO users (nama, email, telepon, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $stmt_insert->bind_param("ssss", $nama, $email, $telepon, $hashed_password); // Tambahkan 's' untuk telepon

            if ($stmt_insert->execute()) {
                $success_message = 'Registrasi berhasil! Anda akan diarahkan ke halaman login.';
                // Redirect setelah beberapa detik untuk memberi waktu user membaca pesan sukses
                header("Refresh: 3; URL=login.php");
                exit;
            } else {
                $error_message = 'Terjadi kesalahan saat pendaftaran. Silakan coba lagi.';
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Bengkel XYZ</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            --dark-primary: #1a1a1a;
            --dark-secondary: #2c2c2c;
            --text-light: #e0e0e0;
            --text-muted: #a0a0a0;
            --accent-color: #8c8c8c; /* Abu-abu yang elegan sebagai aksen */
            --accent-hover: #a6a6a6;
            --border-dark: #444444;
            --input-bg: #3a3a3a;
            --error-red: #dc3545;
            --success-green: #28a745;
        }

        /* Reset dan Dasar */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-primary);
            color: var(--text-light);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .register-container {
            background-color: var(--dark-secondary);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border-dark);
        }

        .register-container h2 {
            color: var(--accent-color);
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 2.2em;
        }

        .logo-bengkel {
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 700;
            color: var(--accent-color);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-light);
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"], /* Tipe input untuk telepon */
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-dark);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            color: var(--text-light);
            background-color: var(--input-bg);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus, /* Focus state untuk telepon */
        input[type="password"]:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(140, 140, 140, 0.25);
            outline: none;
        }

        input::placeholder {
            color: var(--text-muted);
        }

        .btn-register {
            display: block;
            background-color: var(--accent-color);
            color: var(--dark-primary);
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            border: none;
            width: 100%;
            margin-top: 10px;
        }

        .btn-register:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }

        .login-link {
            display: block;
            margin-top: 25px;
            color: var(--text-muted);
            font-size: 0.95em;
        }

        .login-link a {
            color: var(--accent-color);
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        .message-box {
            font-size: 0.9em;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
        }

        .error-message {
            color: var(--error-red);
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--error-red);
        }

        .success-message {
            color: var(--success-green);
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-green);
        }

        /* Responsif untuk mobile */
        @media (max-width: 500px) {
            .register-container {
                padding: 30px 20px;
                margin: 20px auto;
            }
            .register-container h2 {
                font-size: 1.8em;
            }
            .logo-bengkel {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="logo-bengkel">
            Bengkel SAC
        </div>
        <h2>Daftar Akun Baru</h2>

        <?php if ($error_message): ?>
            <p class="message-box error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <p class="message-box success-message"><?= htmlspecialchars($success_message) ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nama">Nama Lengkap:</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($nama) ?>" required autocomplete="name">
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="telepon">Nomor Telepon:</label>
                <input type="tel" id="telepon" name="telepon" value="<?= htmlspecialchars($telepon) ?>" required autocomplete="tel" placeholder="Contoh: 081234567890">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-register">Daftar</button>
        </form>

        <p class="login-link">Sudah punya akun? <a href="login.php">Login di sini</a></p>
    </div>

</body>
</html>
<?php
$koneksi->close(); // Tutup koneksi database setelah selesai
?>