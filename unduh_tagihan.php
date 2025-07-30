<?php
require('fpdf/fpdf.php');
include 'koneksi.php'; // Pastikan file koneksi.php Anda sudah benar dan terhubung ke database

if (!isset($_GET['id'])) {
    die("ID booking tidak ditemukan.");
}

$id_booking = $_GET['id'];

// Ambil data booking + tagihan + info layanan + DETAIL MOBIL
// Dihapus referensi ke paket dan tabel paket_layanan
$query = "
    SELECT
        b.id, b.tanggal, b.jam, b.status, b.id_layanan,
        b.merk_mobil, b.tahun_mobil, b.transmisi, b.warna_mobil,
        u.nama AS nama_customer, u.email,
        l.nama_layanan,
        t.rincian, t.total, t.tanggal_tagihan
    FROM booking b
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    JOIN tagihan t ON b.id = t.id_booking
    WHERE b.id = ? AND b.status = 'selesai'
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $id_booking);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Data tagihan tidak ditemukan atau booking belum selesai.");
}

$data = $result->fetch_assoc();

// --- Logika untuk mendapatkan detail layanan dalam paket (dihapus karena tidak ada paket) ---
// Bagian ini dikosongkan karena fungsionalitas paket dihilangkan
$paket_details_list = []; // Tetap didefinisikan tapi akan selalu kosong
// --------------------------------------------------------------------------------------

// Siapkan PDF
class PDF extends FPDF {
    // Header
    function Header() {
        // Logo Bengkel (Pastikan path dan nama file logo Anda benar, misal 'logo_bengkel.png')
        $this->Image('logo.png', 10, 8, 30);
        $this->SetFont('Arial', 'B', 16);
        // Judul
        $this->Cell(0, 10, 'SEMARANG AUTO CAR', 0, 1, 'C'); // Sesuaikan dengan nama bengkel Anda
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Jl. Contoh Alamat No. 123, Semarang', 0, 1, 'C'); // Alamat bengkel
        $this->Cell(0, 5, 'Telp: (024) 12345678 | Email: info@semarangautocar.com', 0, 1, 'C'); // Kontak bengkel
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'TAGIHAN SERVIS KENDARAAN', 0, 1, 'C');
        $this->Ln(5);
        $this->SetDrawColor(0, 0, 0); // Warna garis hitam
        $this->SetLineWidth(0.5); // Ketebalan garis
        $this->Line(10, $this->GetY(), 200, $this->GetY()); // Garis pemisah header
        $this->Ln(5);
    }

    // Footer
    function Footer() {
        $this->SetY(-15); // Posisi 1.5 cm dari bawah
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages(); // Untuk menampilkan total halaman
$pdf->AddPage();

// Informasi Tagihan
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Nomor Booking', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, 'BK-' . sprintf('%04d', $data['id']), 0, 1); // Format nomor booking

$pdf->Cell(50, 8, 'Tanggal Tagihan', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, date('d F Y', strtotime($data['tanggal_tagihan'])), 0, 1);

// Informasi Booking
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'DETAIL BOOKING', 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Nama Customer', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, $data['nama_customer'], 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Tanggal Servis', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, date('d F Y', strtotime($data['tanggal'])), 0, 1);

$pdf->Cell(50, 8, 'Jam Servis', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, substr($data['jam'], 0, 5), 0, 1); // Hanya menampilkan jam:menit

// Detail Layanan
// Logika yang sebelumnya memeriksa 'id_paket' dihapus
$jenis = 'Layanan'; // Selalu 'Layanan'
$nama = $data['nama_layanan']; // Hanya ambil nama layanan

$pdf->Cell(50, 8, 'Jenis Booking', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, $jenis, 0, 1);

$pdf->Cell(50, 8, 'Layanan', 0, 0); // Ubah label dari 'Layanan/Paket' menjadi 'Layanan'
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, $nama, 0, 1);

// Bagian tampilan deskripsi paket atau daftar layanan dalam paket dihilangkan
// karena fungsionalitas paket sudah dihapus.
// if ($data['id_paket'] !== null && !empty($paket_details_list)) { ... }
// elseif ($data['id_paket'] !== null && empty($paket_details_list)) { ... }

// --- BAGIAN BARU: DETAIL MOBIL ---
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'DETAIL KENDARAAN', 0, 1, 'L');
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(50, 8, 'Merk Mobil', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, htmlspecialchars($data['merk_mobil']), 0, 1);

$pdf->Cell(50, 8, 'Tahun Mobil', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, htmlspecialchars($data['tahun_mobil']), 0, 1);

$pdf->Cell(50, 8, 'Transmisi', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, htmlspecialchars(ucfirst($data['transmisi'])), 0, 1); // ucfirst untuk 'Manual'/'Otomatis'

$pdf->Cell(50, 8, 'Warna Mobil', 0, 0);
$pdf->Cell(5, 8, ':', 0, 0);
$pdf->Cell(0, 8, htmlspecialchars($data['warna_mobil']), 0, 1);
// --- AKHIR BAGIAN BARU ---


// Rincian Tambahan (jika ada)
if (!empty($data['rincian'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'RINCIAN TAMBAHAN (Dari Admin)', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, $data['rincian']);
}

// Total Tagihan
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(130, 10, 'TOTAL TAGIHAN:', 0, 0, 'R');
$pdf->Cell(0, 10, 'Rp' . number_format($data['total'], 0, ',', '.'), 0, 1, 'R');

$pdf->Ln(5);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, "Silakan lakukan pembayaran ke rekening berikut:\nBank BCA - 1234567890 a.n. SEMARANG AUTO CAR\n\nUntuk konfirmasi pembayaran, silakan hubungi admin kami.", 0, 'C');

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Terima kasih telah menggunakan layanan kami!', 0, 1, 'C');

// Output file PDF
$pdf->Output('D', 'Tagihan_Booking_' . $data['id'] . '.pdf');
exit;
?>