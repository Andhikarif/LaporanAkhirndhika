<?php
session_start();
include 'koneksi.php';

// Hanya customer yang boleh akses
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['user']['id'];

// Ambil data booking + tagihan user
// Bergabung hanya dengan tabel layanan
$query = "
    SELECT
        b.id AS booking_id, b.tanggal, b.jam, b.status,
        l.nama_layanan,
        t.total AS total_tagihan,
        t.rincian AS rincian_tagihan,
        t.id AS id_tagihan
    FROM booking b
    LEFT JOIN layanan l ON b.id_layanan = l.id
    LEFT JOIN tagihan t ON b.id = t.id_booking
    WHERE b.id_user = ?
    ORDER BY b.tanggal DESC, b.jam DESC
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

$rows_data = [];
while ($row = $result->fetch_assoc()) {
    $rows_data[] = $row;
}

// Fungsi refValues tidak lagi diperlukan karena tidak ada binding parameter dinamis untuk paket
// if (!function_exists('refValues')) {
//     function refValues($arr)
//     {
//         if (strnatcmp(phpversion(), '5.6') >= 0) {
//             return $arr;
//         }
//         $refs = array();
//         foreach ($arr as $key => $value) {
//             $refs[$key] = &$arr[$key];
//         }
//         return $refs;
//     }
// }
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking - Bengkel XYZ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variabel CSS untuk kemudahan perubahan warna */
        :root {
            --dark-charcoal: #212121;
            --light-grey: #f8f8f8;
            --white: #ffffff;
            --mid-grey: #6c757d;
            --accent-blue: #007bff;
            --hover-blue: #0056b3;
            --border-color: #ddd;
            --status-pending: #ffc107;
            --status-confirmed: #28a745;
            --status-selesai: #17a2b8;
            --status-dibatalkan: #dc3545;
            --status-dikerjakan: #0dcaf0;
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
            max-width: 1000px;
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

        /* Override Bootstrap table styling */
        .table {
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table thead th {
            background-color: var(--dark-charcoal);
            color: var(--white);
            font-weight: 600;
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background-color: #f0f0f0;
        }

        .table td {
            color: var(--dark-charcoal);
        }

        /* Status badge styling */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
            color: var(--white);
        }

        .status-badge.pending {
            background-color: var(--status-pending);
            color: var(--dark-charcoal);
        }

        .status-badge.diterima {
            background-color: var(--status-confirmed);
        }

        .status-badge.selesai {
            background-color: var(--status-selesai);
        }

        .status-badge.dibatalkan {
            background-color: var(--status-dibatalkan);
        }

        .status-badge.dikerjakan {
            background-color: var(--status-dikerjakan);
            color: var(--white);
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: var(--accent-blue);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease;
            text-decoration: none;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .btn-download:hover {
            background-color: var(--hover-blue);
            color: var(--white);
        }

        .link-back {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: var(--accent-blue);
            font-weight: 600;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .link-back:hover {
            color: var(--hover-blue);
            text-decoration: underline;
        }

        /* Detail info styling */
        .detail-info {
            font-size: 0.85em;
            color: var(--mid-grey);
            margin-top: 5px;
            padding-left: 10px;
            border-left: 2px solid var(--border-color);
        }

        .detail-info ul {
            list-style-type: disc;
            padding-left: 20px;
            margin-top: 5px;
        }

        .detail-info ul li {
            margin-bottom: 3px;
        }

        .btn-cancel {
            background-color: var(--status-dibatalkan);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 600;
            transition: background-color 0.3s ease;
            text-decoration: none;
            font-size: 0.9em;
            margin-top: 5px;
            border: none;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background-color: #b02a37; /* Slightly darker red */
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .container-custom {
                margin: 20px auto;
                padding: 15px;
            }

            h2 {
                font-size: 1.8em;
            }

            .table th,
            .table td {
                padding: 10px 8px;
                font-size: 0.9em;
            }

            /* Hide columns on smaller screens */
            .table th:nth-child(1),
            .table td:nth-child(1) {
                display: none; /* No */
            }

            /* The 'Jenis' column (which was the 4th child) is now gone. 
               If you want to hide another column, adjust nth-child accordingly. */
            /* For example, if you want to hide the 'Aksi' column on mobile: */
            /* .table th:last-child,
            .table td:last-child {
                display: none;
            } */
        }
    </style>
</head>

<body>

    <div class="container container-custom">
        <h2>Riwayat Booking Anda</h2>

        <?php if (!empty($rows_data)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal & Jam</th>
                            <th>Layanan</th>
                            <th>Status</th>
                            <th>Tagihan & Rincian</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($rows_data as $row) {
                            // Karena tidak ada paket lagi, "Jenis" selalu "Layanan Biasa" atau bisa dihapus
                            // Saya akan menghapus kolom "Jenis" karena informasi ini tidak lagi relevan
                            
                            $nama_layanan = htmlspecialchars($row['nama_layanan'] ? $row['nama_layanan'] : 'Tidak Diketahui');

                            $status_class = strtolower($row['status']);

                            echo "<tr>";
                            echo "<td>{$no}</td>";
                            echo "<td>" . htmlspecialchars($row['tanggal']) . "<br>" . htmlspecialchars($row['jam']) . "</td>";
                            echo "<td>{$nama_layanan}</td>";
                            echo "<td><span class='status-badge {$status_class}'>" . htmlspecialchars($row['status']) . "</span></td>";
                            echo "<td>";

                            if ($row['status'] == 'selesai' && $row['total_tagihan'] !== null) {
                                echo "<strong>Total:</strong> Rp" . number_format($row['total_tagihan'], 0, ',', '.');
                                if (!empty($row['rincian_tagihan'])) {
                                    echo "<div class='detail-info'><strong>Rincian Tambahan:</strong><br>" . nl2br(htmlspecialchars($row['rincian_tagihan'])) . "</div>";
                                }
                                echo "<a href='unduh_tagihan.php?id={$row['booking_id']}' class='btn-download'><i class='fas fa-download'></i> Unduh PDF</a>";
                            } else if ($row['status'] == 'diterima') {
                                echo "Booking Anda telah diterima!";
                            } else if ($row['status'] == 'dikerjakan') {
                                echo "Kendaraan Anda sedang dikerjakan!";
                            } else if ($row['status'] == 'pending') {
                                echo "Menunggu konfirmasi admin!";
                            } else if ($row['status'] == 'dibatalkan') {
                                echo "Booking ini telah dibatalkan.";
                            } else {
                                echo "-";
                            }
                            echo "</td>";
                            // Kolom Aksi
                            echo "<td>";
                            if ($row['status'] == 'pending') {
                                echo "<form action='cancel_booking.php' method='POST' onsubmit='return confirm(\"Apakah Anda yakin ingin membatalkan booking ini?\");'>";
                                echo "<input type='hidden' name='booking_id' value='{$row['booking_id']}'>";
                                echo "<button type='submit' class='btn-cancel'><i class='fas fa-times-circle'></i> Batal</button>";
                                echo "</form>";
                            } else {
                                echo "-"; // Tidak ada aksi untuk status lain
                            }
                            echo "</td>";
                            echo "</tr>";
                            $no++;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">Anda belum memiliki riwayat booking.</p>
        <?php endif; ?>

        <a href="dashboard_customer.php" class="link-back"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
$stmt->close();
$koneksi->close();
?>