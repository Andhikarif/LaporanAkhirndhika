<?php
session_start();
include 'koneksi.php';

// Akses hanya pegawai
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pegawai') {
    header("Location: login.php");
    exit;
}

// Proses update status (dikerjakan/selesai)
if (isset($_POST['update_status'])) {
    $id_booking = $_POST['id_booking'];
    $status     = $_POST['status'];

    // Cek dulu apakah booking sudah diterima
    $cek = $koneksi->query("SELECT status FROM booking WHERE id = '$id_booking'");
    $data = $cek->fetch_assoc();

    if ($data['status'] === 'diterima') {
        // Update ke dikerjakan
        $koneksi->query("UPDATE booking SET status = 'dikerjakan' WHERE id = '$id_booking'");
    } elseif ($data['status'] === 'dikerjakan') {
        // Update ke selesai
        $koneksi->query("UPDATE booking SET status = 'selesai' WHERE id = '$id_booking'");
    }

    echo "<script>alert('Status booking diperbarui!'); window.location='dashboard_pegawai.php';</script>";
    exit;
}

// Ambil data booking yang statusnya diterima atau dikerjakan
$result = $koneksi->query("
    SELECT b.*, u.nama AS nama_customer, 
           l.nama_layanan, p.nama_paket
    FROM booking b
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    LEFT JOIN paket p ON b.id_paket = p.id
    WHERE b.status IN ('diterima', 'dikerjakan')
    ORDER BY b.tanggal DESC, b.jam ASC
");
?>

<h2>Kelola Booking (Pegawai)</h2>
<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>No</th>
        <th>Customer</th>
        <th>Jenis</th>
        <th>Layanan / Paket</th>
        <th>Tanggal</th>
        <th>Jam</th>
        <th>Status</th>
        <th>Aksi</th>
    </tr>
    <?php
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        $jenis = $row['id_paket'] ? 'Paket Hemat' : 'Layanan Biasa';
        $nama  = $row['id_paket'] ? $row['nama_paket'] : $row['nama_layanan'];
    ?>
    <tr>
        <td><?= $no++ ?></td>
        <td><?= htmlspecialchars($row['nama_customer']) ?></td>
        <td><?= $jenis ?></td>
        <td><?= htmlspecialchars($nama) ?></td>
        <td><?= $row['tanggal'] ?></td>
        <td><?= $row['jam'] ?></td>
        <td><?= $row['status'] ?></td>
        <td>
            <?php if ($row['status'] === 'diterima' || $row['status'] === 'dikerjakan') { ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id_booking" value="<?= $row['id'] ?>">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="status" value="<?= $row['status'] ?>">
                    <button type="submit">
                        <?= $row['status'] === 'diterima' ? 'Mulai Kerjakan' : 'Selesaikan' ?>
                    </button>
                </form>
            <?php } else {
                echo "-";
            } ?>
        </td>
    </tr>
    <?php } ?>
</table>


<p><a href="dashboard_pegawai.php">← Kembali ke Dashboard Pegawai</a></p>
