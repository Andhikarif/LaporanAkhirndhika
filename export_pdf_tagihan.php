<?php
require('fpdf/fpdf.php');
require 'koneksi.php';

// Inisialisasi variabel bulan dan tahun
$bulan_laporan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$tahun_laporan = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Pastikan koneksi database tersedia dan valid
if (!$koneksi) {
    die("Koneksi database gagal! Periksa file koneksi.php Anda.");
}

// Ambil data tagihan lengkap
// Dihapus referensi ke paket_layanan dan kolom terkait
$query = "
    SELECT
        t.*,
        u.nama AS nama_customer,
        b.id_layanan,
        l.nama_layanan
    FROM tagihan t
    JOIN booking b ON t.id_booking = b.id
    JOIN users u ON b.id_user = u.id
    LEFT JOIN layanan l ON b.id_layanan = l.id
    WHERE MONTH(t.tanggal_tagihan) = ? AND YEAR(t.tanggal_tagihan) = ?
    ORDER BY t.tanggal_tagihan ASC
";

$stmt = $koneksi->prepare($query);

// Periksa apakah prepare berhasil
if ($stmt === false) {
    die("Error menyiapkan query: " . $koneksi->error);
}

$stmt->bind_param("ii", $bulan_laporan, $tahun_laporan);
$execute_success = $stmt->execute();

// Periksa apakah execute berhasil
if ($execute_success === false) {
    die("Error mengeksekusi query: " . $stmt->error);
}

$result = $stmt->get_result();

// Periksa apakah get_result berhasil dan mengembalikan objek mysqli_result
if ($result === false) {
    die("Error mendapatkan hasil query: " . $stmt->error);
}

// --- PDF setup ---
class PDF extends FPDF
{
    protected $bulan;
    protected $tahun;

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4', $bulan, $tahun)
    {
        parent::__construct($orientation, $unit, $size);
        $this->bulan = $bulan;
        $this->tahun = $tahun;
    }

    function Header()
    {
        // Ganti 'path/to/your/logo_header.png' dengan path sebenarnya ke logo Anda untuk header
        // Sesuaikan posisi (X, Y) dan ukuran (width)
        $this->Image('logo.png', 10, 10, 30); // Contoh: 10mm dari kiri, 10mm dari atas, lebar 30mm

        $this->SetFillColor(44, 62, 80); // Biru Tua (#2C3E50)
        $this->SetTextColor(255, 255, 255); // Putih
        $this->SetFont('Arial', 'B', 18);
        // Pindahkan judul sedikit ke kanan jika logo di kiri
        $this->Cell(40); // Offset untuk logo di kiri
        $this->Cell(0, 15, 'Laporan Tagihan Bulanan', 0, 1, 'C', true);
        $this->Ln(2);

        $this->SetTextColor(52, 73, 94); // Biru Keabu-abuan (#34495E)
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Periode: ' . date('F Y', mktime(0, 0, 0, $this->bulan, 1, $this->tahun)), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        // Ganti 'path/to/your/logo_footer.png' dengan path sebenarnya ke logo Anda untuk footer
        // Sesuaikan posisi (X, Y) dan ukuran (width)
        $this->Image('logo.png', 10, -28, 20); // Contoh: 10mm dari kiri, 28mm dari bawah, lebar 20mm

        $this->SetY(-15); // Posisi 1.5 cm dari bawah
        $this->SetFont('Arial', 'I', 8); // Font italic kecil
        $this->SetTextColor(150, 150, 150); // Abu-abu
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Dicetak pada ' . date('d-m-Y H:i', time()), 0, 0, 'R');
    }

    function TableHeader($header)
    {
        $this->SetFillColor(52, 152, 219); // Biru Cerah (#3498DB)
        $this->SetTextColor(255, 255, 255); // Putih
        $this->SetDrawColor(44, 62, 80); // Garis biru tua
        $this->SetFont('Arial', 'B', 10);

        // Lebar kolom disesuaikan karena 'Paket' sudah tidak ada
        $w = array(10, 40, 25, 60, 50, 30, 30, 30); // Lebar kolom (menyesuaikan kolom layanan/paket)
        for($i=0; $i<count($header); $i++) {
            $this->Cell($w[$i], 9, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }

    function TableRow($data)
    {
        $this->SetFillColor(248, 249, 249); // Abu-abu sangat terang (#F8F9F9)
        $this->SetTextColor(0, 0, 0); // Hitam
        $this->SetDrawColor(200, 200, 200); // Garis abu-abu terang
        $this->SetFont('Arial', '', 9);

        // Lebar kolom disesuaikan
        $w = array(10, 40, 25, 60, 50, 30, 30, 30); // Lebar kolom (menyesuaikan kolom layanan/paket)
        $align = array('C', 'L', 'L', 'L', 'L', 'C', 'C', 'R'); // Penjajaran teks

        for($i=0; $i<count($data); $i++) {
            $this->Cell($w[$i], 8, $data[$i], 1, 0, $align[$i], true);
        }
        $this->Ln();
    }
}

// Membuat objek PDF
$pdf = new PDF('L', 'mm', 'A4', $bulan_laporan, $tahun_laporan);
$pdf->AliasNbPages(); // Untuk {nb} di footer
$pdf->AddPage();

// Header Tabel - Nama kolom "Layanan/Paket" diubah menjadi "Layanan" dan "Jenis" menjadi "Layanan"
$header = array('No', 'Customer', 'Jenis', 'Layanan', 'Rincian', 'Status Bayar', 'Tanggal', 'Total');
$pdf->TableHeader($header);

// Isi Tabel
$no = 1;
$totalSudahBayar = 0;

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 9);

// Cek apakah ada baris data sebelum mengulang
if ($result->num_rows > 0) {
    $result->data_seek(0); // Memastikan pointer hasil kembali ke awal
    while ($row = $result->fetch_assoc()) {
        $jenis = 'Layanan Biasa'; // Karena tidak ada lagi paket, jenis selalu 'Layanan Biasa'
        $namaLayanan = $row['nama_layanan']; // Hanya ambil nama layanan

        $statusBayarText = ucfirst($row['status_bayar']);
        if ($row['status_bayar'] === 'sudah') {
            $pdf->SetTextColor(39, 174, 96); // Hijau Emerald (#27AE60)
        } else {
            $pdf->SetTextColor(192, 57, 43); // Merah (#C0392B)
        }

        $dataRow = array(
            $no++,
            $row['nama_customer'],
            $jenis,
            $namaLayanan,
            $row['rincian'],
            $statusBayarText,
            date('d-m-Y', strtotime($row['tanggal_tagihan'])),
            'Rp' . number_format($row['total'], 0, ',', '.')
        );
        $pdf->TableRow($dataRow);
        $pdf->SetTextColor(0, 0, 0); // Reset warna teks ke hitam setelah status

        if ($row['status_bayar'] === 'sudah') {
            $totalSudahBayar += $row['total'];
        }
    }
} else {
    // Tampilkan pesan jika tidak ada data
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 10, 'Tidak ada data tagihan untuk bulan ini.', 1, 1, 'C');
}


// Total
$pdf->SetFillColor(44, 62, 80);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(235, 10, 'Total Sudah Dibayar', 1, 0, 'R', true);
$pdf->Cell(30, 10, 'Rp' . number_format($totalSudahBayar, 0, ',', '.'), 1, 1, 'R', true);

$pdf->Output();
$stmt->close();
$koneksi->close();
?>