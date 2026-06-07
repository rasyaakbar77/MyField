<?php
include '../config/connection.php';
session_start();

// Proteksi halaman: hanya admin dan pemilik
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
        // Proses upload foto
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
        // Proses upload foto baru jika ada
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
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_name)) {
                    // Hapus foto lama
                    if ($foto_lama && file_exists($upload_dir . $foto_lama)) {
                        unlink($upload_dir . $foto_lama);
                    }
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
    // Ambil nama foto dulu sebelum hapus
    $foto_res = mysqli_query($conn, "SELECT foto, nama_lapangan FROM lapangan WHERE id_lapangan=$id_lapangan");
    $foto_row = mysqli_fetch_assoc($foto_res);
    $query = "DELETE FROM lapangan WHERE id_lapangan=$id_lapangan";
    if (mysqli_query($conn, $query)) {
        // Hapus file foto jika ada
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
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE 1=1";
if ($search)        $where .= " AND nama_lapangan LIKE '%$search%'";
if ($filter_jenis)  $where .= " AND jenis_olahraga = '$filter_jenis'";
if ($filter_status) $where .= " AND status = '$filter_status'";

$result   = mysqli_query($conn, "SELECT * FROM lapangan $where ORDER BY created_at DESC");
$all_jenis = mysqli_query($conn, "SELECT DISTINCT jenis_olahraga FROM lapangan ORDER BY jenis_olahraga");

// Statistik
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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:        #0d1117;
            --surface:   #161b22;
            --surface2:  #1c2330;
            --border:    #30363d;
            --accent:    #00c896;
            --accent2:   #0ea5e9;
            --warn:      #f59e0b;
            --danger:    #ef4444;
            --purple:    #a78bfa;
            --text:      #e6edf3;
            --muted:     #7d8590;
            --radius:    12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; min-height:100vh; }

        /* SIDEBAR */
        .sidebar {
            position:fixed; top:0; left:0; width:240px; height:100vh;
            background:var(--surface); border-right:1px solid var(--border);
            padding:28px 20px; display:flex; flex-direction:column; gap:6px; z-index:100;
        }
        .sidebar-brand { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; color:var(--accent); letter-spacing:-.5px; margin-bottom:28px; display:flex; align-items:center; gap:8px; }
        .sidebar-brand span { color:var(--text); }
        .nav-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:8px; color:var(--muted); text-decoration:none; font-size:14px; font-weight:500; transition:all .2s; }
        .nav-item:hover, .nav-item.active { background:var(--surface2); color:var(--text); }
        .nav-item.active { color:var(--accent); }
        .nav-icon { font-size:16px; width:20px; text-align:center; }
        .sidebar-footer { margin-top:auto; padding-top:20px; border-top:1px solid var(--border); font-size:13px; color:var(--muted); }
        .sidebar-footer strong { color:var(--text); display:block; margin-bottom:2px; }

        /* MAIN */
        .main { margin-left:240px; padding:32px; }

        /* HEADER */
        .page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; }
        .page-title { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; letter-spacing:-.5px; }
        .page-title span { color:var(--accent); }
        .page-sub { color:var(--muted); font-size:14px; margin-top:4px; }

        /* ALERT */
        .alert { padding:12px 16px; border-radius:var(--radius); margin-bottom:24px; font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success { background:rgba(0,200,150,.12); border:1px solid rgba(0,200,150,.3); color:var(--accent); }
        .alert-error   { background:rgba(239,68,68,.12);  border:1px solid rgba(239,68,68,.3);  color:var(--danger); }

        /* STATS */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; position:relative; overflow:hidden; transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .stat-card.all::before       { background:var(--accent2); }
        .stat-card.tersedia::before  { background:var(--accent); }
        .stat-card.pemeliharaan::before { background:var(--warn); }
        .stat-card.tidak::before     { background:var(--danger); }
        .stat-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:8px; }
        .stat-value { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; }
        .stat-card.all .stat-value       { color:var(--accent2); }
        .stat-card.tersedia .stat-value  { color:var(--accent); }
        .stat-card.pemeliharaan .stat-value { color:var(--warn); }
        .stat-card.tidak .stat-value     { color:var(--danger); }

        /* LAYOUT: form kiri, tabel kanan */
        .content-layout { display:grid; grid-template-columns:360px 1fr; gap:24px; align-items:start; }

        /* FORM CARD */
        .form-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px; position:sticky; top:24px; }
        .form-title { font-family:'Syne',sans-serif; font-size:17px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
        .form-title span { color:var(--accent); }
        .form-group { margin-bottom:16px; }
        label { display:block; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); margin-bottom:6px; }
        input[type=text], input[type=number], textarea, select {
            width:100%; background:var(--surface2); border:1px solid var(--border);
            color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px;
            padding:10px 14px; border-radius:8px; outline:none; transition:border-color .2s;
        }
        input:focus, textarea:focus, select:focus { border-color:var(--accent); }
        textarea { resize:vertical; min-height:80px; }

        /* FOTO UPLOAD */
        .foto-upload-area {
            border:2px dashed var(--border); border-radius:8px;
            padding:20px; text-align:center; cursor:pointer;
            transition:border-color .2s; position:relative;
        }
        .foto-upload-area:hover { border-color:var(--accent); }
        .foto-upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .foto-upload-icon { font-size:28px; margin-bottom:6px; }
        .foto-upload-text { font-size:12px; color:var(--muted); }
        .foto-preview { width:100%; height:140px; object-fit:cover; border-radius:8px; margin-top:10px; display:none; }
        .foto-current { width:100%; height:100px; object-fit:cover; border-radius:8px; margin-bottom:10px; }

        .btn { padding:10px 20px; border-radius:8px; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:500; cursor:pointer; border:none; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-primary { background:var(--accent); color:#0d1117; width:100%; justify-content:center; }
        .btn-primary:hover { background:#00b386; }
        .btn-warn { background:var(--warn); color:#0d1117; width:100%; justify-content:center; }
        .btn-warn:hover { background:#d97706; }
        .btn-ghost { background:var(--surface); border:1px solid var(--border); color:var(--text); }
        .btn-ghost:hover { background:var(--surface2); }
        .btn-sm { padding:6px 12px; font-size:12px; border-radius:6px; }
        .btn-danger-sm { background:rgba(239,68,68,.15); color:var(--danger); border:1px solid rgba(239,68,68,.3); }
        .btn-danger-sm:hover { background:rgba(239,68,68,.25); }
        .btn-edit-sm { background:rgba(14,165,233,.15); color:var(--accent2); border:1px solid rgba(14,165,233,.3); }
        .btn-edit-sm:hover { background:rgba(14,165,233,.25); }
        .btn-row { display:flex; gap:8px; margin-top:12px; }

        /* TOOLBAR */
        .toolbar { display:flex; gap:12px; align-items:center; margin-bottom:20px; flex-wrap:wrap; }
        .search-box { flex:1; min-width:180px; display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:0 14px; }
        .search-box input { flex:1; background:none; border:none; outline:none; color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px; padding:10px 0; }
        .search-box input::placeholder { color:var(--muted); }
        .filter-select { background:var(--surface); border:1px solid var(--border); color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px; padding:10px 14px; border-radius:8px; outline:none; cursor:pointer; }

        /* CARD GRID */
        .cards-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:16px; }
        .field-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; transition:transform .2s, box-shadow .2s; }
        .field-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.3); }
        .field-card-img { width:100%; height:130px; object-fit:cover; background:var(--surface2); display:flex; align-items:center; justify-content:center; font-size:36px; color:var(--border); }
        .field-card-img img { width:100%; height:100%; object-fit:cover; }
        .field-card-body { padding:14px; }
        .field-card-name { font-family:'Syne',sans-serif; font-weight:700; font-size:14px; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .field-card-jenis { font-size:12px; color:var(--muted); margin-bottom:8px; }
        .field-card-price { font-family:'Syne',sans-serif; font-weight:700; color:var(--accent); font-size:15px; margin-bottom:8px; }
        .field-card-price span { font-size:11px; font-weight:400; color:var(--muted); }

        /* BADGE */
        .badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:20px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
        .badge::before { content:''; width:5px; height:5px; border-radius:50%; display:block; }
        .badge-tersedia    { background:rgba(0,200,150,.15); color:var(--accent); }
        .badge-tersedia::before { background:var(--accent); }
        .badge-pemeliharaan { background:rgba(245,158,11,.15); color:var(--warn); }
        .badge-pemeliharaan::before { background:var(--warn); }
        .badge-tidak_tersedia { background:rgba(239,68,68,.15); color:var(--danger); }
        .badge-tidak_tersedia::before { background:var(--danger); }

        /* EMPTY */
        .empty-state { text-align:center; padding:60px 20px; color:var(--muted); }
        .empty-icon { font-size:48px; margin-bottom:12px; }

        /* MODAL */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:28px; width:100%; max-width:420px; }
        .modal-title { font-family:'Syne',sans-serif; font-size:18px; font-weight:700; margin-bottom:8px; }
        .modal-body { color:var(--muted); font-size:14px; margin-bottom:24px; }
        .modal-body strong { color:var(--text); }
        .modal-actions { display:flex; gap:10px; justify-content:flex-end; }

        ::-webkit-scrollbar { width:6px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">⚡ My<span>Field</span></div>
    <a href="dashboard.php" class="nav-item"><span class="nav-icon">🏠</span> Dashboard</a>
    <a href="field_manage.php" class="nav-item active"><span class="nav-icon">🏟️</span> Kelola Lapangan</a>
    <a href="booking_manage.php" class="nav-item"><span class="nav-icon">📋</span> Kelola Booking</a>
    <a href="payment_confirm.php" class="nav-item"><span class="nav-icon">💳</span> Konfirmasi Bayar</a>
    <div class="sidebar-footer">
        <strong><?= htmlspecialchars($_SESSION['nama_pengguna']) ?></strong>
        <?= ucfirst($_SESSION['role']) ?> &nbsp;·&nbsp;
        <a href="../auth/logout.php" style="color:var(--danger);text-decoration:none;">Keluar</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <div>
            <div class="page-title">Kelola <span>Lapangan</span></div>
            <div class="page-sub">Tambah, edit, dan kelola data lapangan olahraga</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✓ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">✗ <?= $error ?></div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="stats-grid">
        <div class="stat-card all">
            <div class="stat-label">Total Lapangan</div>
            <div class="stat-value"><?= $total_all ?></div>
        </div>
        <div class="stat-card tersedia">
            <div class="stat-label">Tersedia</div>
            <div class="stat-value"><?= $stats['tersedia'] ?></div>
        </div>
        <div class="stat-card pemeliharaan">
            <div class="stat-label">Pemeliharaan</div>
            <div class="stat-value"><?= $stats['pemeliharaan'] ?></div>
        </div>
        <div class="stat-card tidak">
            <div class="stat-label">Tidak Tersedia</div>
            <div class="stat-value"><?= $stats['tidak_tersedia'] ?></div>
        </div>
    </div>

    <!-- CONTENT LAYOUT -->
    <div class="content-layout">

        <!-- FORM TAMBAH / EDIT -->
        <div class="form-card">
            <div class="form-title">
                <?php if ($edit_data): ?>
                    ✏️ Edit <span>Lapangan</span>
                <?php else: ?>
                    ➕ Tambah <span>Lapangan</span>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id_lapangan" value="<?= $edit_data['id_lapangan'] ?>">
                    <input type="hidden" name="foto_lama" value="<?= $edit_data['foto'] ?? '' ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Nama Lapangan</label>
                    <input type="text" name="nama_lapangan" placeholder="cth: Lapangan Futsal A"
                           value="<?= $edit_data ? htmlspecialchars($edit_data['nama_lapangan']) : '' ?>" required>
                </div>

                <div class="form-group">
                    <label>Jenis Olahraga</label>
                    <input type="text" name="jenis_olahraga" placeholder="cth: Futsal, Badminton, Basket"
                           value="<?= $edit_data ? htmlspecialchars($edit_data['jenis_olahraga']) : '' ?>" required>
                </div>

                <div class="form-group">
                    <label>Harga per Jam (Rp)</label>
                    <input type="number" name="harga_per_jam" placeholder="cth: 100000" min="0"
                           value="<?= $edit_data ? $edit_data['harga_per_jam'] : '' ?>" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="tersedia"       <?= ($edit_data && $edit_data['status']==='tersedia')       ?'selected':'' ?>>Tersedia</option>
                        <option value="pemeliharaan"   <?= ($edit_data && $edit_data['status']==='pemeliharaan')   ?'selected':'' ?>>Pemeliharaan</option>
                        <option value="tidak_tersedia" <?= ($edit_data && $edit_data['status']==='tidak_tersedia') ?'selected':'' ?>>Tidak Tersedia</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" placeholder="Deskripsikan fasilitas lapangan..."><?= $edit_data ? htmlspecialchars($edit_data['deskripsi']) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label>Foto Lapangan</label>
                    <?php if ($edit_data && !empty($edit_data['foto'])): ?>
                        <img src="../assets/img/lapangan/<?= $edit_data['foto'] ?>" class="foto-current" alt="Foto saat ini">
                        <p style="font-size:12px;color:var(--muted);margin-bottom:8px;">Upload foto baru untuk mengganti</p>
                    <?php endif; ?>
                    <div class="foto-upload-area" onclick="document.getElementById('fotoInput').click()">
                        <input type="file" name="foto" id="fotoInput" accept="image/jpg,image/jpeg,image/png,image/webp" onchange="previewFoto(this)">
                        <div class="foto-upload-icon">🖼️</div>
                        <div class="foto-upload-text">Klik untuk upload foto<br><small>JPG, PNG, WEBP — maks 2MB</small></div>
                        <img id="fotoPreview" class="foto-preview" alt="Preview">
                    </div>
                </div>

                <?php if ($edit_data): ?>
                    <button type="submit" name="edit" class="btn btn-warn">💾 Simpan Perubahan</button>
                    <a href="field_manage.php" class="btn btn-ghost" style="margin-top:10px;width:100%;justify-content:center;">Batal</a>
                <?php else: ?>
                    <button type="submit" name="tambah" class="btn btn-primary">➕ Tambah Lapangan</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- DAFTAR LAPANGAN -->
        <div>
            <!-- TOOLBAR -->
            <form method="GET">
                <div class="toolbar">
                    <div class="search-box">
                        🔍
                        <input type="text" name="search" placeholder="Cari nama lapangan..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="jenis" class="filter-select" onchange="this.form.submit()">
                        <option value="">Semua Jenis</option>
                        <?php while ($j = mysqli_fetch_assoc($all_jenis)): ?>
                            <option value="<?= $j['jenis_olahraga'] ?>"
                                    <?= $filter_jenis===$j['jenis_olahraga']?'selected':'' ?>>
                                <?= htmlspecialchars($j['jenis_olahraga']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="tersedia"       <?= $filter_status==='tersedia'       ?'selected':'' ?>>Tersedia</option>
                        <option value="pemeliharaan"   <?= $filter_status==='pemeliharaan'   ?'selected':'' ?>>Pemeliharaan</option>
                        <option value="tidak_tersedia" <?= $filter_status==='tidak_tersedia' ?'selected':'' ?>>Tidak Tersedia</option>
                    </select>
                    <button type="submit" class="btn btn-ghost btn-sm">Cari</button>
                    <?php if ($search || $filter_jenis || $filter_status): ?>
                        <a href="field_manage.php" class="btn btn-ghost btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- CARDS LAPANGAN -->
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="cards-grid">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="field-card">
                        <div class="field-card-img">
                            <?php if (!empty($row['foto'])): ?>
                                <img src="../assets/img/lapangan/<?= htmlspecialchars($row['foto']) ?>" alt="<?= htmlspecialchars($row['nama_lapangan']) ?>">
                            <?php else: ?>
                                🏟️
                            <?php endif; ?>
                        </div>
                        <div class="field-card-body">
                            <div class="field-card-name" title="<?= htmlspecialchars($row['nama_lapangan']) ?>">
                                <?= htmlspecialchars($row['nama_lapangan']) ?>
                            </div>
                            <div class="field-card-jenis">🏅 <?= htmlspecialchars($row['jenis_olahraga']) ?></div>
                            <div class="field-card-price">
                                Rp <?= number_format($row['harga_per_jam'],0,',','.') ?>
                                <span>/ jam</span>
                            </div>
                            <span class="badge badge-<?= $row['status'] ?>">
                                <?= str_replace('_',' ', ucfirst($row['status'])) ?>
                            </span>
                            <div class="btn-row">
                                <a href="field_manage.php?edit=<?= $row['id_lapangan'] ?>"
                                   class="btn btn-edit-sm btn-sm" style="flex:1;justify-content:center;">✏️ Edit</a>
                                <button class="btn btn-danger-sm btn-sm" style="flex:1;justify-content:center;"
                                        onclick="confirmHapus(<?= $row['id_lapangan'] ?>, '<?= htmlspecialchars($row['nama_lapangan']) ?>')">
                                    🗑 Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🏟️</div>
                    <p>Belum ada lapangan<?= $search || $filter_jenis || $filter_status ? ' yang sesuai filter' : '' ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal">
        <div class="modal-title">🗑 Hapus Lapangan</div>
        <div class="modal-body" id="modalBody">Apakah kamu yakin ingin menghapus lapangan ini?</div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
            <a href="#" id="btnHapusConfirm" class="btn" style="background:var(--danger);color:#fff;">Ya, Hapus</a>
        </div>
    </div>
</div>

<script>
// Preview foto sebelum upload
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

// Modal hapus
function confirmHapus(id, nama) {
    document.getElementById('modalBody').innerHTML =
        `Yakin ingin menghapus lapangan <strong>${nama}</strong>? Semua booking terkait juga akan terhapus.`;
    document.getElementById('btnHapusConfirm').href = `field_manage.php?hapus=${id}`;
    document.getElementById('modalHapus').classList.add('active');
}
function closeModal() {
    document.getElementById('modalHapus').classList.remove('active');
}
document.getElementById('modalHapus').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>