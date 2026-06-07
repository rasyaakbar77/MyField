<?php
include '../config/connection.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pemilik'])) {
    header("Location: ../auth/login.php");
    exit();
}

$success = '';
$error   = '';

// ── TAMBAH LAPANGAN ────────────────────────────────────────────────────────
if (isset($_POST['tambah'])) {
    $nama          = mysqli_real_escape_string($conn, trim($_POST['nama_lapangan']));
    $jenis         = mysqli_real_escape_string($conn, trim($_POST['jenis_olahraga']));
    $deskripsi     = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $harga         = (float) $_POST['harga_per_jam'];
    $status        = $_POST['status'];
    $allowed_status = ['tersedia', 'pemeliharaan', 'tidak_tersedia'];
    $foto_name     = NULL;

    if (empty($nama) || empty($jenis) || $harga <= 0) {
        $error = "Nama lapangan, jenis olahraga, dan harga wajib diisi.";
    } elseif (!in_array($status, $allowed_status)) {
        $error = "Status tidak valid.";
    } else {
        if (!empty($_FILES['foto']['name'])) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $error = "Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.";
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2MB.";
            } else {
                $upload_dir = '../assets/img/lapangan/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $foto_name = 'lapangan_' . time() . '.' . $ext;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_name)) {
                    $error = "Gagal mengupload foto.";
                    $foto_name = NULL;
                }
            }
        }
        if (!$error) {
            $foto_val = $foto_name ? "'$foto_name'" : "NULL";
            $query = "INSERT INTO lapangan (nama_lapangan, jenis_olahraga, deskripsi, harga_per_jam, status, foto)
                      VALUES ('$nama', '$jenis', '$deskripsi', $harga, '$status', $foto_val)";
            if (mysqli_query($conn, $query)) {
                $success = "Lapangan \"$nama\" berhasil ditambahkan.";
            } else {
                $error = "Gagal menyimpan: " . mysqli_error($conn);
            }
        }
    }
}

// ── EDIT LAPANGAN ──────────────────────────────────────────────────────────
if (isset($_POST['edit'])) {
    $id_lapangan   = (int) $_POST['id_lapangan'];
    $nama          = mysqli_real_escape_string($conn, trim($_POST['nama_lapangan']));
    $jenis         = mysqli_real_escape_string($conn, trim($_POST['jenis_olahraga']));
    $deskripsi     = mysqli_real_escape_string($conn, trim($_POST['deskripsi']));
    $harga         = (float) $_POST['harga_per_jam'];
    $status        = $_POST['status'];
    $foto_lama     = $_POST['foto_lama'];
    $allowed_status = ['tersedia', 'pemeliharaan', 'tidak_tersedia'];
    $foto_name     = $foto_lama;

    if (empty($nama) || empty($jenis) || $harga <= 0) {
        $error = "Nama lapangan, jenis olahraga, dan harga wajib diisi.";
    } elseif (!in_array($status, $allowed_status)) {
        $error = "Status tidak valid.";
    } else {
        if (!empty($_FILES['foto']['name'])) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) {
                $error = "Format foto tidak didukung.";
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2MB.";
            } else {
                $upload_dir = '../assets/img/lapangan/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $foto_name = 'lapangan_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_name)) {
                    if ($foto_lama && file_exists($upload_dir . $foto_lama)) unlink($upload_dir . $foto_lama);
                } else {
                    $error = "Gagal mengupload foto baru.";
                    $foto_name = $foto_lama;
                }
            }
        }
        if (!$error) {
            $foto_val = $foto_name ? "'$foto_name'" : "NULL";
            $query = "UPDATE lapangan
                      SET nama_lapangan='$nama', jenis_olahraga='$jenis', deskripsi='$deskripsi',
                          harga_per_jam=$harga, status='$status', foto=$foto_val
                      WHERE id_lapangan=$id_lapangan";
            if (mysqli_query($conn, $query)) {
                $success = "Lapangan \"$nama\" berhasil diperbarui.";
            } else {
                $error = "Gagal memperbarui: " . mysqli_error($conn);
            }
        }
    }
}

// ── HAPUS LAPANGAN ─────────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id_lapangan = (int) $_GET['hapus'];
    $foto_res = mysqli_query($conn, "SELECT foto, nama_lapangan FROM lapangan WHERE id_lapangan=$id_lapangan");
    $foto_row = mysqli_fetch_assoc($foto_res);
    $query = "DELETE FROM lapangan WHERE id_lapangan=$id_lapangan";
    if (mysqli_query($conn, $query)) {
        if ($foto_row['foto'] && file_exists('../assets/img/lapangan/' . $foto_row['foto'])) {
            unlink('../assets/img/lapangan/' . $foto_row['foto']);
        }
        $success = "Lapangan \"{$foto_row['nama_lapangan']}\" berhasil dihapus.";
    } else {
        $error = "Gagal menghapus: " . mysqli_error($conn);
    }
}

// ── AMBIL DATA UNTUK EDIT ──────────────────────────────────────────────────
$edit_data = NULL;
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM lapangan WHERE id_lapangan=$id_edit");
    $edit_data = mysqli_fetch_assoc($res);
}

// ── AMBIL SEMUA LAPANGAN ───────────────────────────────────────────────────
$search        = isset($_GET['search'])  ? mysqli_real_escape_string($conn, $_GET['search'])  : '';
$filter_jenis  = isset($_GET['jenis'])   ? mysqli_real_escape_string($conn, $_GET['jenis'])   : '';
$filter_status = isset($_GET['status'])  ? $_GET['status'] : '';

$where = "WHERE 1=1";
if ($search)        $where .= " AND nama_lapangan LIKE '%$search%'";
if ($filter_jenis)  $where .= " AND jenis_olahraga = '$filter_jenis'";
if ($filter_status) $where .= " AND status = '$filter_status'";

$result   = mysqli_query($conn, "SELECT * FROM lapangan $where ORDER BY created_at DESC");
$all_jenis = mysqli_query($conn, "SELECT DISTINCT jenis_olahraga FROM lapangan ORDER BY jenis_olahraga");

$stat_q = mysqli_query($conn, "SELECT status, COUNT(*) as total FROM lapangan GROUP BY status");
$stats  = ['tersedia' => 0, 'pemeliharaan' => 0, 'tidak_tersedia' => 0];
while ($s = mysqli_fetch_assoc($stat_q)) $stats[$s['status']] = $s['total'];
$total_all = array_sum($stats);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lapangan — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-stat { border: none; border-radius: 10px; transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-4px); }
        .field-card-img {
            height: 140px; object-fit: cover; width: 100%;
            background: #e9ecef;
        }
        .foto-upload-area {
            border: 2px dashed #dee2e6; border-radius: 8px;
            padding: 20px; text-align: center; cursor: pointer;
            transition: border-color .2s; position: relative;
        }
        .foto-upload-area:hover { border-color: #0d6efd; }
        .foto-upload-area input[type=file] {
            position: absolute; inset: 0; opacity: 0;
            cursor: pointer; width: 100%; height: 100%;
        }
        #fotoPreview { display: none; width: 100%; max-height: 140px; object-fit: cover; border-radius: 8px; margin-top: 10px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <div class="text-white text-center mb-4">
                <h4><i class="fa-solid fa-dumbbell me-2"></i>MyField</h4>
                <small class="text-muted">Admin Panel</small>
            </div>
            <hr class="text-secondary">
            <ul class="nav flex-column gap-2">
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="dashboard.php">
                        <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded p-3" href="field_manage.php">
                        <i class="fa-solid fa-layer-group me-2"></i> Kelola Lapangan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="booking_manage.php">
                        <i class="fa-solid fa-calendar-check me-2"></i> Kelola Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="payment_confirm.php">
                        <i class="fa-solid fa-credit-card me-2"></i> Konfirmasi Pembayaran
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link rounded p-3 text-danger" href="../auth/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Kelola Lapangan</h1>
                <span class="badge bg-secondary p-2">
                    Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?>
                </span>
            </div>

            <!-- ALERT -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- STATISTIK -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Total</h6>
                                <h3 class="mb-0"><?= $total_all ?></h3>
                            </div>
                            <i class="fa-solid fa-layer-group fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Tersedia</h6>
                                <h3 class="mb-0"><?= $stats['tersedia'] ?></h3>
                            </div>
                            <i class="fa-solid fa-circle-check fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Pemeliharaan</h6>
                                <h3 class="mb-0"><?= $stats['pemeliharaan'] ?></h3>
                            </div>
                            <i class="fa-solid fa-wrench fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-danger text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Tidak Tersedia</h6>
                                <h3 class="mb-0"><?= $stats['tidak_tersedia'] ?></h3>
                            </div>
                            <i class="fa-solid fa-ban fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LAYOUT: FORM KIRI + KARTU KANAN -->
            <div class="row g-4 align-items-start">

                <!-- FORM TAMBAH / EDIT -->
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <?php if ($edit_data): ?>
                                    <i class="fa-solid fa-pen me-2 text-warning"></i>Edit Lapangan
                                <?php else: ?>
                                    <i class="fa-solid fa-plus me-2 text-success"></i>Tambah Lapangan
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="id_lapangan" value="<?= $edit_data['id_lapangan'] ?>">
                                    <input type="hidden" name="foto_lama"   value="<?= $edit_data['foto'] ?? '' ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nama Lapangan</label>
                                    <input type="text" name="nama_lapangan" class="form-control"
                                           placeholder="cth: Lapangan Futsal A"
                                           value="<?= $edit_data ? htmlspecialchars($edit_data['nama_lapangan']) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Jenis Olahraga</label>
                                    <input type="text" name="jenis_olahraga" class="form-control"
                                           placeholder="cth: Futsal, Badminton, Basket"
                                           value="<?= $edit_data ? htmlspecialchars($edit_data['jenis_olahraga']) : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Harga per Jam (Rp)</label>
                                    <input type="number" name="harga_per_jam" class="form-control"
                                           placeholder="cth: 100000" min="0"
                                           value="<?= $edit_data ? $edit_data['harga_per_jam'] : '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="tersedia"       <?= ($edit_data && $edit_data['status']==='tersedia')       ?'selected':'' ?>>Tersedia</option>
                                        <option value="pemeliharaan"   <?= ($edit_data && $edit_data['status']==='pemeliharaan')   ?'selected':'' ?>>Pemeliharaan</option>
                                        <option value="tidak_tersedia" <?= ($edit_data && $edit_data['status']==='tidak_tersedia') ?'selected':'' ?>>Tidak Tersedia</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Deskripsi</label>
                                    <textarea name="deskripsi" class="form-control" rows="3"
                                              placeholder="Deskripsikan fasilitas lapangan..."><?= $edit_data ? htmlspecialchars($edit_data['deskripsi']) : '' ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Foto Lapangan</label>
                                    <?php if ($edit_data && !empty($edit_data['foto'])): ?>
                                        <img src="../assets/img/lapangan/<?= $edit_data['foto'] ?>"
                                             class="img-fluid rounded mb-2" style="max-height:100px;object-fit:cover;width:100%;" alt="Foto saat ini">
                                        <p class="text-muted" style="font-size:12px;">Upload foto baru untuk mengganti</p>
                                    <?php endif; ?>
                                    <div class="foto-upload-area" onclick="document.getElementById('fotoInput').click()">
                                        <input type="file" name="foto" id="fotoInput"
                                               accept="image/jpg,image/jpeg,image/png,image/webp"
                                               onchange="previewFoto(this)">
                                        <i class="fa-solid fa-image fa-2x text-muted mb-2 d-block"></i>
                                        <div class="text-muted" style="font-size:13px;">
                                            Klik untuk upload foto<br>
                                            <small>JPG, PNG, WEBP — maks 2MB</small>
                                        </div>
                                        <img id="fotoPreview" alt="Preview">
                                    </div>
                                </div>

                                <?php if ($edit_data): ?>
                                    <button type="submit" name="edit" class="btn btn-warning w-100 mb-2">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Perubahan
                                    </button>
                                    <a href="field_manage.php" class="btn btn-outline-secondary w-100">Batal</a>
                                <?php else: ?>
                                    <button type="submit" name="tambah" class="btn btn-success w-100">
                                        <i class="fa-solid fa-plus me-2"></i>Tambah Lapangan
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- DAFTAR LAPANGAN -->
                <div class="col-12 col-lg-8">

                    <!-- FILTER -->
                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-body py-2">
                            <form method="GET" class="row g-2 align-items-center">
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white">
                                            <i class="fa-solid fa-magnifying-glass text-muted"></i>
                                        </span>
                                        <input type="text" name="search" class="form-control"
                                               placeholder="Cari nama lapangan..."
                                               value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <select name="jenis" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Semua Jenis</option>
                                        <?php while ($j = mysqli_fetch_assoc($all_jenis)): ?>
                                            <option value="<?= $j['jenis_olahraga'] ?>"
                                                    <?= $filter_jenis===$j['jenis_olahraga']?'selected':'' ?>>
                                                <?= htmlspecialchars($j['jenis_olahraga']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="tersedia"       <?= $filter_status==='tersedia'       ?'selected':'' ?>>Tersedia</option>
                                        <option value="pemeliharaan"   <?= $filter_status==='pemeliharaan'   ?'selected':'' ?>>Pemeliharaan</option>
                                        <option value="tidak_tersedia" <?= $filter_status==='tidak_tersedia' ?'selected':'' ?>>Tidak Tersedia</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-auto d-flex gap-1">
                                    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                                    <?php if ($search || $filter_jenis || $filter_status): ?>
                                        <a href="field_manage.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- KARTU LAPANGAN -->
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <div class="row g-3">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                $status_color = ['tersedia' => 'success', 'pemeliharaan' => 'warning', 'tidak_tersedia' => 'danger'];
                                $sc = $status_color[$row['status']] ?? 'secondary';
                            ?>
                            <div class="col-12 col-sm-6">
                                <div class="card border-0 shadow-sm rounded-3 h-100">
                                    <?php if (!empty($row['foto'])): ?>
                                        <img src="../assets/img/lapangan/<?= htmlspecialchars($row['foto']) ?>"
                                             class="field-card-img card-img-top rounded-top-3"
                                             alt="<?= htmlspecialchars($row['nama_lapangan']) ?>">
                                    <?php else: ?>
                                        <div class="field-card-img d-flex align-items-center justify-content-center rounded-top-3">
                                            <i class="fa-solid fa-image fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($row['nama_lapangan']) ?></h6>
                                        <div class="text-muted mb-2" style="font-size:12px;">
                                            <i class="fa-solid fa-medal me-1"></i><?= htmlspecialchars($row['jenis_olahraga']) ?>
                                        </div>
                                        <div class="fw-bold text-success mb-2">
                                            Rp <?= number_format($row['harga_per_jam'], 0, ',', '.') ?>
                                            <small class="text-muted fw-normal">/ jam</small>
                                        </div>
                                        <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle rounded-pill px-3 py-1 mb-3">
                                            <?= str_replace('_', ' ', ucfirst($row['status'])) ?>
                                        </span>
                                        <div class="d-flex gap-2">
                                            <a href="field_manage.php?edit=<?= $row['id_lapangan'] ?>"
                                               class="btn btn-warning btn-sm flex-fill">
                                                <i class="fa-solid fa-pen me-1"></i>Edit
                                            </a>
                                            <button class="btn btn-danger btn-sm flex-fill"
                                                    onclick="confirmHapus(<?= $row['id_lapangan'] ?>, '<?= htmlspecialchars($row['nama_lapangan']) ?>')">
                                                <i class="fa-solid fa-trash me-1"></i>Hapus
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body text-center py-5 text-muted">
                                <i class="fa-solid fa-layer-group fa-2x mb-2 d-block"></i>
                                Belum ada lapangan<?= $search || $filter_jenis || $filter_status ? ' yang sesuai filter' : '' ?>.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-trash me-2 text-danger"></i>Hapus Lapangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Apakah kamu yakin ingin menghapus lapangan ini?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnHapusConfirm" class="btn btn-danger">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewFoto(input) {
    const preview = document.getElementById('fotoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function confirmHapus(id, nama) {
    document.getElementById('modalBody').innerHTML =
        `Yakin ingin menghapus lapangan <strong>${nama}</strong>? Semua booking terkait juga akan terhapus.`;
    document.getElementById('btnHapusConfirm').href = `field_manage.php?hapus=${id}`;
    new bootstrap.Modal(document.getElementById('modalHapus')).show();
}
</script>

</body>
</html>