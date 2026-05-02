<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Pengumuman';
$menu_aktif_rayhanrp    = 'pengumuman';
require_once __DIR__ . '/_layout.php';

if (!can('view_pengumuman', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_buat = can('create_pengumuman', $data_admin_rayhanrp);
$pesan = $error = '';

$opsi_grup = $data_admin_rayhanrp['role'] === 'guru'
    ? getGrupDiajarGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT grup_id,nama_grup FROM grup_rayhanRP WHERE aktif=1 ORDER BY nama_grup ASC'));

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['act'] ?? 'kirim');

    if ($aksi === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $dp = sirey_fetch(sirey_query('SELECT pengumuman_id,pembuat_id FROM pengumuman_rayhanRP WHERE pengumuman_id=?','i',$id));
            $allowed = $dp && (
                in_array($data_admin_rayhanrp['role'], ['admin','kurikulum'], true)
                || (int)$dp['pembuat_id'] === (int)$data_admin_rayhanrp['id']
            );
            if (!$allowed) { $error = 'Anda tidak berhak menghapus pengumuman ini.'; }
            else {
                sirey_execute('DELETE FROM pengumuman_rayhanRP WHERE pengumuman_id=?','i',$id);
                auditLog($data_admin_rayhanrp['id'],'delete_pengumuman','pengumuman',$id);
                $pesan = 'Pengumuman berhasil dihapus.';
            }
        }

    } elseif ($bisa_buat) {
        requireNotReadonly($data_admin_rayhanrp, 'pengumuman.php');

        $judul      = trim((string)($_POST['judul'] ?? ''));
        $isi        = trim((string)($_POST['pesan'] ?? ''));
        $grup_id    = (int)($_POST['grup_id'] ?? 0);
        $prioritas  = in_array($_POST['prioritas'] ?? '', ['biasa','penting','darurat'], true) ? (string)$_POST['prioritas'] : 'biasa';
        $target_role = $data_admin_rayhanrp['role'] === 'guru' ? 'siswa'
            : (in_array($_POST['target_role'] ?? '', ['all','guru','siswa'], true) ? (string)$_POST['target_role'] : 'all');
        $tgl_tayang = trim((string)($_POST['tanggal_tayang'] ?? date('Y-m-d')));
        $grup_target = $grup_id > 0 ? $grup_id : null;

        if ($judul === '' || $isi === '') {
            $error = 'Judul dan isi pengumuman wajib diisi.';
        } elseif ($data_admin_rayhanrp['role'] === 'guru' && $grup_target === null) {
            $error = 'Guru wajib memilih salah satu kelas yang diajar.';
        } else {
            // Kumpulkan target Telegram
            $sql_tg = 'SELECT DISTINCT at.telegram_chat_id
                       FROM akun_telegram_rayhanRP at
                       INNER JOIN akun_rayhanRP a ON at.akun_id=a.akun_id';
            $t_types = ''; $t_params = [];
            if ($grup_target !== null) {
                $sql_tg .= ' INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id=ga.akun_id WHERE ga.grup_id=? AND ga.aktif=1';
                $t_types .= 'i'; $t_params[] = $grup_target;
            } else {
                $sql_tg .= ' WHERE 1=1';
            }
            if ($target_role !== 'all') {
                $sql_tg .= ' AND a.role=?'; $t_types .= 's'; $t_params[] = $target_role;
            }
            $targets = $t_types
                ? sirey_fetchAll(sirey_query($sql_tg, $t_types, ...$t_params))
                : sirey_fetchAll(sirey_query($sql_tg));

            $prefix = match($prioritas) { 'penting'=>'[⚠️PENTING]', 'darurat'=>'[🚨DARURAT]', default=>'[ℹ️Pengumuman]' };
            $tg_text = "$prefix\n\n*$judul:*\n\n$isi\n\n— SKADACI BOT";
            $sent = 0;
            foreach ($targets as $t) {
                if (!empty($t['telegram_chat_id']) && sendTelegramMessage((int)$t['telegram_chat_id'], $tg_text)) $sent++;
            }

            $h = sirey_execute(
                'INSERT INTO pengumuman_rayhanRP (judul,isi,grup_id,prioritas,target_role,status,tanggal_tayang,pembuat_id,via_telegram)
                 VALUES (?,?,?,?,?,"published",?,?,1)',
                'ssisssi', $judul, $isi, $grup_target, $prioritas, $target_role, $tgl_tayang, $data_admin_rayhanrp['id']
            );
            if ($h >= 1) {
                $new_id = sirey_lastInsertId();
                sirey_execute(
                    'INSERT INTO notifikasi_rayhanRP (tipe,sumber_tipe,sumber_id,grup_id,pesan,jumlah_terkirim) VALUES ("pengumuman","pengumuman",?,?,?,?)',
                    'iisi', $new_id, $grup_target, "[$judul] $isi", $sent
                );
                auditLog($data_admin_rayhanrp['id'],'create_pengumuman','pengumuman',$new_id,['grup_id'=>$grup_target,'terkirim'=>$sent]);
                $pesan = "Pengumuman berhasil dikirim ke <strong>$sent</strong> akun Telegram.";
            } else { $error = 'Gagal menyimpan pengumuman.'; }
        }
    }
}

// ── Query daftar ──
$fStatus = trim((string)($_GET['filter_status'] ?? ''));
$fCari   = trim((string)($_GET['cari'] ?? ''));

$sql    = 'SELECT p.pengumuman_id,p.judul,p.isi,p.prioritas,p.target_role,p.status,p.tanggal_tayang,p.dibuat_pada,
                  COALESCE(n.jumlah_terkirim,0) AS jumlah_terkirim,
                  COALESCE(n.waktu_kirim,p.dibuat_pada) AS waktu_kirim_tg,
                  g.nama_grup,a.nama_lengkap AS pembuat_nama,a.akun_id AS pembuat_id
           FROM pengumuman_rayhanRP p
           LEFT JOIN notifikasi_rayhanRP n ON n.sumber_tipe="pengumuman" AND n.sumber_id=p.pengumuman_id
           LEFT JOIN grup_rayhanRP g ON p.grup_id=g.grup_id
           LEFT JOIN akun_rayhanRP a ON p.pembuat_id=a.akun_id
           WHERE p.status="published"';
$types = ''; $params = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' AND p.pembuat_id=?'; $types .= 'i'; $params[] = $data_admin_rayhanrp['id'];
}
if ($fCari !== '') { $sql .= ' AND p.judul LIKE ?'; $types .= 's'; $params[] = "%$fCari%"; }
$sql .= ' ORDER BY p.dibuat_pada DESC LIMIT 100';

$daftar = $types
    ? sirey_fetchAll(sirey_query($sql, $types, ...$params))
    : sirey_fetchAll(sirey_query($sql));

function prioritasBadge(string $p): string {
    return match($p) {
        'penting' => '<span class="badge bg-warning text-dark">⚠️ Penting</span>',
        'darurat' => '<span class="badge bg-danger">🚨 Darurat</span>',
        default   => '<span class="badge bg-secondary">📋 Biasa</span>',
    };
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-megaphone-fill text-primary me-2"></i>Pengumuman</h2>
    <p><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Guru hanya bisa mengirim ke kelas yang diajar.' : 'Kelola pengumuman resmi via Telegram.'; ?></p>
  </div>
  <?php if ($bisa_buat): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuat">
      <i class="bi bi-plus-lg me-1"></i>Buat Pengumuman
    </button>
  <?php endif; ?>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss mb-3">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo $pesan; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger alert-dismissible fade show mb-3">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <label class="form-label">Cari Judul</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="cari" class="form-control" placeholder="Kata kunci judul…"
                 value="<?php echo htmlspecialchars($fCari); ?>">
        </div>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="pengumuman.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
      <div class="col-auto ms-auto">
        <span class="badge bg-primary fs-6 px-3 py-2"><?php echo count($daftar); ?> pengumuman</span>
      </div>
    </form>
  </div>
</div>

<!-- Tabel Pengumuman -->
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-table me-2"></i>Riwayat Pengumuman</h5>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state">
        <i class="bi bi-megaphone"></i>
        <p>Belum ada pengumuman<?php echo $fCari ? ' yang cocok.' : '.'; ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblPengumuman">
          <thead>
            <tr>
              <th style="width:4px;"></th>
              <th>Judul & Isi</th>
              <th>Target</th>
              <th>Prioritas</th>
              <th class="text-center">Terkirim</th>
              <th>Pembuat</th>
              <th>Tanggal</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $item): ?>
              <?php
                $barColor = match($item['prioritas']) {
                    'penting' => '#d97706',
                    'darurat' => '#dc2626',
                    default   => '#2563eb',
                };
              ?>
              <tr>
                <!-- Indikator prioritas -->
                <td class="p-0">
                  <div style="width:4px; background:<?php echo $barColor; ?>; height:100%; min-height:60px; border-radius:2px;"></div>
                </td>
                <td>
                  <div class="fw-600" style="font-size:14px;"><?php echo htmlspecialchars($item['judul']); ?></div>
                  <div class="text-muted text-truncate" style="max-width:320px; font-size:12px;">
                    <?php echo htmlspecialchars(mb_substr((string)$item['isi'], 0, 120)); ?>…
                  </div>
                  <button class="btn btn-link btn-sm p-0 text-primary mt-1" style="font-size:11px;"
                          onclick="lihatPengumuman(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES); ?>)">
                    Lihat selengkapnya
                  </button>
                </td>
                <td style="font-size:13px;">
                  <div><?php echo htmlspecialchars($item['nama_grup'] ?? 'Semua'); ?></div>
                  <span class="badge bg-light text-dark border" style="font-size:10px;">
                    <?php echo match($item['target_role']) { 'siswa'=>'Siswa', 'guru'=>'Guru', default=>'Semua' }; ?>
                  </span>
                </td>
                <td><?php echo prioritasBadge($item['prioritas']); ?></td>
                <td class="text-center">
                  <div class="fw-600" style="font-size:16px;"><?php echo number_format((int)$item['jumlah_terkirim']); ?></div>
                  <div class="text-muted" style="font-size:10px;">akun</div>
                </td>
                <td style="font-size:12px;"><?php echo htmlspecialchars($item['pembuat_nama'] ?? '—'); ?></td>
                <td style="font-size:12px; color:#64748b; white-space:nowrap;">
                  <?php echo date('d/m/Y', strtotime($item['tanggal_tayang'])); ?>
                  <div style="font-size:10px;"><?php echo formatDatetime($item['waktu_kirim_tg'], 'd/m H:i'); ?></div>
                </td>
                <td class="text-center">
                  <?php
                    $bisa_hapus = in_array($data_admin_rayhanrp['role'],['admin','kurikulum'],true)
                        || (int)$item['pembuat_id'] === (int)$data_admin_rayhanrp['id'];
                  ?>
                  <?php if ($bisa_hapus): ?>
                    <form method="POST" class="m-0"
                          data-confirm="Hapus pengumuman '<?php echo htmlspecialchars($item['judul']); ?>'?">
                      <input type="hidden" name="act" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int)$item['pengumuman_id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($bisa_buat): ?>
<!-- ── MODAL BUAT PENGUMUMAN ── -->
<div class="modal fade" id="modalBuat" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="kirim">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>Buat Pengumuman</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Judul Pengumuman <span class="text-danger">*</span></label>
              <input type="text" name="judul" class="form-control"
                     placeholder="Contoh: Informasi Ujian Tengah Semester" required>
            </div>

            <div class="col-sm-<?php echo $data_admin_rayhanrp['role'] === 'guru' ? '6' : '4'; ?>">
              <label class="form-label">Kelas</label>
              <select name="grup_id" class="form-select"
                      <?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'required' : ''; ?>>
                <option value=""><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Pilih kelas…' : 'Semua Pengguna'; ?></option>
                <?php foreach ($opsi_grup as $g): ?>
                  <option value="<?php echo (int)$g['grup_id']; ?>"><?php echo htmlspecialchars($g['nama_grup']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($data_admin_rayhanrp['role'] !== 'guru'): ?>
            <div class="col-sm-4">
              <label class="form-label">Target Role</label>
              <select name="target_role" class="form-select">
                <option value="all">Semua</option>
                <option value="siswa">Siswa</option>
                <option value="guru">Guru</option>
              </select>
            </div>
            <?php else: ?>
              <input type="hidden" name="target_role" value="siswa">
            <?php endif; ?>

            <div class="col-sm-4">
              <label class="form-label">Prioritas</label>
              <select name="prioritas" class="form-select">
                <option value="biasa">📋 Biasa</option>
                <option value="penting">⚠️ Penting</option>
                <option value="darurat">🚨 Darurat</option>
              </select>
            </div>

            <div class="col-sm-4">
              <label class="form-label">Tanggal Tayang</label>
              <input type="date" name="tanggal_tayang" class="form-control"
                     value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Isi Pengumuman <span class="text-danger">*</span></label>
              <textarea name="pesan" class="form-control" rows="6"
                        placeholder="Tulis isi pengumuman yang akan dikirim ke Telegram…" required></textarea>
              <div class="form-text text-muted">
                <i class="bi bi-telegram me-1"></i>Mendukung format Markdown Telegram: *tebal*, _miring_, dll.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-send me-1"></i>Kirim Sekarang
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Detail Pengumuman -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailJudul">Detail Pengumuman</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 d-flex gap-2 flex-wrap" id="detailMeta"></div>
        <div class="p-3 bg-light rounded" id="detailIsi"
             style="font-size:14px; line-height:1.8; white-space:pre-wrap; max-height:400px; overflow-y:auto;"></div>
        <div class="row g-3 mt-2" id="detailStats"></div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  if ($('#tblPengumuman').length) {
    $('#tblPengumuman').DataTable({
      order: [],
      columnDefs: [
        { orderable: false, targets: [0, -1] }
      ]
    });
  }
});

function lihatPengumuman(item) {
  document.getElementById('detailJudul').textContent = item.judul;
  document.getElementById('detailIsi').textContent   = item.isi;

  const prioritasLabel = { biasa:'📋 Biasa', penting:'⚠️ Penting', darurat:'🚨 Darurat' };
  const targetLabel    = { all:'Semua', siswa:'Siswa', guru:'Guru' };
  document.getElementById('detailMeta').innerHTML = `
    <span class="badge bg-secondary">${prioritasLabel[item.prioritas] || item.prioritas}</span>
    <span class="badge bg-info text-dark">${item.nama_grup || 'Semua Kelas'}</span>
    <span class="badge bg-light text-dark border">${targetLabel[item.target_role] || item.target_role}</span>
    <span class="badge bg-light text-dark border">Oleh: ${item.pembuat_nama || '—'}</span>
    <span class="badge bg-light text-dark border">${item.tanggal_tayang}</span>
  `;
  document.getElementById('detailStats').innerHTML = `
    <div class="col-4 text-center">
      <div style="font-size:28px; font-weight:800; color:#2563eb;">${parseInt(item.jumlah_terkirim||0).toLocaleString()}</div>
      <div style="font-size:12px; color:#64748b;">Akun Terkirim</div>
    </div>
  `;
  new bootstrap.Modal(document.getElementById('modalDetail')).show();
}
</script>

<?php layoutEnd(); ?>