<?php
session_start();
include 'koneksi.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $koneksi->prepare("SELECT id, nama, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;

            if ($user['role'] == 'customer') {
                header("Location: dashboard_customer.php");
            } elseif ($user['role'] == 'admin') {
                header("Location: dashboard_admin.php");
            } elseif ($user['role'] == 'pegawai') {
                header("Location: dashboard_pegawai.php");
            }
            exit;
        } else {
            $error_message = 'Password salah. Silakan coba lagi.';
        }
    } else {
        $error_message = 'Email tidak terdaftar. Silakan periksa kembali atau daftar akun baru.';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bengkel XYZ</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            --dark-primary: #1a1a1a;
            --dark-secondary: #2c2c2c;
            --text-light: #e0e0e0;
            --text-muted: #a0a0a0;
            --accent-color: #8c8c8c;
            --accent-hover: #a6a6a6;
            --border-dark: #444444;
            --input-bg: #3a3a3a;
            --error-red: #dc3545;
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
            color: var(--text-light); /* Teks default terang */
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background-color: var(--dark-secondary); /* Latar belakang form agak terang dari primary */
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4); /* Bayangan lebih kuat untuk kesan mendalam */
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border-dark); /* Border gelap */
        }

        .login-container h2 {
            color: var(--accent-color); /* Warna judul sesuai aksen */
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 2.2em;
        }

        .logo-bengkel {
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 700;
            color: var(--accent-color); /* Warna logo sesuai aksen */
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-light); /* Label teks terang */
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-dark);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 1em;
            color: var(--text-light); /* Teks input terang */
            background-color: var(--input-bg); /* Latar belakang input gelap */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(140, 140, 140, 0.25); /* Focus ring dengan warna aksen */
            outline: none;
        }

        input::placeholder {
            color: var(--text-muted); /* Warna placeholder */
        }

        .btn-login {
            display: block;
            background-color: var(--accent-color);
            color: var(--dark-primary); /* Teks gelap di tombol aksen */
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            border: none;
            width: 100%;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }

        .register-link {
            display: block;
            margin-top: 25px;
            color: var(--text-muted);
            font-size: 0.95em;
        }

        .register-link a {
            color: var(--accent-color);
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        .error-message {
            color: var(--error-red);
            font-size: 0.9em;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
            background-color: rgba(220, 53, 69, 0.1); /* Latar belakang error yang lembut */
            padding: 10px;
            border-radius: 5px;
        }

        /* Responsif untuk mobile */
        @media (max-width: 500px) {
            .login-container {
                padding: 30px 20px;
                margin: 20px auto;
            }
            .login-container h2 {
                font-size: 1.8em;
            }
            .logo-bengkel {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo-bengkel">
            Bengkel SAC
        </div>
        <h2>Selamat Datang Kembali!</h2>

        <?php if ($error_message): ?>
            <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="register-link">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    </div>

</body>
</html>
<?php
$koneksi->close();
?>