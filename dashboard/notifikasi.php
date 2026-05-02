<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Riwayat Notifikasi';
$menu_aktif_rayhanrp    = 'notifikasi';
require_once __DIR__ . '/_layout.php';

if (!can('view_notifikasi', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

// ── Filter ──
$fTipe = trim((string)($_GET['filter_tipe'] ?? ''));
$fCari = trim((string)($_GET['cari'] ?? ''));
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql    = 'SELECT n.notifikasi_id,n.tipe,n.pesan,n.jumlah_terkirim,n.waktu_kirim,
                  g.nama_grup
           FROM notifikasi_rayhanRP n
           LEFT JOIN grup_rayhanRP g ON n.grup_id = g.grup_id
           WHERE 1=1';
$types  = ''; $params = [];

if ($fTipe !== '') {
    $sql .= ' AND n.tipe = ?'; $types .= 's'; $params[] = $fTipe;
}
if ($fCari !== '') {
    $sql .= ' AND n.pesan LIKE ?'; $types .= 's'; $params[] = "%$fCari%";
}

// Total
$total_row = sirey_fetch(sirey_query("SELECT COUNT(*) AS c FROM ($sql) x", $types, ...$params));
$total     = (int)($total_row['c'] ?? 0);
$total_pg  = max(1, (int)ceil($total / $limit));

$sql .= ' ORDER BY n.waktu_kirim DESC LIMIT ? OFFSET ?';
$types .= 'ii'; $params[] = $limit; $params[] = $offset;

$daftar = $params
    ? sirey_fetchAll(sirey_query($sql, $types, ...$params))
    : sirey_fetchAll(sirey_query($sql));

// ── Statistik ──
$stats = sirey_fetch(sirey_query(
    "SELECT
        COUNT(*) AS total,
        SUM(jumlah_terkirim) AS total_terkirim,
        SUM(CASE WHEN tipe='tugas' THEN 1 ELSE 0 END) AS jml_tugas,
        SUM(CASE WHEN tipe='jadwal' THEN 1 ELSE 0 END) AS jml_jadwal,
        SUM(CASE WHEN tipe='pengumuman' THEN 1 ELSE 0 END) AS jml_pengumuman,
        SUM(CASE WHEN DATE(waktu_kirim)=CURDATE() THEN 1 ELSE 0 END) AS hari_ini
     FROM notifikasi_rayhanRP"
)) ?? [];

// ── Helper: ikon & warna tipe ──
function tipeInfo(string $tipe): array {
    return match($tipe) {
        'tugas'      => ['bi-journal-text',   '#7c3aed', '#ede9fe', 'Tugas'],
        'jadwal'     => ['bi-calendar3',      '#d97706', '#fef3c7', 'Jadwal'],
        'pengumuman' => ['bi-megaphone-fill', '#2563eb', '#dbeafe', 'Pengumuman'],
        default      => ['bi-bell-fill',      '#64748b', '#f1f5f9', ucfirst($tipe)],
    };
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-bell-fill text-primary me-2"></i>Riwayat Notifikasi</h2>
    <p>Log semua notifikasi yang telah dikirim via Telegram.</p>
  </div>
  <?php if (can('create_pengumuman', $data_admin_rayhanrp)): ?>
    <a href="pengumuman.php" class="btn btn-primary">
      <i class="bi bi-megaphone me-1"></i>Kirim Pengumuman
    </a>
  <?php endif; ?>
</div>

<!-- Statistik -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;">
        <i class="bi bi-broadcast" style="color:#2563eb;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['total'] ?? 0)); ?></div>
        <div class="stat-label">Total Broadcast</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;">
        <i class="bi bi-send-fill" style="color:#059669;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['total_terkirim'] ?? 0)); ?></div>
        <div class="stat-label">Total Terkirim</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;">
        <i class="bi bi-calendar-day" style="color:#d97706;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo (int)($stats['hari_ini'] ?? 0); ?></div>
        <div class="stat-label">Dikirim Hari Ini</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;">
        <i class="bi bi-pie-chart-fill" style="color:#7c3aed;"></i>
      </div>
      <div>
        <div class="stat-value">
          <?php
            $total_all = (int)($stats['total'] ?? 0);
            echo $total_all > 0
                ? round(((int)($stats['total_terkirim'] ?? 0) / $total_all))
                : 0;
          ?>
        </div>
        <div class="stat-label">Rata-rata Penerima</div>
      </div>
    </div>
  </div>
</div>

<!-- Distribusi tipe -->
<div class="row g-3 mb-4">
  <?php
  $distribusi = [
    ['label'=>'Pengumuman','key'=>'jml_pengumuman','icon'=>'bi-megaphone-fill','color'=>'#2563eb','bg'=>'#dbeafe'],
    ['label'=>'Tugas',     'key'=>'jml_tugas',     'icon'=>'bi-journal-text',  'color'=>'#7c3aed','bg'=>'#ede9fe'],
    ['label'=>'Jadwal',    'key'=>'jml_jadwal',    'icon'=>'bi-calendar3',     'color'=>'#d97706','bg'=>'#fef3c7'],
  ];
  foreach ($distribusi as $d): ?>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3 py-3">
          <div class="stat-icon flex-shrink-0" style="background:<?php echo $d['bg']; ?>; width:42px; height:42px; border-radius:10px;">
            <i class="bi <?php echo $d['icon']; ?>" style="color:<?php echo $d['color']; ?>;"></i>
          </div>
          <div class="flex-grow-1">
            <div style="font-size:12px; color:#64748b; font-weight:600;"><?php echo $d['label']; ?></div>
            <div style="font-size:22px; font-weight:800; color:#0f172a; line-height:1.2;">
              <?php echo number_format((int)($stats[$d['key']] ?? 0)); ?>
            </div>
          </div>
          <?php
            $pct_val = $total_all > 0 ? round((int)($stats[$d['key']] ?? 0) / $total_all * 100) : 0;
          ?>
          <div class="text-end">
            <div style="font-size:14px; font-weight:700; color:<?php echo $d['color']; ?>;"><?php echo $pct_val; ?>%</div>
            <div style="font-size:10px; color:#94a3b8;">dari total</div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Filter + Log -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-list-ul me-2"></i>Log Broadcast <span class="badge bg-primary ms-1"><?php echo $total; ?></span></h5>
  </div>

  <!-- Filter bar -->
  <div class="card-body border-bottom py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label">Tipe</label>
        <select name="filter_tipe" class="form-select form-select-sm">
          <option value="">Semua Tipe</option>
          <option value="pengumuman" <?php echo $fTipe==='pengumuman'?'selected':''; ?>>📣 Pengumuman</option>
          <option value="tugas"      <?php echo $fTipe==='tugas'?'selected':''; ?>>📝 Tugas</option>
          <option value="jadwal"     <?php echo $fTipe==='jadwal'?'selected':''; ?>>📅 Jadwal</option>
        </select>
      </div>
      <div class="col-sm-5">
        <label class="form-label">Cari Pesan</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="cari" class="form-control" placeholder="Kata kunci pesan…"
                 value="<?php echo htmlspecialchars($fCari); ?>">
        </div>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="notifikasi.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>

  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state">
        <i class="bi bi-bell-slash"></i>
        <p>Belum ada notifikasi yang cocok.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width:48px;"></th>
              <th>Tipe</th>
              <th>Target Kelas</th>
              <th>Pesan</th>
              <th class="text-center">Terkirim ke</th>
              <th>Waktu</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $n): ?>
              <?php [$icon, $color, $bg, $label] = tipeInfo($n['tipe']); ?>
              <tr>
                <td>
                  <div class="stat-icon mx-auto"
                       style="width:34px;height:34px;border-radius:9px;background:<?php echo $bg; ?>;font-size:14px;">
                    <i class="bi <?php echo $icon; ?>" style="color:<?php echo $color; ?>;"></i>
                  </div>
                </td>
                <td>
                  <span class="badge" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>;border:1px solid <?php echo $color; ?>40;font-size:11px;">
                    <?php echo $label; ?>
                  </span>
                </td>
                <td style="font-size:13px;">
                  <?php echo !empty($n['nama_grup']) ? htmlspecialchars($n['nama_grup']) : '<span class="badge bg-secondary">Semua</span>'; ?>
                </td>
                <td style="max-width:360px;">
                  <div class="text-truncate" style="max-width:340px; font-size:13px;"
                       title="<?php echo htmlspecialchars($n['pesan']); ?>">
                    <?php echo htmlspecialchars(mb_substr((string)$n['pesan'], 0, 100)); ?>
                  </div>
                  <?php if (mb_strlen((string)$n['pesan']) > 100): ?>
                    <button class="btn btn-link btn-sm p-0 text-primary" style="font-size:11px;"
                            onclick="lihatPesan(<?php echo htmlspecialchars(json_encode($n['pesan']), ENT_QUOTES); ?>, '<?php echo htmlspecialchars(addslashes($label)); ?>')">
                      Lihat selengkapnya…
                    </button>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <span class="fw-600" style="font-size:15px; color:#0f172a;">
                    <?php echo number_format((int)$n['jumlah_terkirim']); ?>
                  </span>
                  <div class="text-muted" style="font-size:10px;">orang</div>
                </td>
                <td style="white-space:nowrap; font-size:12px; color:#64748b;">
                  <?php echo formatDatetime($n['waktu_kirim']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pg > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
          <div class="text-muted small">
            Halaman <?php echo $page; ?> dari <?php echo $total_pg; ?> (<?php echo $total; ?> notifikasi)
          </div>
          <div class="d-flex gap-1 flex-wrap">
            <?php
            $qBase = $_GET;
            $start = max(1, $page - 2);
            $end   = min($total_pg, $page + 2);
            if ($page > 1): $qBase['page'] = $page - 1; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary">‹</a>
            <?php endif;
            for ($pg = $start; $pg <= $end; $pg++): $qBase['page'] = $pg; ?>
              <a href="?<?php echo http_build_query($qBase); ?>"
                 class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <?php echo $pg; ?>
              </a>
            <?php endfor;
            if ($page < $total_pg): $qBase['page'] = $page + 1; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary">›</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Modal lihat pesan lengkap -->
<div class="modal fade" id="modalPesan" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-text me-2"></i>Isi Pesan: <span id="pesanLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="pesanIsi" class="p-3 bg-light rounded"
             style="font-size:13.5px; line-height:1.8; white-space:pre-wrap; max-height:500px; overflow-y:auto;"></div>
      </div>
    </div>
  </div>
</div>

<script>
function lihatPesan(teks, label) {
  document.getElementById('pesanLabel').textContent = label;
  document.getElementById('pesanIsi').textContent   = teks;
  new bootstrap.Modal(document.getElementById('modalPesan')).show();
}
</script>

<?php layoutEnd(); ?>