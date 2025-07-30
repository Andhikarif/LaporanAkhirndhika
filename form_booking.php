<?php
session_start();
include 'koneksi.php';

// Akses hanya untuk customer
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

// Ambil data layanan
$layanan = $koneksi->query("SELECT id, nama_layanan, harga FROM layanan ORDER BY nama_layanan ASC");

// Ambil tanggal jika sudah dipilih, default hari ini
$tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');

// Daftar jam kerja bengkel
$jam_kerja = ["08:00", "09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00"];

// Ambil jam yang dinonaktifkan (format HH:MM)
$jam_nonaktif = [];
$stmt_nonaktif = $koneksi->prepare("SELECT jam FROM jadwal_nonaktif WHERE tanggal=?");
$stmt_nonaktif->bind_param("s", $tanggal);
$stmt_nonaktif->execute();
$result_nonaktif = $stmt_nonaktif->get_result();
while ($row = $result_nonaktif->fetch_assoc()) {
    $jam_nonaktif[] = substr($row['jam'], 0, 5);
}
$stmt_nonaktif->close();

// Proses booking
if (isset($_POST['booking'])) {
    $id_user    = $_SESSION['user']['id'];
    $tanggal    = $_POST['tanggal'];
    $jam        = $_POST['jam'];
    
    // Jenis booking otomatis 'layanan'
    $id_layanan = isset($_POST['id_layanan']) ? (int)$_POST['id_layanan'] : null;

    // Ambil detail mobil dari POST
    $merk_mobil = $_POST['merk_mobil'];
    $tahun_mobil = $_POST['tahun_mobil'];
    $transmisi = $_POST['transmisi'];
    $warna_mobil = $_POST['warna_mobil'];

    // Validasi: jam tidak boleh termasuk nonaktif
    if (in_array($jam, $jam_nonaktif)) {
        echo "<script>alert('Slot pada jam ini sudah dinonaktifkan oleh admin!'); window.location='form_booking.php';</script>";
        exit;
    }

    // Validasi: jam belum dibooking user lain
    $stmt_cek_booking = $koneksi->prepare("SELECT COUNT(*) AS total FROM booking
                                           WHERE tanggal=? AND jam=? AND status IN ('pending', 'diterima', 'dikerjakan')");
    $stmt_cek_booking->bind_param("ss", $tanggal, $jam);
    $stmt_cek_booking->execute();
    $data_cek = $stmt_cek_booking->get_result()->fetch_assoc();
    $stmt_cek_booking->close();

    if ($data_cek['total'] > 0) {
        echo "<script>alert('Slot pada jam ini sudah penuh!'); window.location='form_booking.php';</script>";
        exit;
    }

    // Simpan booking
    $stmt_insert_booking = $koneksi->prepare("INSERT INTO booking (id_user, merk_mobil, tahun_mobil, transmisi, warna_mobil, id_layanan, tanggal, jam, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt_insert_booking->bind_param("issssiss", $id_user, $merk_mobil, $tahun_mobil, $transmisi, $warna_mobil, $id_layanan, $tanggal, $jam);
    $stmt_insert_booking->execute();
    $stmt_insert_booking->close();

    echo "<script>alert('Booking berhasil! Silakan tunggu konfirmasi dari admin.'); window.location='riwayat_booking.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Booking Servis - Bengkel XYZ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            --dark-charcoal: #212121; /* Hitam tidak pekat */
            --light-grey: #f8f8f8;
            --white: #ffffff;
            --mid-grey: #6c757d;
            --accent-blue: #007bff; /* Warna aksen untuk tombol */
            --hover-blue: #0056b3;
            --border-color: #ddd;
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

        .container-custom {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        h2 {
            text-align: center;
            color: var(--dark-charcoal);
            margin-bottom: 30px;
            font-weight: 700;
        }

        /* Custom Button */
        .btn-custom-primary {
            background-color: var(--accent-blue);
            color: var(--white);
            padding: 12px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            border: none;
            width: auto;
        }

        .btn-custom-primary:hover {
            background-color: var(--hover-blue);
            transform: translateY(-2px);
            color: var(--white); /* Ensure text color remains white on hover */
        }

        .link-back {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: var(--accent-blue);
            font-weight: 600;
            transition: color 0.3s ease;
            text-decoration: none; /* Remove underline */
        }

        .link-back:hover {
            color: var(--hover-blue);
            text-decoration: underline;
        }

        /* Styling for disabled select options (jam) */
        select option[disabled] {
            color: #ccc;
            font-style: italic;
        }

        /* Responsive for mobile */
        @media (max-width: 767.98px) {
            .container-custom {
                margin: 20px auto;
                padding: 20px;
            }
            h2 {
                font-size: 1.8em;
            }
            .btn-custom-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="container container-custom">
        <h2>Form Booking Servis Kendaraan</h2>

        <form method="POST" id="dateForm">
            <div class="mb-3">
                <label for="tanggal" class="form-label">Tanggal Booking:</label>
                <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>" required onchange="document.getElementById('dateForm').submit()">
                <noscript><button type="submit" class="btn btn-primary mt-2">Pilih Tanggal</button></noscript>
            </div>
        </form>

        <form method="POST" id="bookingForm">
            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
            
            <div class="row gx-3">
                <div class="col-md-12"> 
                    <div class="mb-3">
                        <label for="id_layanan" class="form-label">Pilih Layanan:</label>
                        <select name="id_layanan" id="id_layanan" class="form-select" required>
                            <option value="">-- Pilih Layanan --</option>
                            <?php
                            $layanan->data_seek(0);
                            while ($row = $layanan->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>{$row['nama_layanan']} - Rp" . number_format($row['harga'], 0, ',', '.') . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <hr class="my-4"> <h4>Detail Mobil Anda</h4>
            <div class="row gx-3">
                <div class="col-md-6 mb-3">
                    <label for="merk_mobil" class="form-label">Merk Mobil:</label>
                    <input type="text" class="form-control" id="merk_mobil" name="merk_mobil" placeholder="Contoh: Toyota Yaris" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tahun_mobil" class="form-label">Tahun Mobil:</label>
                    <input type="number" class="form-control" id="tahun_mobil" name="tahun_mobil" placeholder="Contoh: 2020" min="1900" max="<?= date('Y') + 1 ?>" required>
                </div>
            </div>

            <div class="row gx-3">
                <div class="col-md-6 mb-3">
                    <label for="transmisi" class="form-label">Transmisi:</label>
                    <select class="form-select" id="transmisi" name="transmisi" required>
                        <option value="">-- Pilih Transmisi --</option>
                        <option value="manual">Manual</option>
                        <option value="otomatis">Otomatis</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="warna_mobil" class="form-label">Warna Mobil:</label>
                    <input type="text" class="form-control" id="warna_mobil" name="warna_mobil" placeholder="Contoh: Hitam" required>
                </div>
            </div>

            <hr class="my-4"> <div class="mb-4">
                <label for="jam" class="form-label">Jam Booking (tersedia):</label>
                <select name="jam" id="jam" class="form-select" required>
                    <option value="">-- Pilih Jam --</option>
                    <?php
                    $jam_tersedia_found = false;
                    $selected_jam_from_post = isset($_POST['jam']) ? $_POST['jam'] : '';
                    foreach ($jam_kerja as $jam_option) {
                        $is_disabled = in_array($jam_option, $jam_nonaktif);

                        $jam_sql = $koneksi->real_escape_string($jam_option);
                        $stmt_cek_slot = $koneksi->prepare("SELECT COUNT(*) AS total FROM booking
                                                               WHERE tanggal=? AND jam=? AND status IN ('pending', 'diterima', 'dikerjakan')");
                        $stmt_cek_slot->bind_param("ss", $tanggal, $jam_sql);
                        $stmt_cek_slot->execute();
                        $data_slot = $stmt_cek_slot->get_result()->fetch_assoc();
                        $stmt_cek_slot->close();

                        $is_booked = $data_slot['total'] > 0;

                        $disabled_attr = '';
                        $display_text = $jam_option;

                        if ($is_disabled) {
                            $disabled_attr = 'disabled';
                            $display_text .= " (Nonaktif)";
                        } elseif ($is_booked) {
                            $disabled_attr = 'disabled';
                            $display_text .= " (Penuh)";
                        } else {
                            $jam_tersedia_found = true;
                        }

                        $selected_attr = ($selected_jam_from_post === $jam_option && !$is_disabled && !$is_booked) ? 'selected' : '';

                        echo "<option value='{$jam_option}' {$disabled_attr} {$selected_attr}>{$display_text}</option>";
                    }

                    if (!$jam_tersedia_found) {
                        echo "<option disabled>Tidak ada slot tersedia untuk tanggal ini.</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit" name="booking" class="btn btn-custom-primary w-100">Booking Sekarang</button>
        </form>

        <a href="dashboard_customer.php" class="link-back">← Kembali ke Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>