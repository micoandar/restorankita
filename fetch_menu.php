<?php
include 'config/database.php';

$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'all';

// Query SQL dengan filter kategori
if ($kategori == 'all') {
    $query = "SELECT * FROM menu ORDER BY id DESC";
} else {
    $kategori = mysqli_real_escape_string($conn, $kategori);
    $query = "SELECT * FROM menu WHERE kategori = '$kategori' ORDER BY id DESC";
}

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $imagePath = 'uploads/' . $row['gambar'];
        $image = (!empty($row['gambar']) && file_exists($imagePath)) 
                 ? $imagePath 
                 : 'https://images.unsplash.com/photo-1559314809-2b99056a8c4a?w=600';
        
        echo '
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card card-menu shadow-sm">
                <img src="'.$image.'" class="card-img-top" alt="'.htmlspecialchars($row['nama']).'">
                <div class="card-body">
                    <div class="badge bg-light text-danger mb-2">'.ucfirst($row['kategori']).'</div>
                    <h5 class="card-title">'.htmlspecialchars($row['nama']).'</h5>
                    <p class="card-text text-muted small">'.htmlspecialchars($row['deskripsi']).'</p>
                    <span class="price">Rp '.number_format($row['harga']).'</span>
                    <a href="order.php?menu='.urlencode($row['nama']).'&harga='.$row['harga'].'" class="btn-order">
                        <i class="fas fa-shopping-cart me-2"></i>Pesan Sekarang
                    </a>
                </div>
            </div>
        </div>';
    }
} else {
    echo '<div class="col-12 text-center py-5">
            <i class="fas fa-utensils fa-3x text-light mb-3"></i>
            <p class="text-muted">Menu dalam kategori ini belum tersedia.</p>
          </div>';
}
?>