<?php
session_start();
include 'db.php';
require 'vendor/autoload.php'; // PhpSpreadsheet autoload

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = "";

// ==== Handle Excel Upload ====
if (isset($_POST['upload'])) {
    if (!empty($_FILES['excel_file']['tmp_name'])) {
        $file_tmp  = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        $file_ext  = pathinfo($file_name, PATHINFO_EXTENSION);

        // Allow only Excel files
        if (!in_array($file_ext, ['xls', 'xlsx'])) {
            $message = "<div class='alert alert-danger'>❌ Only .xls or .xlsx files are allowed.</div>";
        } else {
            try {
                $spreadsheet = IOFactory::load($file_tmp);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // Skip header row
                $count = 0;
                foreach ($rows as $index => $row) {
                    if ($index === 0) continue; // Skip header
                    $name   = trim($row[0]);
                    $number = trim($row[1]);

                    if ($name !== '' && $number !== '') {
                        $stmt = $conn->prepare("INSERT INTO target (name, number_list) VALUES (?, ?)");
                        $stmt->bind_param("ss", $name, $number);
                        $stmt->execute();
                        $count++;
                    }
                }

                $message = "<div class='alert alert-success'>✅ Successfully uploaded $count records.</div>";
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger'>❌ Error reading file: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-warning'>⚠️ Please select an Excel file to upload.</div>";
    }
}

// ==== Fetch all records ====
$result = $conn->query("SELECT * FROM target ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Target Numbers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#f8f9fa; font-family:'Poppins', sans-serif; }
        .container { max-width:900px; margin-top:50px; background:white; padding:30px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        h2 { color:#0b1b58; margin-bottom:20px; text-align:center; }
        .btn-upload { background:#0b1b58; color:white; font-weight:500; }
        table { margin-top:25px; }
        th { background:#0b1b58; color:white; }
        .alert { margin-top:15px; }
    </style>
</head>
<body>

<div class="container">
    <h2>📤 Upload Target Numbers</h2>

    <?= $message ?>

    <form method="post" enctype="multipart/form-data" class="mb-3">
        <div class="row">
            <div class="col-md-8">
                <input type="file" name="excel_file" class="form-control" accept=".xls,.xlsx" required>
            </div>
            <div class="col-md-4">
                <button type="submit" name="upload" class="btn btn-upload w-100">Upload Excel</button>
            </div>
        </div>
    </form>

    <hr>

    <h5>📋 Uploaded Records</h5>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">#</th>
                    <th>Name</th>
                    <th>Number</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['number_list']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">No data available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
