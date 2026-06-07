<?php
include '../config/connection.php';
session_start();

// Proteksi halaman: hanya admin dan pemilik
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pemilik'])) {
    header("Location: ../auth/login.php");
    exit();
}

$success = '';
$error = '';

// ── UPDATE STATUS BOOKING ──────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $id_booking     = (int) $_POST['id_booking'];
    $status_baru    = $_POST['status_booking'];
    $allowed_status = ['pending', 'dikonfirmasi', 'selesai', 'dibatalkan'];

    if (in_array($status_baru, $allowed_status)) {
        $query = "UPDATE booking SET status_booking = '$status_baru' WHERE id_booking = $id_booking";
        if (mysqli_query($conn, $query)) {
            // Jika dibatalkan, kembalikan slot jadwal menjadi kosong
            if ($status_baru === 'dibatalkan') {
                $get_booking = mysqli_query($conn, "SELECT * FROM booking WHERE id_booking = $id_booking");
                $booking_data = mysqli_fetch_assoc($get_booking);
                $update_jadwal = "UPDATE jadwal_lapangan 
                                  SET status_slot = 'kosong' 
                                  WHERE id_lapangan = {$booking_data['id_lapangan']}
                                  AND tanggal = '{$booking_data['tanggal_main']}'
                                  AND jam_mulai = '{$booking_data['jam_mulai']}'
                                  AND jam_selesai = '{$booking_data['jam_selesai']}'";
                mysqli_query($conn, $update_jadwal);
            }
            $success = "Status booking #$id_booking berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui status: " . mysqli_error($conn);
        }
    }
}

// ── HAPUS BOOKING ──────────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id_booking = (int) $_GET['hapus'];
    $query = "DELETE FROM booking WHERE id_booking = $id_booking";
    if (mysqli_query($conn, $query)) {
        $success = "Booking #$id_booking berhasil dihapus.";
    } else {
        $error = "Gagal menghapus: " . mysqli_error($conn);
    }
}

// ── FILTER & PENCARIAN ─────────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if ($filter_status !== '') {
    $where .= " AND b.status_booking = '$filter_status'";
}
if ($search !== '') {
    $where .= " AND (p.nama_pengguna LIKE '%$search%' OR l.nama_lapangan LIKE '%$search%')";
}

// ── AMBIL DATA BOOKING ─────────────────────────────────────────────────────
$query = "SELECT 
            b.id_booking,
            p.nama_pengguna,
            l.nama_lapangan,
            l.jenis_olahraga,
            b.tanggal_main,
            b.jam_mulai,
            b.jam_selesai,
            b.total_harga,
            b.status_booking,
            b.created_at,
            pay.status_bayar,
            pay.metode_bayar
          FROM booking b
          JOIN pengguna p ON b.id_pengguna = p.id_pengguna
          JOIN lapangan l ON b.id_lapangan = l.id_lapangan
          LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
          $where
          ORDER BY b.created_at DESC";

$result = mysqli_query($conn, $query);

// ── HITUNG STATISTIK ───────────────────────────────────────────────────────
$stat_query = mysqli_query($conn, "SELECT status_booking, COUNT(*) as total FROM booking GROUP BY status_booking");
$stats = ['pending' => 0, 'dikonfirmasi' => 0, 'selesai' => 0, 'dibatalkan' => 0];
while ($s = mysqli_fetch_assoc($stat_query)) {
    $stats[$s['status_booking']] = $s['total'];
}
$total_all = array_sum($stats);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking — MyField</title>
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
            --text:      #e6edf3;
            --muted:     #7d8590;
            --radius:    12px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 240px; height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 28px 20px;
            display: flex; flex-direction: column; gap: 6px;
            z-index: 100;
        }
        .sidebar-brand {
            font-family: 'Syne', sans-serif;
            font-size: 22px; font-weight: 800;
            color: var(--accent);
            letter-spacing: -0.5px;
            margin-bottom: 28px;
            display: flex; align-items: center; gap: 8px;
        }
        .sidebar-brand span { color: var(--text); }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 8px;
            color: var(--muted); text-decoration: none;
            font-size: 14px; font-weight: 500;
            transition: all .2s;
        }
        .nav-item:hover, .nav-item.active {
            background: var(--surface2);
            color: var(--text);
        }
        .nav-item.active { color: var(--accent); }
        .nav-icon { font-size: 16px; width: 20px; text-align: center; }
        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 13px; color: var(--muted);
        }
        .sidebar-footer strong { color: var(--text); display: block; margin-bottom: 2px; }

        /* ── MAIN ── */
        .main {
            margin-left: 240px;
            padding: 32px;
            min-height: 100vh;
        }

        /* ── HEADER ── */
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 32px;
        }
        .page-title {
            font-family: 'Syne', sans-serif;
            font-size: 28px; font-weight: 800;
            letter-spacing: -0.5px;
        }
        .page-title span { color: var(--accent); }
        .page-sub { color: var(--muted); font-size: 14px; margin-top: 4px; }

        /* ── ALERT ── */
        .alert {
            padding: 12px 16px; border-radius: var(--radius);
            margin-bottom: 24px; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: rgba(0,200,150,.12); border: 1px solid rgba(0,200,150,.3); color: var(--accent); }
        .alert-error   { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: var(--danger); }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            position: relative; overflow: hidden;
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
        }
        .stat-card.all::before    { background: var(--accent2); }
        .stat-card.pending::before     { background: var(--warn); }
        .stat-card.dikonfirmasi::before { background: var(--accent); }
        .stat-card.selesai::before     { background: #a78bfa; }
        .stat-card.dibatalkan::before  { background: var(--danger); }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 8px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; }
        .stat-card.all .stat-value    { color: var(--accent2); }
        .stat-card.pending .stat-value     { color: var(--warn); }
        .stat-card.dikonfirmasi .stat-value { color: var(--accent); }
        .stat-card.selesai .stat-value     { color: #a78bfa; }
        .stat-card.dibatalkan .stat-value  { color: var(--danger); }

        /* ── TOOLBAR ── */
        .toolbar {
            display: flex; gap: 12px; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap;
        }
        .search-box {
            flex: 1; min-width: 200px;
            display: flex; align-items: center; gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px; padding: 0 14px;
        }
        .search-box input {
            flex: 1; background: none; border: none; outline: none;
            color: var(--text); font-family: 'DM Sans', sans-serif;
            font-size: 14px; padding: 10px 0;
        }
        .search-box input::placeholder { color: var(--muted); }
        .filter-select {
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text); font-family: 'DM Sans', sans-serif;
            font-size: 14px; padding: 10px 14px;
            border-radius: 8px; outline: none; cursor: pointer;
        }
        .btn {
            padding: 10px 20px; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
            cursor: pointer; border: none; transition: all .2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: var(--accent); color: #0d1117; }
        .btn-primary:hover { background: #00b386; }
        .btn-ghost {
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-ghost:hover { background: var(--surface2); }

        /* ── TABLE ── */
        .table-wrapper {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--surface2); }
        th {
            padding: 12px 16px; text-align: left;
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 1px; color: var(--muted); font-weight: 600;
        }
        td {
            padding: 14px 16px; font-size: 14px;
            border-top: 1px solid var(--border);
        }
        tr:hover td { background: rgba(255,255,255,.02); }

        .booking-id {
            font-family: 'Syne', sans-serif; font-weight: 700;
            color: var(--accent2); font-size: 13px;
        }
        .customer-name { font-weight: 500; }
        .field-name { color: var(--muted); font-size: 13px; margin-top: 2px; }
        .time-info { font-size: 13px; }
        .date-text { font-weight: 500; }
        .time-text { color: var(--muted); font-size: 12px; }
        .price { font-family: 'Syne', sans-serif; font-weight: 700; color: var(--accent); }

        /* ── BADGE STATUS ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px;
        }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; display: block; }
        .badge-pending     { background: rgba(245,158,11,.15); color: var(--warn);    }
        .badge-pending::before     { background: var(--warn); }
        .badge-dikonfirmasi { background: rgba(0,200,150,.15); color: var(--accent);  }
        .badge-dikonfirmasi::before { background: var(--accent); }
        .badge-selesai     { background: rgba(167,139,250,.15); color: #a78bfa;       }
        .badge-selesai::before     { background: #a78bfa; }
        .badge-dibatalkan  { background: rgba(239,68,68,.15);   color: var(--danger); }
        .badge-dibatalkan::before  { background: var(--danger); }
        .badge-lunas  { background: rgba(0,200,150,.1); color: var(--accent); font-size: 10px; }
        .badge-lunas::before { background: var(--accent); }
        .badge-belum_bayar, .badge-pending_pay { background: rgba(245,158,11,.1); color: var(--warn); font-size: 10px; }
        .badge-belum_bayar::before, .badge-pending_pay::before { background: var(--warn); }
        .badge-gagal  { background: rgba(239,68,68,.1); color: var(--danger); font-size: 10px; }
        .badge-gagal::before { background: var(--danger); }

        /* ── ACTIONS ── */
        .actions { display: flex; gap: 8px; align-items: center; }
        .select-status {
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--text); font-family: 'DM Sans', sans-serif;
            font-size: 12px; padding: 6px 10px;
            border-radius: 6px; outline: none; cursor: pointer;
        }
        .btn-sm {
            padding: 6px 12px; font-size: 12px; border-radius: 6px;
        }
        .btn-danger { background: rgba(239,68,68,.15); color: var(--danger); border: 1px solid rgba(239,68,68,.3); }
        .btn-danger:hover { background: rgba(239,68,68,.25); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 60px 20px; color: var(--muted);
        }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.7); z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px; width: 100%; max-width: 420px;
        }
        .modal-title {
            font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700;
            margin-bottom: 8px;
        }
        .modal-body { color: var(--muted); font-size: 14px; margin-bottom: 24px; }
        .modal-body strong { color: var(--text); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">⚡ My<span>Field</span></div>

    <a href="dashboard.php" class="nav-item">
        <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a href="field_manage.php" class="nav-item">
        <span class="nav-icon">🏟️</span> Kelola Lapangan
    </a>
    <a href="booking_manage.php" class="nav-item active">
        <span class="nav-icon">📋</span> Kelola Booking
    </a>
    <a href="payment_confirm.php" class="nav-item">
        <span class="nav-icon">💳</span> Konfirmasi Bayar
    </a>

    <div class="sidebar-footer">
        <strong><?= htmlspecialchars($_SESSION['nama_pengguna']) ?></strong>
        <?= ucfirst($_SESSION['role']) ?> &nbsp;·&nbsp;
        <a href="../auth/logout.php" style="color:var(--danger); text-decoration:none;">Keluar</a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">

    <!-- HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title">Kelola <span>Booking</span></div>
            <div class="page-sub">Pantau dan kelola semua transaksi pemesanan lapangan</div>
        </div>
    </div>

    <!-- ALERT -->
    <?php if ($success): ?>
        <div class="alert alert-success">✓ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">✗ <?= $error ?></div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="stats-grid">
        <div class="stat-card all">
            <div class="stat-label">Total Booking</div>
            <div class="stat-value"><?= $total_all ?></div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?= $stats['pending'] ?></div>
        </div>
        <div class="stat-card dikonfirmasi">
            <div class="stat-label">Dikonfirmasi</div>
            <div class="stat-value"><?= $stats['dikonfirmasi'] ?></div>
        </div>
        <div class="stat-card selesai">
            <div class="stat-label">Selesai</div>
            <div class="stat-value"><?= $stats['selesai'] ?></div>
        </div>
        <div class="stat-card dibatalkan">
            <div class="stat-label">Dibatalkan</div>
            <div class="stat-value"><?= $stats['dibatalkan'] ?></div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <form method="GET" action="">
        <div class="toolbar">
            <div class="search-box">
                🔍
                <input type="text" name="search" placeholder="Cari nama pelanggan atau lapangan..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="pending"      <?= $filter_status==='pending'      ?'selected':'' ?>>Pending</option>
                <option value="dikonfirmasi" <?= $filter_status==='dikonfirmasi' ?'selected':'' ?>>Dikonfirmasi</option>
                <option value="selesai"      <?= $filter_status==='selesai'      ?'selected':'' ?>>Selesai</option>
                <option value="dibatalkan"   <?= $filter_status==='dibatalkan'   ?'selected':'' ?>>Dibatalkan</option>
            </select>
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if ($search || $filter_status): ?>
                <a href="booking_manage.php" class="btn btn-ghost">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- TABEL BOOKING -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Pelanggan</th>
                    <th>Lapangan</th>
                    <th>Jadwal</th>
                    <th>Total</th>
                    <th>Pembayaran</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><span class="booking-id">#<?= $row['id_booking'] ?></span></td>
                    <td>
                        <div class="customer-name"><?= htmlspecialchars($row['nama_pengguna']) ?></div>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($row['nama_lapangan']) ?></div>
                        <div class="field-name"><?= htmlspecialchars($row['jenis_olahraga']) ?></div>
                    </td>
                    <td class="time-info">
                        <div class="date-text"><?= date('d M Y', strtotime($row['tanggal_main'])) ?></div>
                        <div class="time-text">
                            <?= substr($row['jam_mulai'],0,5) ?> – <?= substr($row['jam_selesai'],0,5) ?>
                        </div>
                    </td>
                    <td>
                        <span class="price">Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></span>
                    </td>
                    <td>
                        <?php
                            $sb = $row['status_bayar'] ?? 'belum_bayar';
                            $sb_class = ($sb === 'pending') ? 'pending_pay' : $sb;
                            $sb_label = ['belum_bayar'=>'Belum Bayar','pending'=>'Menunggu','lunas'=>'Lunas','gagal'=>'Gagal'][$sb] ?? $sb;
                        ?>
                        <span class="badge badge-<?= $sb_class ?>"><?= $sb_label ?></span>
                        <?php if ($row['metode_bayar']): ?>
                            <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                                <?= str_replace('_',' ', ucfirst($row['metode_bayar'])) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $row['status_booking'] ?>">
                            <?= ucfirst($row['status_booking']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <!-- Form ubah status -->
                            <form method="POST" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="id_booking" value="<?= $row['id_booking'] ?>">
                                <select name="status_booking" class="select-status">
                                    <option value="pending"      <?= $row['status_booking']==='pending'      ?'selected':'' ?>>Pending</option>
                                    <option value="dikonfirmasi" <?= $row['status_booking']==='dikonfirmasi' ?'selected':'' ?>>Konfirmasi</option>
                                    <option value="selesai"      <?= $row['status_booking']==='selesai'      ?'selected':'' ?>>Selesai</option>
                                    <option value="dibatalkan"   <?= $row['status_booking']==='dibatalkan'   ?'selected':'' ?>>Batalkan</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Simpan</button>
                            </form>
                            <!-- Tombol hapus -->
                            <button class="btn btn-danger btn-sm"
                                onclick="confirmHapus(<?= $row['id_booking'] ?>, '<?= htmlspecialchars($row['nama_pengguna']) ?>')">
                                🗑
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>Tidak ada data booking<?= $search || $filter_status ? ' yang sesuai filter' : '' ?>.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal-overlay" id="modalHapus">
    <div class="modal">
        <div class="modal-title">🗑 Hapus Booking</div>
        <div class="modal-body" id="modalBody">
            Apakah kamu yakin ingin menghapus booking ini? Tindakan ini tidak dapat dibatalkan.
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
            <a href="#" id="btnHapusConfirm" class="btn btn-danger">Ya, Hapus</a>
        </div>
    </div>
</div>

<script>
function confirmHapus(id, nama) {
    document.getElementById('modalBody').innerHTML =
        `Yakin ingin menghapus booking <strong>#${id}</strong> milik <strong>${nama}</strong>? Tindakan ini tidak dapat dibatalkan.`;
    document.getElementById('btnHapusConfirm').href = `booking_manage.php?hapus=${id}`;
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