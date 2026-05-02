<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Audit Log';
$menu_aktif_rayhanrp    = 'audit_log';
require_once __DIR__ . '/_layout.php';

if (!can('view_audit_log', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

// ── Filter params ──
$fAksi   = trim((string)($_GET['aksi']   ?? ''));
$fStatus = trim((string)($_GET['status'] ?? ''));
$fNama   = trim((string)($_GET['nama']   ?? ''));
$fTgl    = trim((string)($_GET['tgl']    ?? ''));
$page    = max(1, (int)($_GET['page']    ?? 1));
$limit   = 30;
$offset  = ($page - 1) * $limit;

// ── Query ──
$sql    = 'SELECT al.id,al.aksi,al.objek_tipe,al.objek_id,al.status,al.detail,
                  al.ip_address,al.waktu,a.nama_lengkap,a.role
           FROM audit_log_rayhanRP al
           LEFT JOIN akun_rayhanRP a ON al.akun_id=a.akun_id
           WHERE 1=1';
$types = ''; $params = [];

if ($fAksi !== '') {
    $sql .= ' AND al.aksi LIKE ?'; $types .= 's'; $params[] = "%$fAksi%";
}
if ($fStatus !== '') {
    $sql .= ' AND al.status=?'; $types .= 's'; $params[] = $fStatus;
}
if ($fNama !== '') {
    $sql .= ' AND a.nama_lengkap LIKE ?'; $types .= 's'; $params[] = "%$fNama%";
}
if ($fTgl !== '') {
    $sql .= ' AND DATE(al.waktu)=?'; $types .= 's'; $params[] = $fTgl;
}

// Total
$total_row = sirey_fetch(sirey_query("SELECT COUNT(*) AS c FROM ($sql) x", $types, ...$params));
$total     = (int)($total_row['c'] ?? 0);
$total_pg  = max(1, (int)ceil($total / $limit));

$sql .= ' ORDER BY al.waktu DESC LIMIT ? OFFSET ?';
$types .= 'ii'; $params[] = $limit; $params[] = $offset;

$baris = $types
    ? sirey_fetchAll(sirey_query($sql, $types, ...$params))
    : sirey_fetchAll(sirey_query($sql));

// ── Statistik ringkas ──
$stats = sirey_fetch(sirey_query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='sukses'  THEN 1 ELSE 0 END) AS sukses,
        SUM(CASE WHEN status='gagal'   THEN 1 ELSE 0 END) AS gagal,
        SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) AS ditolak,
        SUM(CASE WHEN DATE(waktu)=CURDATE() THEN 1 ELSE 0 END) AS hari_ini
     FROM audit_log_rayhanRP"
)) ?? [];

// ── Aksi unik untuk filter dropdown ──
$aksi_unik = sirey_fetchAll(sirey_query(
    'SELECT DISTINCT aksi FROM audit_log_rayhanRP ORDER BY aksi ASC'
));

// ── Helper ──
function statusBadgeAudit(string $status): string {
    return match($status) {
        'sukses'  => '<span class="badge bg-success">✅ Sukses</span>',
        'gagal'   => '<span class="badge bg-danger">❌ Gagal</span>',
        'ditolak' => '<span class="badge bg-warning text-dark">⚠️ Ditolak</span>',
        default   => '<span class="badge bg-secondary">'.htmlspecialchars($status).'</span>',
    };
}

function aksiIcon(string $aksi): string {
    if (str_contains($aksi,'login'))  return 'bi-box-arrow-in-right text-primary';
    if (str_contains($aksi,'logout')) return 'bi-box-arrow-right text-secondary';
    if (str_contains($aksi,'create') || str_contains($aksi,'insert')) return 'bi-plus-circle text-success';
    if (str_contains($aksi,'update') || str_contains($aksi,'edit'))   return 'bi-pencil text-warning';
    if (str_contains($aksi,'delete') || str_contains($aksi,'hapus'))  return 'bi-trash text-danger';
    if (str_contains($aksi,'reset'))  return 'bi-key text-warning';
    if (str_contains($aksi,'import')) return 'bi-upload text-info';
    if (str_contains($aksi,'export')) return 'bi-download text-info';
    if (str_contains($aksi,'toggle')) return 'bi-toggle-on text-secondary';
    return 'bi-activity text-muted';
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-shield-check text-success me-2"></i>Audit Log</h2>
    <p>Riwayat aksi sensitif dan perubahan penting di sistem.</p>
  </div>
  <span class="badge bg-success fs-6 px-3 py-2">
    <i class="bi bi-database me-1"></i><?php echo number_format($total); ?> records
  </span>
</div>

<!-- Statistik -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;">
        <i class="bi bi-activity" style="color:#2563eb;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['total'] ?? 0)); ?></div>
        <div class="stat-label">Total Aktivitas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;">
        <i class="bi bi-check-circle-fill" style="color:#059669;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['sukses'] ?? 0)); ?></div>
        <div class="stat-label">Sukses</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fee2e2;">
        <i class="bi bi-x-circle-fill" style="color:#dc2626;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['gagal'] ?? 0)); ?></div>
        <div class="stat-label">Gagal</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;">
        <i class="bi bi-calendar-day" style="color:#d97706;"></i>
      </div>
      <div>
        <div class="stat-value"><?php echo number_format((int)($stats['hari_ini'] ?? 0)); ?></div>
        <div class="stat-label">Aktivitas Hari Ini</div>
      </div>
    </div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label">Aksi</label>
        <select name="aksi" class="form-select form-select-sm">
          <option value="">Semua Aksi</option>
          <?php foreach ($aksi_unik as $au): ?>
            <option value="<?php echo htmlspecialchars($au['aksi']); ?>"
                    <?php echo $fAksi === $au['aksi'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($au['aksi']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua</option>
          <option value="sukses"  <?php echo $fStatus==='sukses'?'selected':''; ?>>✅ Sukses</option>
          <option value="gagal"   <?php echo $fStatus==='gagal'?'selected':''; ?>>❌ Gagal</option>
          <option value="ditolak" <?php echo $fStatus==='ditolak'?'selected':''; ?>>⚠️ Ditolak</option>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label">Nama Pelaku</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" name="nama" class="form-control" placeholder="Cari nama…"
                 value="<?php echo htmlspecialchars($fNama); ?>">
        </div>
      </div>
      <div class="col-sm-2">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tgl" class="form-control form-control-sm"
               value="<?php echo htmlspecialchars($fTgl); ?>">
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="audit_log.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel Log -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5>
      <i class="bi bi-table me-2"></i>Riwayat Aktivitas
      <span class="badge bg-primary ms-1"><?php echo number_format($total); ?></span>
    </h5>
    <button class="btn btn-sm btn-outline-success" onclick="exportCSV()">
      <i class="bi bi-download me-1"></i>Export CSV
    </button>
  </div>
  <div class="card-body p-0">
    <?php if (empty($baris)): ?>
      <div class="empty-state">
        <i class="bi bi-shield"></i>
        <p>Belum ada data audit yang cocok.</p>
        <?php if ($fAksi || $fStatus || $fNama || $fTgl): ?>
          <a href="audit_log.php" class="btn btn-sm btn-outline-primary mt-2">Reset Filter</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblAudit">
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Pelaku</th>
              <th>Aksi</th>
              <th>Objek</th>
              <th>Status</th>
              <th>Detail</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($baris as $item): ?>
              <?php
                $detail = '—';
                if (!empty($item['detail'])) {
                    $dec = json_decode((string)$item['detail'], true);
                    $detail = is_array($dec)
                        ? json_encode($dec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string)$item['detail'];
                }
                $icon = aksiIcon($item['aksi']);
              ?>
              <tr>
                <td style="white-space:nowrap; font-size:12px; color:#64748b;">
                  <div><?php echo date('d/m/Y', strtotime($item['waktu'])); ?></div>
                  <div style="font-size:11px;"><?php echo date('H:i:s', strtotime($item['waktu'])); ?></div>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:30px;height:30px;background:#f1f5f9;font-size:11px;font-weight:700;color:#475569;">
                      <?php echo strtoupper(substr($item['nama_lengkap'] ?? 'S', 0, 1)); ?>
                    </div>
                    <div>
                      <div style="font-size:13px;font-weight:600;">
                        <?php echo htmlspecialchars($item['nama_lengkap'] ?? 'Sistem'); ?>
                      </div>
                      <?php if (!empty($item['role'])): ?>
                        <span class="badge <?php echo roleBadgeClass($item['role']); ?>" style="font-size:9px;">
                          <?php echo roleLabel($item['role']); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi <?php echo $icon; ?> fs-5 flex-shrink-0"></i>
                    <code style="font-size:12px; background:#f8fafc; padding:2px 6px; border-radius:4px; border:1px solid #e2e8f0;">
                      <?php echo htmlspecialchars($item['aksi']); ?>
                    </code>
                  </div>
                </td>
                <td style="font-size:12px;">
                  <?php if (!empty($item['objek_tipe'])): ?>
                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['objek_tipe']); ?></span>
                    <?php if (!empty($item['objek_id'])): ?>
                      <span class="text-muted ms-1">#<?php echo (int)$item['objek_id']; ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?php echo statusBadgeAudit((string)$item['status']); ?></td>
                <td style="max-width:280px;">
                  <?php if ($detail !== '—'): ?>
                    <button class="btn btn-link btn-sm p-0 text-muted text-start" style="font-size:11px; max-width:260px;"
                            onclick="lihatDetail(<?php echo htmlspecialchars(json_encode($detail), ENT_QUOTES); ?>, '<?php echo htmlspecialchars(addslashes($item['aksi'])); ?>')">
                      <span class="text-truncate d-block" style="max-width:240px;"><?php echo htmlspecialchars(mb_substr($detail, 0, 60)); ?>…</span>
                    </button>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:11px; color:#94a3b8; white-space:nowrap;">
                  <?php echo htmlspecialchars($item['ip_address'] ?? '—'); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
        <div class="text-muted small">
          Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $limit, $total); ?> dari <?php echo number_format($total); ?> records
        </div>
        <?php if ($total_pg > 1): ?>
          <div class="d-flex gap-1 flex-wrap">
            <?php
            $qBase = $_GET;
            if ($page > 1): $qBase['page'] = $page - 1; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
              </a>
            <?php endif;

            $start = max(1, $page - 2);
            $end   = min($total_pg, $page + 2);

            if ($start > 1): $qBase['page'] = 1; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary">1</a>
              <?php if ($start > 2): ?><span class="btn btn-sm btn-outline-secondary disabled">…</span><?php endif; ?>
            <?php endif;

            for ($pg = $start; $pg <= $end; $pg++): $qBase['page'] = $pg; ?>
              <a href="?<?php echo http_build_query($qBase); ?>"
                 class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <?php echo $pg; ?>
              </a>
            <?php endfor;

            if ($end < $total_pg):
                if ($end < $total_pg - 1): ?><span class="btn btn-sm btn-outline-secondary disabled">…</span><?php endif;
                $qBase['page'] = $total_pg; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary"><?php echo $total_pg; ?></a>
            <?php endif;

            if ($page < $total_pg): $qBase['page'] = $page + 1; ?>
              <a href="?<?php echo http_build_query($qBase); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-code-slash me-2"></i>Detail Aksi: <code id="detailAksiLabel"></code>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="detailJson"
             style="background:#0f172a; color:#e2e8f0; padding:20px; border-radius:10px;
                    font-size:13px; line-height:1.7; max-height:500px; overflow-y:auto;
                    white-space:pre-wrap; word-break:break-word;"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyDetail()">
          <i class="bi bi-clipboard me-1"></i>Salin
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
function lihatDetail(raw, aksi) {
  document.getElementById('detailAksiLabel').textContent = aksi;
  let formatted = raw;
  try {
    const parsed = JSON.parse(raw);
    formatted = JSON.stringify(parsed, null, 2);
  } catch (e) { /* bukan JSON */ }
  document.getElementById('detailJson').textContent = formatted;
  new bootstrap.Modal(document.getElementById('modalDetail')).show();
}

function copyDetail() {
  const txt = document.getElementById('detailJson').textContent;
  navigator.clipboard.writeText(txt).then(() => {
    Swal.fire({ icon:'success', title:'Tersalin!', timer:1200, showConfirmButton:false });
  });
}

function exportCSV() {
  const rows   = [];
  const thead  = document.querySelector('#tblAudit thead tr');
  const thCols = [...thead.querySelectorAll('th')].map(th => '"' + th.innerText.trim() + '"');
  rows.push(thCols.join(','));

  document.querySelectorAll('#tblAudit tbody tr').forEach(tr => {
    const cols = [...tr.querySelectorAll('td')].map(td => {
      const val = td.innerText.replace(/\n/g,' ').replace(/"/g,'""').trim();
      return '"' + val + '"';
    });
    rows.push(cols.join(','));
  });

  const blob = new Blob(['\uFEFF' + rows.join('\n')], { type:'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  const now  = new Date().toISOString().slice(0,10).replace(/-/g,'');
  a.href     = url;
  a.download = `audit_log_${now}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// Highlight baris gagal/ditolak
document.querySelectorAll('#tblAudit tbody tr').forEach(tr => {
  const statusEl = tr.querySelector('.badge');
  if (!statusEl) return;
  if (statusEl.classList.contains('bg-danger'))        tr.style.background = '#fff5f5';
  else if (statusEl.classList.contains('bg-warning'))  tr.style.background = '#fffbeb';
});
</script>

<?php layoutEnd(); ?>