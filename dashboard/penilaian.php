<?php
declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// ═══ AJAX ═══
$aksi_ajax = (string)($_GET['action'] ?? '');
if ($aksi_ajax !== '') {
    startSession();
    $guru_id  = (int)($_SESSION['admin_id'] ?? 0);
    $role_ses = (string)($_SESSION['admin_role'] ?? '');

    if ($aksi_ajax === 'get_pengumpulan') {
        header('Content-Type: application/json; charset=utf-8');
        $tid = (int)($_GET['tugas_id'] ?? 0);
        $data_tugas = sirey_fetch(sirey_query(
            'SELECT t.tugas_id,t.judul,t.tenggat,t.poin_maksimal,mp.nama AS matpel,g.nama_grup
             FROM tugas_rayhanRP t LEFT JOIN grup_rayhanRP g ON t.grup_id=g.grup_id
             LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
             WHERE t.tugas_id=?','i',$tid
        ));
        if (!$data_tugas) { echo json_encode(['success'=>false,'message'=>'Tugas tidak ditemukan']); exit; }

        $rows = sirey_fetchAll(sirey_query(
            'SELECT p.pengumpulan_id,p.akun_id,a.nama_lengkap,a.nis_nip,p.status,p.waktu_kumpul,p.via,
                    p.teks_jawaban,p.file_path,p.file_nama_asli,p.link_jawaban,
                    pn.penilaian_id,pn.nilai,pn.status_lulus,pn.catatan_guru,pn.dinilai_pada
             FROM pengumpulan_rayhanRP p
             INNER JOIN akun_rayhanRP a ON p.akun_id=a.akun_id
             LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id=p.pengumpulan_id
             WHERE p.tugas_id=? ORDER BY a.nama_lengkap ASC','i',$tid
        ));

        echo json_encode(['success'=>true,'tugas'=>$data_tugas,'rows'=>$rows]);
        exit;
    }

    if ($aksi_ajax === 'simpan_nilai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if ($role_ses !== 'guru') { echo json_encode(['success'=>false,'message'=>'Hanya guru yang dapat menilai.']); exit; }

        $pid       = (int)($_POST['pengumpulan_id'] ?? 0);
        $nilai     = (float)($_POST['nilai'] ?? 0);
        $st_lulus  = (string)($_POST['status_lulus'] ?? '');
        $catatan   = trim((string)($_POST['catatan_guru'] ?? ''));

        if ($pid <= 0) { echo json_encode(['success'=>false,'message'=>'ID tidak valid.']); exit; }
        
        if (!in_array($st_lulus, ['lulus','tidak_lulus','revisi'])) {
            echo json_encode(['success'=>false,'message'=>'Pilih status penilaian terlebih dahulu.']); exit;
        }

        $dp = sirey_fetch(sirey_query(
            'SELECT p.tugas_id,t.poin_maksimal FROM pengumpulan_rayhanRP p
             INNER JOIN tugas_rayhanRP t ON p.tugas_id=t.tugas_id WHERE p.pengumpulan_id=?','i',$pid
        ));
        if (!$dp) { echo json_encode(['success'=>false,'message'=>'Pengumpulan tidak ditemukan.']); exit; }

        $poin_max = (int)$dp['poin_maksimal'];
        if ($nilai < 0 || $nilai > $poin_max) {
            echo json_encode(['success'=>false,'message'=>"Nilai harus 0 – $poin_max."]); exit;
        }

        $existing = sirey_fetch(sirey_query('SELECT penilaian_id FROM penilaian_rayhanRP WHERE pengumpulan_id=?','i',$pid));
        if ($existing) {
            sirey_execute('UPDATE penilaian_rayhanRP SET nilai=?,status_lulus=?,catatan_guru=?,dinilai_oleh=?,dinilai_pada=NOW() WHERE penilaian_id=?',
                'dssii',$nilai,$st_lulus,$catatan,$guru_id,(int)$existing['penilaian_id']);
        } else {
            sirey_execute('INSERT INTO penilaian_rayhanRP (pengumpulan_id,dinilai_oleh,nilai,status_lulus,catatan_guru,dinilai_pada) VALUES (?,?,?,?,?,NOW())',
                'iidss',$pid,$guru_id,$nilai,$st_lulus,$catatan);
        }
        sirey_execute("UPDATE pengumpulan_rayhanRP SET status='graded' WHERE pengumpulan_id=?",'i',$pid);

        // Kirim notif Telegram ke siswa
        $tg = sirey_fetch(sirey_query(
            'SELECT at.telegram_chat_id,t.judul FROM pengumpulan_rayhanRP p
             JOIN akun_telegram_rayhanRP at ON at.akun_id=p.akun_id
             JOIN tugas_rayhanRP t ON p.tugas_id=t.tugas_id WHERE p.pengumpulan_id=?','i',$pid
        ));
        if ($tg && $tg['telegram_chat_id']) {
            $em = match($st_lulus) { 'lulus'=>'✅ Lulus','revisi'=>'✏️ Revisi',default=>'❌ Tidak Lulus' };
            $msg = "🎉 *Tugas Anda Sudah Dinilai!*\n\nTugas: *{$tg['judul']}*\nNilai: *$nilai*\nStatus: $em"
                  .($catatan ? "\nCatatan:\n_$catatan _" : '')
                  .($st_lulus === 'revisi' ? "\n\n📝 *Silakan perbaiki dan resubmit.*" : '')
                  ."\n\n— Bot SiRey";
            sendTelegramMessage((int)$tg['telegram_chat_id'], $msg);
        }
        echo json_encode(['success'=>true,'message'=>'Nilai tersimpan.']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal']);
    exit;
}

// ═══ PAGE ═══
startSession();
$data_admin = requireAdminSession('login.php');
$judul_halaman_rayhanrp = 'Penilaian Tugas';
$menu_aktif_rayhanrp    = 'penilaian';
require_once __DIR__ . '/_layout.php';

$id_guru  = (int)($data_admin['id'] ?? 0);
$role_now = (string)($data_admin['role'] ?? '');

$where = $role_now === 'guru' ? 'WHERE t.pembuat_id=?' : '';
$args  = $role_now === 'guru' ? ['i', $id_guru] : ['', null];

$daftar_tugas = sirey_fetchAll(sirey_query(
    "SELECT t.tugas_id,t.judul,t.tipe_tugas,t.grup_id,mp.nama AS matpel_nama,t.tenggat,t.poin_maksimal,t.status,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id=t.tugas_id) AS total_kumpul,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id=t.tugas_id
             AND NOT EXISTS (SELECT 1 FROM penilaian_rayhanRP pn WHERE pn.pengumpulan_id=p.pengumpulan_id AND pn.nilai IS NOT NULL)) AS belum_dinilai,
            (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id=t.tugas_id
             AND EXISTS (SELECT 1 FROM penilaian_rayhanRP pn WHERE pn.pengumpulan_id=p.pengumpulan_id AND pn.nilai IS NOT NULL)) AS sudah_dinilai,
            (SELECT AVG(pn.nilai) FROM pengumpulan_rayhanRP p LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id=p.pengumpulan_id
             WHERE p.tugas_id=t.tugas_id AND pn.nilai IS NOT NULL) AS rata_nilai,
            CASE WHEN t.tipe_tugas='grup'
                 THEN (SELECT COUNT(*) FROM grup_anggota_rayhanRP ga INNER JOIN akun_rayhanRP aa ON ga.akun_id=aa.akun_id
                       WHERE ga.grup_id=t.grup_id AND ga.aktif=1 AND aa.role='siswa')
                 ELSE (SELECT COUNT(*) FROM tugas_perorang_rayhanRP WHERE tugas_id=t.tugas_id)
            END AS total_siswa
     FROM tugas_rayhanRP t LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
     $where ORDER BY t.tenggat DESC",
    ...(array_filter($args, fn($v) => $v !== null) ?: [''])
));

$total_belum   = (int)array_sum(array_column($daftar_tugas,'belum_dinilai'));
$total_sudah   = (int)array_sum(array_column($daftar_tugas,'sudah_dinilai'));
$total_tugas   = count($daftar_tugas);
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-star-fill text-warning me-2"></i>Penilaian Tugas</h2>
    <p>Berikan nilai, catatan, dan feedback untuk setiap pengumpulan murid.</p>
  </div>
</div>

<!-- Statistik -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;"><i class="bi bi-journal-text" style="color:#2563eb;"></i></div>
      <div><div class="stat-value"><?php echo $total_tugas; ?></div><div class="stat-label">Total Tugas</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;"><i class="bi bi-hourglass-split" style="color:#d97706;"></i></div>
      <div><div class="stat-value"><?php echo $total_belum; ?></div><div class="stat-label">Belum Dinilai</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#d1fae5;"><i class="bi bi-check-circle-fill" style="color:#059669;"></i></div>
      <div><div class="stat-value"><?php echo $total_sudah; ?></div><div class="stat-label">Sudah Dinilai</div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;"><i class="bi bi-bar-chart-fill" style="color:#7c3aed;"></i></div>
      <div>
        <div class="stat-value">
          <?php echo ($total_sudah + $total_belum) > 0 ? round(($total_sudah / ($total_sudah + $total_belum)) * 100) : 0; ?>%
        </div>
        <div class="stat-label">Progress Penilaian</div>
      </div>
    </div>
  </div>
</div>

<!-- Daftar Tugas -->
<div class="card">
  <div class="card-header"><h5><i class="bi bi-table me-2"></i>Daftar Tugas</h5></div>
  <div class="card-body p-0">
    <?php if (empty($daftar_tugas)): ?>
      <div class="empty-state"><i class="bi bi-star"></i><p>Belum ada tugas.</p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblPenilaian">
          <thead>
            <tr>
              <th>Tugas</th><th>Mapel</th><th>Tenggat</th>
              <th class="text-center">Pengumpulan</th>
              <th class="text-center">Progress Nilai</th>
              <th class="text-center">Rata-rata</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar_tugas as $t): ?>
              <?php
                $pct     = $t['total_siswa'] > 0 ? round(($t['sudah_dinilai'] / $t['total_siswa']) * 100) : 0;
                $barCol  = $pct >= 100 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');
                $belumN  = (int)$t['belum_dinilai'];
              ?>
              <tr>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($t['judul']); ?></div>
                  <span class="badge <?php echo $t['status']==='active'?'bg-success':'bg-danger'; ?>" style="font-size:10px;">
                    <?php echo $t['status']==='active'?'Aktif':'Non-Aktif'; ?>
                  </span>
                </td>
                <td class="text-muted" style="font-size:13px;"><?php echo htmlspecialchars($t['matpel_nama'] ?? '—'); ?></td>
                <td style="font-size:12px; color:<?php echo strtotime($t['tenggat']) < time() ? '#dc2626' : 'inherit'; ?>;">
                  <?php echo date('d/m/Y H:i', strtotime($t['tenggat'])); ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-primary"><?php echo (int)$t['total_kumpul']; ?> / <?php echo (int)$t['total_siswa']; ?></span>
                  <?php if ($belumN > 0): ?>
                    <div class="text-danger" style="font-size:10px; margin-top:2px;"><?php echo $belumN; ?> belum dinilai</div>
                  <?php endif; ?>
                </td>
                <td style="min-width:120px;">
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px; border-radius:4px;">
                      <div class="progress-bar" style="width:<?php echo $pct; ?>%; background:<?php echo $barCol; ?>; border-radius:4px;"></div>
                    </div>
                    <span style="font-size:11px; color:<?php echo $barCol; ?>; font-weight:700; min-width:32px;"><?php echo $pct; ?>%</span>
                  </div>
                </td>
                <td class="text-center">
                  <?php if ($t['rata_nilai'] !== null): ?>
                    <span class="fw-600" style="font-size:14px; color:#0f172a;">
                      <?php echo number_format((float)$t['rata_nilai'], 1); ?>
                    </span>
                    <span class="text-muted" style="font-size:11px;">/<?php echo (int)$t['poin_maksimal']; ?></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <button class="btn btn-sm btn-primary"
                          onclick="bukaTugas(<?php echo (int)$t['tugas_id']; ?>, '<?php echo htmlspecialchars(addslashes($t['judul'])); ?>', <?php echo (int)$t['poin_maksimal']; ?>)">
                    <i class="bi bi-pencil-square me-1"></i>Nilai
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── MODAL PENILAIAN ── -->
<div class="modal fade" id="modalPenilaian" tabindex="-1">
  <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="penilaianTitle">Penilaian</h5>
          <div class="text-muted small" id="penilaianSub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="penilaianBody">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
      <?php if ($role_now === 'guru'): ?>
        <div class="modal-footer d-flex justify-content-end gap-2 border-top">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="button" class="btn btn-success" id="btnSimpanSemua" onclick="simpanSemua()">
            <i class="bi bi-save me-1"></i>Simpan Semua Nilai
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── MODAL TEKS JAWABAN ── -->
<div class="modal fade" id="modalTeks" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-text me-2"></i>Jawaban: <span id="teksNama"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="teksIsi" class="p-3 bg-light rounded" style="font-size:14px; line-height:1.7; white-space:pre-wrap; max-height:500px; overflow-y:auto;"></div>
      </div>
    </div>
  </div>
</div>



<style>
.fw-600 { font-weight: 600; }
.nilai-bar { height: 4px; border-radius: 2px; background: #e5e7eb; margin-top: 4px; }
.nilai-bar-fill { height: 100%; border-radius: 2px; transition: width .3s; }
</style>

<script>
let currentPoinMax = 100;

$(document).ready(function () {
  if ($('#tblPenilaian').length) {
    $('#tblPenilaian').DataTable({ order:[[2,'asc']], columnDefs:[{orderable:false,targets:-1}] });
  }
});

// ── Buka tugas untuk dinilai ──
function bukaTugas(tid, judul, poinMax) {
  currentPoinMax = poinMax;
  document.getElementById('penilaianTitle').textContent = judul;
  document.getElementById('penilaianSub').textContent   = 'Nilai Maksimal: ' + poinMax;
  document.getElementById('penilaianBody').innerHTML    = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  new bootstrap.Modal(document.getElementById('modalPenilaian')).show();

  fetch(`./penilaian.php?action=get_pengumpulan&tugas_id=${tid}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { document.getElementById('penilaianBody').innerHTML = '<p class="text-danger text-center py-4">Gagal memuat data.</p>'; return; }
      renderPenilaian(d.tugas, d.rows, poinMax);
    })
    .catch(() => { document.getElementById('penilaianBody').innerHTML = '<p class="text-danger text-center py-4">Error memuat data.</p>'; });
}

function renderPenilaian(tugas, rows, poinMax) {
  const isGuru = <?php echo $role_now === 'guru' ? 'true' : 'false'; ?>;
  const sudah  = rows.filter(r => r.nilai !== null).length;
  const belum  = rows.length - sudah;

  let html = `<div class="px-4 pt-3 pb-2 border-bottom bg-light">
    <div class="row g-3">
      <div class="col-auto"><span class="badge bg-info text-dark">📚 ${tugas.matpel||'—'}</span></div>
      <div class="col-auto"><span class="badge bg-secondary">🎓 ${tugas.nama_grup||'—'}</span></div>
      <div class="col-auto"><span class="badge bg-warning text-dark">💯 Maks: ${poinMax}</span></div>
      <div class="col-auto"><span class="badge bg-${belum>0?'danger':'success'}">${sudah}/${rows.length} dinilai</span></div>
    </div>
  </div>`;

  if (!rows.length) {
    html += '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>Belum ada yang mengumpulkan.</div>';
  } else {
    html += '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Murid</th><th>Status</th><th>Waktu</th><th>Jawaban</th>';
    if (isGuru) html += '<th style="min-width:280px;">Nilai & Catatan</th><th class="text-center">Simpan</th>';
    else         html += '<th>Nilai</th>';
    html += '</tr></thead><tbody>';

    rows.forEach(r => {
      const st = { dikumpulkan:'✅ Tepat', terlambat:'⚠️ Terlambat', graded:'✔️ Dinilai', belum:'❌ Belum' };
      const stLabel = st[r.status] || r.status;
      const stBadge = r.status==='dikumpulkan'?'bg-success':(r.status==='terlambat'?'bg-warning text-dark':(r.status==='graded'?'bg-info text-dark':'bg-danger'));
      const nilaiVal = r.nilai !== null ? r.nilai : '';
      const catatanVal = r.catatan_guru || '';
      const statusLulus = r.status_lulus || '';
      const pct = nilaiVal !== '' ? Math.round(nilaiVal / poinMax * 100) : 0;
      const barCol = pct >= 70 ? '#059669' : (pct >= 50 ? '#d97706' : '#dc2626');

      let jawabanHtml = '—';
      if (r.file_path) jawabanHtml = `<a href="../${r.file_path}" target="_blank" class="btn btn-xs btn-outline-primary btn-sm"><i class="bi bi-paperclip"></i> File</a>`;
      else if (r.link_jawaban) jawabanHtml = `<a href="${r.link_jawaban}" target="_blank" class="btn btn-xs btn-outline-info btn-sm"><i class="bi bi-link-45deg"></i> Link</a>`;
      else if (r.teks_jawaban) jawabanHtml = `<button class="btn btn-sm btn-outline-secondary btn-lihat-teks" data-teks-id="${r.pengumpulan_id}" data-teks="${btoa(unescape(encodeURIComponent(r.teks_jawaban)))}" data-nama="${btoa(unescape(encodeURIComponent(r.nama_lengkap)))}"><i class="bi bi-chat-text"></i> Lihat</button>`;

      html += `<tr id="row${r.pengumpulan_id}">
        <td><div class="fw-600" style="font-size:13px;">${r.nama_lengkap}</div><div class="text-muted" style="font-size:11px;">${r.nis_nip}</div></td>
        <td><span class="badge ${stBadge}" style="font-size:11px;">${stLabel}</span><div class="text-muted" style="font-size:10px;">via ${r.via||'—'}</div></td>
        <td class="text-muted" style="font-size:11px; white-space:nowrap;">${r.waktu_kumpul||'—'}</td>
        <td>${jawabanHtml}</td>`;

      if (isGuru) {
        html += `<td>
          <input type="number" id="nilai${r.pengumpulan_id}" value="${nilaiVal}" min="0" max="${poinMax}" step="0.01"
                 class="form-control form-control-sm mb-1" placeholder="0 – ${poinMax}"
                 oninput="updateBar(${r.pengumpulan_id}, this.value, ${poinMax})">
          <div class="nilai-bar"><div class="nilai-bar-fill" id="bar${r.pengumpulan_id}" style="width:${pct}%;background:${barCol};"></div></div>
          <select id="lulus${r.pengumpulan_id}" class="form-select form-select-sm mt-1">
            <option value="">⏳ (Belum Dinilai)</option>
            <option value="lulus" ${statusLulus==='lulus'?'selected':''}>✅ Lulus</option>
            <option value="tidak_lulus" ${statusLulus==='tidak_lulus'?'selected':''}>❌ Tidak Lulus</option>
            <option value="revisi" ${statusLulus==='revisi'?'selected':''}>✏️ Revisi</option>
          </select>
          <textarea id="catatan${r.pengumpulan_id}" class="form-control form-control-sm mt-1" rows="2" placeholder="Catatan guru…">${catatanVal}</textarea>
        </td>
        <td class="text-center">
          <button class="btn btn-sm btn-success" onclick="simpanSatu(${r.pengumpulan_id})">
            <i class="bi bi-save"></i>
          </button>
          <div id="notif${r.pengumpulan_id}" class="text-muted mt-1" style="font-size:10px; min-height:14px;"></div>
        </td>`;
      } else {
        html += `<td>${nilaiVal !== '' ? `<span class="fw-600">${nilaiVal}/${poinMax}</span>` : '—'}</td>`;
      }
      html += '</tr>';
    });
    html += '</tbody></table></div>';
  }
  document.getElementById('penilaianBody').innerHTML = html;
  
  // Attach event listeners for text answer buttons
  document.querySelectorAll('.btn-lihat-teks').forEach(btn => {
    btn.addEventListener('click', function() {
      const teks = decodeURIComponent(escape(atob(this.dataset.teks)));
      const nama = decodeURIComponent(escape(atob(this.dataset.nama)));
      lihatTeks(teks, nama);
    });
  });
}

function updateBar(pid, val, max) {
  const pct = Math.min(100, Math.max(0, Math.round((parseFloat(val)||0) / max * 100)));
  const bar = document.getElementById('bar'+pid);
  if (!bar) return;
  bar.style.width = pct + '%';
  bar.style.background = pct >= 70 ? '#059669' : (pct >= 50 ? '#d97706' : '#dc2626');
}

function simpanSatu(pid) {
  const nilai   = document.getElementById('nilai'+pid)?.value;
  const lulus   = document.getElementById('lulus'+pid)?.value;
  const catatan = document.getElementById('catatan'+pid)?.value;
  const notif   = document.getElementById('notif'+pid);
  if (nilai === undefined || nilai === '') { if(notif) notif.textContent='⚠ Isi nilai'; return; }
  if (!lulus) { if(notif) notif.textContent='⚠ Pilih status'; return; }
  if(notif) notif.textContent = '⏳…';
  const fd = new FormData();
  fd.append('pengumpulan_id', pid);
  fd.append('nilai', nilai);
  fd.append('status_lulus', lulus);
  fd.append('catatan_guru', catatan || '');
  fetch('./penilaian.php?action=simpan_nilai', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      if(notif) { notif.textContent = d.success ? '✅' : '❌'; setTimeout(()=>{ notif.textContent=''; }, 2000); }
    })
    .catch(()=>{ if(notif) notif.textContent='❌'; });
}

function simpanSemua() {
  const inputs = document.querySelectorAll('[id^="nilai"]');
  if (!inputs.length) { Swal.fire({ icon:'info', title:'Tidak ada data', timer:1500, showConfirmButton:false }); return; }
  const items = [];
  inputs.forEach(inp => {
    const pid = inp.id.replace('nilai','');
    if (!inp.value) return;
    const statusSelect = document.getElementById('lulus'+pid);
    if (!statusSelect?.value) return;
    items.push({ pid, nilai:inp.value, lulus:statusSelect.value, catatan:document.getElementById('catatan'+pid)?.value||'' });
  });
  if (!items.length) { Swal.fire({ icon:'warning', title:'Isi minimal satu nilai dan pilih status untuk setiap siswa' }); return; }
  Swal.fire({ title:`Simpan ${items.length} penilaian?`, icon:'question', showCancelButton:true, confirmButtonText:'Ya, Simpan', cancelButtonText:'Batal' })
    .then(async r => {
      if (!r.isConfirmed) return;
      const btn = document.getElementById('btnSimpanSemua');
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan…';
      let ok = 0;
      for (const it of items) {
        const fd = new FormData();
        fd.append('pengumpulan_id', it.pid); fd.append('nilai', it.nilai);
        fd.append('status_lulus', it.lulus); fd.append('catatan_guru', it.catatan);
        const resp = await fetch('./penilaian.php?action=simpan_nilai', { method:'POST', body:fd });
        const d = await resp.json();
        if (d.success) ok++;
      }
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-save me-1"></i>Simpan Semua Nilai';
      Swal.fire({ icon:'success', title:`${ok} penilaian tersimpan!`, timer:2000, showConfirmButton:false });
    });
}

function lihatTeks(teks, nama) {
  document.getElementById('teksNama').textContent = nama;
  document.getElementById('teksIsi').textContent  = teks;
  new bootstrap.Modal(document.getElementById('modalTeks')).show();
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    ['modalPenilaian','modalTeks'].forEach(id => {
      const el = document.getElementById(id);
      if (el) bootstrap.Modal.getInstance(el)?.hide();
    });
  }
});
</script>

<?php layoutEnd(); ?>