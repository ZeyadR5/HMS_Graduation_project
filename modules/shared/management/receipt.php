<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/secure-token.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Admin', 'System Admin'], true)) {
    die("Access denied.");
}

$connect = hms_management_connect();
require_once __DIR__ . '/../../../libs/tfpdf/tfpdf.php';
require_once __DIR__ . '/../../../includes/arabic-helper.php';

if (!class_exists('HmsReceiptPdf')) {
    class HmsReceiptPdf extends tFPDF
    {
        public function Header()
        {
            $logoPath = str_replace('\\', '/', __DIR__ . '/../../../assets/images/black echo.png');
            $this->Image($logoPath, 0, 0, 25);
            $this->AddFont('Arial', '', 'arial.ttf', true);
            $this->AddFont('Arial', 'B', 'arialbd.ttf', true);
            $this->SetFont('Arial', '', 12);
            $this->Cell(80);
            $this->Cell(-70, 30, ArabicShaper::prepare('Echo Medical System'), 0, 1, 'C');
            $this->Ln(20);
        }

        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 10, ArabicShaper::prepare('Page ') . $this->PageNo(), 0, 0, 'C');
        }

        public function FancyTable(array $header, array $data): void
        {
            $this->SetY(40);
            $this->SetFillColor(255, 0, 0);
            $this->SetTextColor(255);
            $this->SetDrawColor(128, 0, 0);
            $this->SetLineWidth(.3);
            $this->SetFont('Arial', 'B', 10);

            $widths = [42, 60];
            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);

            $fill = false;
            foreach ($data as $row) {
                for ($i = 0; $i < count($header); $i++) {
                    $this->SetX(1.8);
                    $this->Cell($widths[0], 9, ArabicShaper::prepare($header[$i]), 1, 0, ArabicShaper::getAlign($header[$i]), true);
                    $this->Cell($widths[1], 9, ArabicShaper::prepare($row[$i]), 1, 1, ArabicShaper::getAlign($row[$i]), $fill);
                }
                $this->Ln();
                $fill = !$fill;
            }

            $this->Ln(5);
            $this->SetX(10);
            $this->Cell(60, 0, ArabicShaper::prepare('Signature:'), 0, 0, 'L');
            $this->Cell(50, 0, ArabicShaper::prepare('Stamp:'), 0, 1, 'L');
        }
    }
}

$header = ['Patient Name', 'Date', 'Doctor', 'Specialization', 'Patient ID', 'Total Fees', 'Discount', 'Amount Paid'];
if (isset($_GET['ref'])) {
    $ref = $_GET['ref'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Backward compatibility: redirect raw ID to secure ref
    $ref = hms_encrypt_id(intval($_GET['id']));
    $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?') . '?ref=' . urlencode($ref);
    header("Location: " . $redirectUrl);
    exit();
} else {
    $ref = '';
}

$appointmentId = hms_decrypt_id($ref) ?: 0;

if ($appointmentId === 0) {
    die("Invalid parameters.");
}

$sql = mysqli_query($connect, "SELECT doctors.doctorName AS docname, appointment.* FROM appointment JOIN doctors ON doctors.id = appointment.doctorId WHERE apid = {$appointmentId}");
if (!$sql || mysqli_num_rows($sql) === 0) {
    die("Receipt not found.");
}

$row = mysqli_fetch_assoc($sql);
$fees = (int)$row['consultancyFees'];
$paid = (int)$row['paid'];
$deposit = (($row['deposit_status'] ?? 'none') === 'paid') ? (int)($row['deposit_amount'] ?? 0) : 0;
$discount = (int)($row['discount'] ?? 0);
$totalPaid = $paid + $deposit;

$data = [[
    $row['patient_Name'],
    $row['appointmentDate'],
    $row['docname'],
    $row['doctorSpecialization'],
    $row['userId'],
    $fees . ' EGP',
    $discount . ' EGP',
    $totalPaid . ' EGP',
]];

$pdf = new HmsReceiptPdf('P', 'mm', [105, 148]);
$pdf->SetFont('Arial', '', 14);
$pdf->AddPage();
$pdf->FancyTable($header, $data);
$pdf->Output();
