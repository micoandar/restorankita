<?php
include '../config/database.php';

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $status = $_GET['status'];
    $search = $_GET['search'];

    $where = "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    if ($status != 'all') {
        $where .= " AND status = '$status'";
    }
    if (!empty($search)) {
        $where .= " AND (nama_pelanggan LIKE '%$search%' OR menu LIKE '%$search%')";
    }

    $filename = "Laporan_Order_" . date('Ymd') . ".xls";

    // Header untuk download excel
    header("Content-Type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    echo "Data Order Restoran Kita\n";
    echo "Periode: $start_date sampai $end_date\n\n";
    
    echo "ID\tTanggal\tPelanggan\tMenu\tJumlah\tTotal\tStatus\n";

    $query = mysqli_query($conn, "SELECT * FROM orders $where ORDER BY created_at DESC");
    while ($row = mysqli_fetch_assoc($query)) {
        echo "#" . str_pad($row['id'], 6, '0', STR_PAD_LEFT) . "\t";
        echo $row['created_at'] . "\t";
        echo $row['nama_pelanggan'] . "\t";
        echo $row['menu'] . "\t";
        echo $row['jumlah'] . "\t";
        echo $row['total'] . "\t";
        echo $row['status'] . "\n";
    }
    exit();
}
?>