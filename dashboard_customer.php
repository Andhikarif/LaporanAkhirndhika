<?php
session_start();
include 'koneksi.php';

// Akses hanya untuk customer
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

$nama = $_SESSION['user']['nama'];

// Ambil data layanan dari database
$layanan_query = $koneksi->query("SELECT nama_layanan, harga, deskripsi FROM layanan ORDER BY nama_layanan ASC");
$layanan_data = [];
while ($row = $layanan_query->fetch_assoc()) {
    $layanan_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Customer - Bengkel Semarang Auto Car
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            --dark-charcoal: #212121;
            /* Hitam tidak pekat */
            --light-grey: #f8f8f8;
            --white: #ffffff;
            --mid-grey: #6c757d;
            --accent-blue: #007bff;
            /* Warna aksen untuk tombol */
            --hover-blue: #0056b3;
            --whatsapp-green: #25D366;
            --instagram-purple: #C13584;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-grey);
            color: var(--dark-charcoal);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Override Bootstrap Navbar */
        .navbar-dark-custom {
            background-color: var(--dark-charcoal);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-dark-custom .navbar-brand,
        .navbar-dark-custom .nav-link {
            color: var(--white) !important;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .navbar-dark-custom .nav-link:hover {
            color: var(--mid-grey) !important;
        }

        .navbar-dark-custom .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-dark-custom .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('bg.jpg') no-repeat center center;
            /* Ganti URL gambar */
            background-size: cover;
            color: var(--white);
            padding: 100px 0;
            text-align: center;
            border-bottom: 3px solid var(--mid-grey);
        }

        .hero-section h1 {
            font-size: 3em;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .hero-section p {
            font-size: 1.2em;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            color: #d0d0d0;
        }

        /* Custom Button */
        .btn-custom-primary {
            background-color: var(--accent-blue);
            color: var(--white);
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            border: none;
        }

        .btn-custom-primary:hover {
            background-color: var(--hover-blue);
            transform: translateY(-2px);
            color: var(--white);
            /* Ensure text color remains white on hover */
        }

        /* Content Sections */
        .content-section {
            padding: 80px 0;
            background-color: var(--white);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .content-section:nth-of-type(even) {
            background-color: var(--light-grey);
            /* Warna latar selang-seling */
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark-charcoal);
        }

        .section-header p {
            font-size: 1.1em;
            color: var(--mid-grey);
            max-width: 800px;
            margin: 0 auto;
        }

        /* Card Customization */
        .card-custom {
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease;
            background-color: var(--white);
            /* Ensure card background matches design */
        }

        .card-custom:hover {
            transform: translateY(-7px);
        }

        .card-header-custom {
            background-color: var(--dark-charcoal);
            color: var(--white);
            padding: 15px 20px;
            font-size: 1.3em;
            font-weight: 600;
            border-bottom: none;
            /* Remove default Bootstrap border */
        }

        .card-body-custom p {
            color: var(--mid-grey);
            font-size: 0.95em;
        }

        /* Service Items */
        .service-item-custom {
            background-color: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .service-item-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .service-item-custom h5 {
            font-size: 1.4em;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-charcoal);
        }

        .service-item-custom .price {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--accent-blue);
            margin-bottom: 10px;
        }

        .service-item-custom p {
            font-size: 0.95em;
            color: var(--mid-grey);
        }

        /* Footer */
        .footer {
            background-color: var(--dark-charcoal);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
            margin-top: auto;
            /* Push footer to the bottom */
        }

        .footer p {
            margin-bottom: 10px;
            /* Spacing for address/contact */
        }

        .footer-links a {
            color: var(--white);
            margin: 0 15px;
            transition: color 0.3s ease;
            text-decoration: none;
            /* Remove underline */
        }

        .footer-links a:hover {
            color: var(--mid-grey);
        }

        .social-icons a {
            color: var(--white);
            font-size: 1.5em;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .social-icons a.whatsapp:hover {
            color: var(--whatsapp-green);
        }

        .social-icons a.instagram:hover {
            color: var(--instagram-purple);
        }

        /* Responsive adjustments for Bootstrap */
        @media (max-width: 767.98px) {
            .hero-section h1 {
                font-size: 2.5em;
            }

            .hero-section p {
                font-size: 1em;
            }

            .section-header h2 {
                font-size: 2em;
            }
        }
        
        /* Tambahan CSS untuk bagian "Konten Tambahan" agar lebih responsif */
        .additional-content {
            margin: 40px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            /* height: 80vh; */ /* Dihapus atau disesuaikan jika ingin konten lebih fleksibel */
            box-sizing: border-box;
        }

        .additional-content .text-column {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
        }

        .additional-content .text-column h1 {
            margin-top: 0;
            font-size: 3em; /* Ukuran font disesuaikan untuk responsif */
            font-weight: 730;
            line-height: 1.2;
        }

        .additional-content .text-column p {
            font-size: 1em;
            line-height: 1.5;
            color:#31363F;
            margin-top: 25px;
            margin-right: 20px; /* Disesuaikan untuk responsif */
        }

        .additional-content .text-column .btn-book-now {
            display: inline-block;
            padding: 18px 55px;
            color: black;
            background-color: transparent;
            text-decoration: none;
            border: 2px solid black;
            border-radius: 45px;
            font-size: 1em;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .additional-content .text-column .btn-book-now:hover {
            background-color: var(--dark-charcoal);
            color: var(--white);
            border-color: var(--dark-charcoal);
        }

        .additional-content .image-column {
            flex: 1;
            display: flex; /* Menggunakan flexbox untuk memusatkan gambar */
            justify-content: center;
            align-items: center;
        }

        .additional-content .image-column img {
            max-width: 100%; /* Pastikan gambar tidak melebihi lebar kontainernya */
            height: auto;
            border-radius: 16px;
        }

        @media (max-width: 991.98px) {
            .additional-content {
                flex-direction: column; /* Ubah tata letak menjadi kolom pada layar kecil */
                margin: 20px; /* Kurangi margin pada layar kecil */
            }

            .additional-content .text-column {
                padding: 15px; /* Kurangi padding pada kolom teks */
            }

            .additional-content .text-column h1 {
                font-size: 2.2em; /* Sesuaikan ukuran font untuk layar tablet */
                text-align: center;
            }

            .additional-content .text-column p {
                margin-right: 0; /* Hapus margin kanan pada layar kecil */
                text-align: center;
            }

            .additional-content .text-column .btn-book-now {
                width: 100%; /* Tombol memenuhi lebar pada layar kecil */
                text-align: center;
                box-sizing: border-box;
            }
        }

        @media (max-width: 575.98px) {
            .additional-content {
                margin: 15px; /* Margin lebih kecil lagi untuk ponsel */
            }

            .additional-content .text-column h1 {
                font-size: 1.8em; /* Ukuran font lebih kecil untuk ponsel */
            }

            .additional-content .text-column p {
                font-size: 0.9em; /* Ukuran font lebih kecil untuk ponsel */
            }

            .additional-content .text-column .btn-book-now {
                padding: 15px 30px; /* Padding tombol lebih kecil untuk ponsel */
                font-size: 0.9em;
            }
        }

    </style>
</head>

<body>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark-custom">
            <div class="container">
                <a class="navbar-brand logo" href="#">Bengkel SAC</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="#home">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about">Tentang Kami</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services">Layanan Service</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="form_booking.php">Booking Service</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="riwayat_booking.php">Riwayat Booking</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="hero-section" id="home">
        <div class="container">
            <h1>Selamat Datang, <?= htmlspecialchars($nama) ?>!</h1>
            <p>Kami adalah solusi terbaik untuk perawatan kendaraan Anda. Percayakan kenyamanan dan keamanan perjalanan Anda kepada kami.</p>
            <a href="form_booking.php" class="btn btn-custom-primary">Booking Servis Sekarang</a>
        </div>
    </section>
    
    <section class="additional-content">
        <div class="text-column">
            <h1>Jaga Performa Mobil Anda <br>dengan Servis Rutin Terbaik!</h1>
            <p>Bengkel SAC berkomitmen untuk memberikan perawatan terbaik bagi kendaraan Anda. Dengan teknisi ahli dan suku cadang berkualitas, kami memastikan mobil Anda selalu dalam kondisi prima. Jadwalkan servis Anda sekarang untuk pengalaman berkendara yang aman dan nyaman!</p>
        </div>
        <div class="image-column">
            <img src="bg.jpg" alt="Gambar Motor">
        </div>
    </section>

    <section class="content-section" id="about">
        <div class="container">
            <div class="section-header">
                <h2>Mengapa Memilih Bengkel Kami?</h2>
                <p>Kami berkomitmen untuk memberikan layanan terbaik dengan integritas, teknisi ahli, dan suku cadang terjamin.</p>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <div class="col">
                    <div class="card h-100 card-custom">
                        <div class="card-header card-header-custom">
                            Teknisi Profesional
                        </div>
                        <div class="card-body card-body-custom">
                            <p class="card-text">Didukung tim teknisi yang telah melewati pelatihan ketat dan memiliki pengalaman bertahun-tahun dalam menangani berbagai jenis kendaraan.</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100 card-custom">
                        <div class="card-header card-header-custom">
                            Suku Cadang Berkualitas
                        </div>
                        <div class="card-body card-body-custom">
                            <p class="card-text">Kami hanya menggunakan suku cadang asli atau setara dengan standar OEM untuk memastikan performa optimal dan ketahanan jangka panjang kendaraan Anda.</p>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card h-100 card-custom">
                        <div class="card-header card-header-custom">
                            Transparansi Harga
                        </div>
                        <div class="card-body card-body-custom">
                            <p class="card-text">Nikmati layanan berkualitas dengan harga yang jujur dan transparan. Tidak ada biaya tersembunyi, Anda tahu persis apa yang Anda bayar.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="content-section" id="services">
        <div class="container">
            <div class="section-header">
                <h2>Layanan Servis Kami</h2>
                <p>Beragam pilihan servis untuk menjaga kendaraan Anda tetap dalam kondisi prima dan siap untuk perjalanan jauh.</p>
            </div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php if (empty($layanan_data)): ?>
                    <div class="col-12 text-center text-muted">
                        <p>Belum ada layanan yang tersedia saat ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($layanan_data as $layanan): ?>
                        <div class="col">
                            <div class="service-item-custom">
                                <h5><?= htmlspecialchars($layanan['nama_layanan']) ?></h5>
                                <p class="price">Rp<?= number_format($layanan['harga'], 0, ',', '.') ?></p>
                                <p><?= nl2br(htmlspecialchars($layanan['deskripsi'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="text-center mt-5">
                <a href="form_booking.php" class="btn btn-custom-primary">Booking Servis Sekarang</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>Jalan Much. Prabu Mangkunegara.83 (Belakang Wr. Tugu Muda Bukit Sangkal, Suka Maju, Kec. Sako, Kota Palembang, Sumatera Selatan) 30961</p>
            <p>&copy; <?= date('Y') ?> Bengkel SAC. Semua Hak Dilindungi Undang-Undang.</p>
            <p class="social-icons">
                <a href="https://wa.me/082176598324" target="_blank" class="whatsapp" title="Hubungi kami via WhatsApp" style="text-decoration: none;"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                <a href="https://www.instagram.com/bengkel_sac_palembang?igsh=czYzaHBrMjVqN25h/nama_instagram_anda" target="_blank" class="instagram" title="Ikuti kami di Instagram" style="text-decoration: none;"><i class="fab fa-instagram"></i> Instagram</a>
            </p>
            <p class="footer-links">
                <a href="#">Kebijakan Privasi</a> | <a href="#">Syarat & Ketentuan</a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>