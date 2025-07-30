<?php
session_start();
include 'koneksi.php'; // Pastikan file koneksi.php sudah terhubung ke database

// Hanya customer yang boleh akses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

// Pastikan request adalah POST dan booking_id ada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    $id_user = $_SESSION['user']['id'];

    // Memulai transaksi
    $koneksi->begin_transaction();

    try {
        // Cek status booking dan pastikan booking tersebut milik user yang sedang login
        $stmt_check = $koneksi->prepare("SELECT status FROM booking WHERE id = ? AND id_user = ? FOR UPDATE"); // FOR UPDATE untuk locking row
        $stmt_check->bind_param("ii", $booking_id, $id_user);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $row_check = $result_check->fetch_assoc();
            if ($row_check['status'] == 'pending') {
                // Update status booking menjadi 'dibatalkan'
                $stmt_update = $koneksi->prepare("UPDATE booking SET status = 'dibatalkan' WHERE id = ?");
                $stmt_update->bind_param("i", $booking_id);
                $stmt_update->execute();

                if ($stmt_update->affected_rows > 0) {
                    $koneksi->commit();
                    $_SESSION['pesan'] = ['type' => 'success', 'text' => 'Booking berhasil dibatalkan.'];
                } else {
                    throw new Exception('Gagal membatalkan booking. Tidak ada perubahan yang dilakukan.');
                }
                $stmt_update->close();
            } else {
                throw new Exception('Booking tidak dapat dibatalkan karena statusnya bukan pending.');
            }
        } else {
            throw new Exception('Booking tidak ditemukan atau Anda tidak memiliki izin untuk membatalkan booking ini.');
        }
        $stmt_check->close();
    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['pesan'] = ['type' => 'danger', 'text' => 'Error: ' . $e->getMessage()];
    }

    // Redirect kembali ke halaman riwayat booking
    header("Location: riwayat_booking.php");
    exit;
} else {
    // Jika akses langsung tanpa POST atau tanpa booking_id
    header("Location: riwayat_booking.php");
    exit;
}

$koneksi->close();
?>