<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

$admin_ajax = [
    'id'   => (int)($_SESSION['admin_id'] ?? 0),
    'role' => (string)($_SESSION['admin_role'] ?? ''),
    'name' => (string)($_SESSION['admin_name'] ?? ''),
];

// ═══ AJAX HANDLERS ═══
$aksi_ajax    = (string)($_GET['action'] ?? '');
$tugas_id_ajax = (int)($_GET['tugas_id'] ?? 0);

if ($aksi_ajax !== '') {
    if ($admin_ajax['id'] <= 0 || !can('view_tugas', $admin_ajax)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    if ($aksi_ajax === 'get_kelas_by_matpel') {
        header('Content-Type: application/json; charset=utf-8');
        $mid = (int)($_GET['matpel_id'] ?? 0);
        $rows = $mid > 0
            ? getTeachingCoverageByMatpel($mid, $admin_ajax['role'] === 'guru' ? $admin_ajax['id'] : null)
            : [];
        echo json_encode(['success' => true, 'items' => $rows]);
        exit;
    }

    if ($aksi_ajax === 'get_siswa_by_matpel') {
        header('Content-Type: application/json; charset=utf-8');
        $mid = (int)($_GET['matpel_id'] ?? 0);
        $rows = ($admin_ajax['role'] === 'guru' && $mid > 0)
            ? getSiswaFromGuruKelas($admin_ajax['id'], $mid)
            : ($mid > 0 ? sirey_fetchAll(sirey_query(
                'SELECT a.akun_id,a.nama_lengkap,g.nama_grup
                 FROM akun_rayhanRP a
                 LEFT JOIN grup_anggota_rayhanRP ga ON ga.akun_id=a.akun_id AND ga.tipe_keanggotaan="utama" AND ga.aktif=1
                 LEFT JOIN grup_rayhanRP g ON ga.grup_id=g.grup_id
                 WHERE a.role="siswa" ORDER BY a.nama_lengkap ASC')) : []);
        echo json_encode(['success' => true, 'items' => $rows]);
        exit;
    }

    if ($tugas_id_ajax <= 0 && !in_array($aksi_ajax, ['get_kelas_by_matpel','get_siswa_by_matpel'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ID tugas tidak valid']);
        exit;
    }

    $tugas_row = sirey_fetch(sirey_query(
        'SELECT tugas_id,judul,grup_id,pembuat_id,status,izin_revisi,tipe_tugas FROM tugas_rayhanRP WHERE tugas_id=?',
        'i', $tugas_id_ajax
    ));
    $bisa_kelola = $tugas_row && ($admin_ajax['role'] !== 'guru' || (int)$tugas_row['pembuat_id'] === $admin_ajax['id']);

    if ($aksi_ajax === 'toggle_status') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $baru = $tugas_row['status'] === 'active' ? 'closed' : 'active';
        sirey_execute('UPDATE tugas_rayhanRP SET status=? WHERE tugas_id=?','si',$baru,$tugas_id_ajax);
        auditLog($admin_ajax['id'],'toggle_status_tugas','tugas',$tugas_id_ajax,['status'=>$baru]);
        echo json_encode(['success'=>true,'status'=>$baru]);
        exit;
    }

    if ($aksi_ajax === 'toggle_revision') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $baru = (int)$tugas_row['izin_revisi'] === 1 ? 0 : 1;
        sirey_execute('UPDATE tugas_rayhanRP SET izin_revisi=? WHERE tugas_id=?','ii',$baru,$tugas_id_ajax);
        auditLog($admin_ajax['id'],'toggle_revision_tugas','tugas',$tugas_id_ajax,['izin_revisi'=>$baru]);
        echo json_encode(['success'=>true,'izin_revisi'=>$baru]);
        exit;
    }

    if ($aksi_ajax === 'get_submissions') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }

        $data_tugas = sirey_fetch(sirey_query(
            'SELECT t.tugas_id,t.tipe_tugas,t.grup_id,g.nama_grup FROM tugas_rayhanRP t
             LEFT JOIN grup_rayhanRP g ON t.grup_id=g.grup_id WHERE t.tugas_id=?','i',$tugas_id_ajax
        ));

        if ($data_tugas['tipe_tugas'] === 'grup' && (int)($data_tugas['grup_id'] ?? 0) > 0) {
            $rekap = getRekapPengumpulanKelas($tugas_id_ajax, (int)$data_tugas['grup_id']);
            $total     = count($rekap);
            $aktif     = count(array_filter($rekap, fn($r) => (int)$r['aktif_di_kelas'] === 1));
            $kumpul    = count(array_filter($rekap, fn($r) => !empty($r['pengumpulan_id'])));
            $belum     = max(0, $aktif - $kumpul);
            echo json_encode([
                'success' => true,
                'stats'   => ['total'=>$total,'aktif'=>$aktif,'kumpul'=>$kumpul,'belum'=>$belum],
                'rows'    => $rekap,
                'tipe'    => 'grup',
            ]);
        } else {
            $siswa = sirey_fetchAll(sirey_query(
                'SELECT a.akun_id,a.nama_lengkap,a.nis_nip FROM akun_rayhanRP a
                 INNER JOIN tugas_perorang_rayhanRP tp ON a.akun_id=tp.akun_id
                 WHERE tp.tugas_id=? AND a.role="siswa" ORDER BY a.nama_lengkap ASC','i',$tugas_id_ajax
            ));
            $kumpul = sirey_fetchAll(sirey_query(
                'SELECT p.akun_id,a.nama_lengkap,a.nis_nip,p.status,p.waktu_kumpul,pn.nilai
                 FROM pengumpulan_rayhanRP p
                 INNER JOIN akun_rayhanRP a ON p.akun_id=a.akun_id
                 LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id=p.pengumpulan_id
                 WHERE p.tugas_id=? ORDER BY a.nama_lengkap ASC','i',$tugas_id_ajax
            ));
            $kumpulIds = array_column($kumpul,'akun_id');
            $belum     = array_filter($siswa, fn($s) => !in_array($s['akun_id'],$kumpulIds,true));
            echo json_encode([
                'success' => true,
                'stats'   => ['total'=>count($siswa),'kumpul'=>count($kumpul),'belum'=>count($belum)],
                'sudah'   => $kumpul,
                'belum'   => array_values($belum),
                'tipe'    => 'perorang',
            ]);
        }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal']);
    exit;
}

// ═══ HALAMAN NORMAL ═══
$judul_halaman_rayhanrp = 'Manajemen Tugas';
$menu_aktif_rayhanrp    = 'tugas';
require_once __DIR__ . '/_layout.php';

if (!can('view_tugas', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_tulis = can('create_tugas', $data_admin_rayhanrp);
$pesan = $error = '';
$id_pembuat = (int)$data_admin_rayhanrp['id'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi        = (string)($_POST['act'] ?? '');
    $id_tugas_post = (int)($_POST['id'] ?? 0);

    if ($aksi === 'delete') {
        $dt = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id=?','i',$id_tugas_post));
        if (!$dt || ($data_admin_rayhanrp['role'] === 'guru' && (int)$dt['pembuat_id'] !== $id_pembuat)) {
            $error = 'Anda tidak berhak menghapus tugas ini.';
        } else {
            $h = safeDeleteTugas($id_tugas_post);
            if ($h['success']) { auditLog($id_pembuat,'delete_tugas','tugas',$id_tugas_post); $pesan = $h['message']; }
            else { $error = $h['message']; }
        }

    } elseif ($aksi === 'bulk_delete') {
        $ids = array_filter(array_map('intval',(array)($_POST['ids'] ?? [])));
        $ok = $fail = 0;
        foreach ($ids as $tid) {
            $dt = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id=?','i',$tid));
            if (!$dt || ($data_admin_rayhanrp['role'] === 'guru' && (int)$dt['pembuat_id'] !== $id_pembuat)) { $fail++; continue; }
            $h = safeDeleteTugas($tid);
            $h['success'] ? $ok++ : $fail++;
        }
        auditLog($id_pembuat,'bulk_delete_tugas','tugas',null,['ids'=>$ids]);
        $pesan = "$ok tugas berhasil dihapus." . ($fail ? " $fail gagal." : '');

    } elseif (!$bisa_tulis) {
        $error = 'Anda tidak memiliki izin mengelola tugas.';
    } else {
        $tipe_tugas    = (string)($_POST['tipe_tugas'] ?? 'grup');
        $grup_id       = (int)($_POST['grup_id'] ?? 0);
        $judul         = trim((string)($_POST['judul'] ?? ''));
        $deskripsi     = trim((string)($_POST['deskripsi'] ?? ''));
        $matpel_id     = (int)($_POST['matpel_id'] ?? 0);
        $tenggat       = trim((string)($_POST['tenggat'] ?? ''));
        $poin_maks     = max(1, min(100, (int)($_POST['poin_maksimal'] ?? 100)));
        $lampiran      = trim((string)($_POST['lampiran_url'] ?? ''));
        $status        = in_array($_POST['status'] ?? '', ['active','closed'], true) ? (string)$_POST['status'] : 'active';
        $recipient_ids = array_filter(array_map('intval',(array)($_POST['recipient_ids'] ?? [])));

        if ($judul === '' || $tenggat === '' || $matpel_id <= 0) {
            $error = 'Mapel, judul, dan tenggat wajib diisi.';
        } elseif ($tipe_tugas === 'grup' && $grup_id <= 0) {
            $error = 'Kelas tujuan wajib dipilih untuk tugas grup.';
        } elseif ($tipe_tugas === 'perorang' && empty($recipient_ids)) {
            $error = 'Pilih minimal satu siswa untuk tugas perorangan.';
        } else {
            if ($aksi === 'create') {
                $h = sirey_execute(
                    'INSERT INTO tugas_rayhanRP (grup_id,judul,deskripsi,matpel_id,tenggat,poin_maksimal,lampiran_url,status,tipe_tugas,pembuat_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    'issisiissi',
                    $tipe_tugas === 'grup' ? $grup_id : null,
                    $judul, $deskripsi ?: null, $matpel_id, $tenggat, $poin_maks,
                    $lampiran ?: null, $status, $tipe_tugas, $id_pembuat
                );
                if ($h >= 1) {
                    $new_id = sirey_lastInsertId();
                    if ($tipe_tugas === 'perorang') {
                        foreach ($recipient_ids as $rid) {
                            sirey_execute('INSERT INTO tugas_perorang_rayhanRP (tugas_id,akun_id) VALUES (?,?)','ii',$new_id,$rid);
                        }
                    }
                    // Notif Telegram jika active
                    if ($status === 'active') {
                        $mp_row  = sirey_fetch(sirey_query('SELECT nama FROM mata_pelajaran_rayhanRP WHERE matpel_id=?','i',$matpel_id));
                        $tgl_fmt = date('d/m/Y H:i', strtotime($tenggat));
                        $tg_msg  = "📝 Tugas Baru!\n\n*$judul*\n".($mp_row ? "Mapel: {$mp_row['nama']}\n" : '')."Deadline: $tgl_fmt";
                        if ($tipe_tugas === 'grup') {
                            $targets = sirey_fetchAll(sirey_query(
                                'SELECT DISTINCT at.telegram_chat_id FROM akun_telegram_rayhanRP at
                                 INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id=ga.akun_id
                                 INNER JOIN akun_rayhanRP a ON a.akun_id=ga.akun_id
                                 WHERE ga.grup_id=? AND ga.aktif=1 AND a.role="siswa"','i',$grup_id
                            ));
                        } else {
                            $placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
                            $targets = sirey_fetchAll(sirey_query(
                                "SELECT DISTINCT at.telegram_chat_id FROM akun_telegram_rayhanRP at
                                 INNER JOIN akun_rayhanRP a ON at.akun_id=a.akun_id
                                 WHERE a.akun_id IN ($placeholders) AND a.role='siswa'",
                                str_repeat('i', count($recipient_ids)), ...$recipient_ids
                            ));
                        }
                        $sent = 0;
                        foreach ($targets as $t) { if (sendTelegramMessage((int)$t['telegram_chat_id'], $tg_msg)) $sent++; }
                        if ($sent > 0) {
                            sirey_execute('INSERT INTO notifikasi_rayhanRP (tipe,grup_id,pesan,jumlah_terkirim) VALUES (?,?,?,?)',
                                'sisi', 'tugas', $tipe_tugas === 'grup' ? $grup_id : null, "Tugas baru: $judul", $sent);
                        }
                    }
                    auditLog($id_pembuat,'create_tugas','tugas',$new_id,['grup_id'=>$grup_id,'tipe'=>$tipe_tugas]);
                    $pesan = 'Tugas berhasil dibuat.';
                } else { $error = 'Gagal menyimpan tugas.'; }

            } elseif ($aksi === 'update') {
                $dt = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id=?','i',$id_tugas_post));
                if (!$dt || ($data_admin_rayhanrp['role'] === 'guru' && (int)$dt['pembuat_id'] !== $id_pembuat)) {
                    $error = 'Anda tidak berhak mengubah tugas ini.';
                } else {
                    sirey_execute(
                        'UPDATE tugas_rayhanRP SET grup_id=?,judul=?,deskripsi=?,matpel_id=?,tenggat=?,poin_maksimal=?,lampiran_url=?,status=? WHERE tugas_id=?',
                        'issisissi',
                        $tipe_tugas === 'grup' ? $grup_id : null, $judul, $deskripsi ?: null,
                        $matpel_id, $tenggat, $poin_maks, $lampiran ?: null, $status, $id_tugas_post
                    );
                    auditLog($id_pembuat,'update_tugas','tugas',$id_tugas_post);
                    $pesan = 'Tugas berhasil diperbarui.';
                }
            }
        }
    }
}

// ── Filter & Query ──
$fJudul  = trim((string)($_GET['filter_judul'] ?? ''));
$fGrup   = (int)($_GET['filter_grup'] ?? 0);
$fStatus = trim((string)($_GET['filter_status'] ?? ''));
$fMapel  = (int)($_GET['filter_matpel'] ?? 0);
$fGuru   = (int)($_GET['filter_guru'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 15;
$offset  = ($page - 1) * $limit;

$sql = 'SELECT t.tugas_id,t.tipe_tugas,t.grup_id,g.nama_grup,t.judul,
               t.matpel_id,mp.kode AS matpel_kode,mp.nama AS matpel_nama,
               t.tenggat,t.poin_maksimal,t.status,t.izin_revisi,t.lampiran_url,
               t.pembuat_id,a.nama_lengkap AS pembuat_nama,
               (SELECT COUNT(*) FROM pengumpulan_rayhanRP WHERE tugas_id=t.tugas_id) AS jml_submit,
               (SELECT COUNT(*) FROM tugas_perorang_rayhanRP WHERE tugas_id=t.tugas_id) AS jml_penerima
        FROM tugas_rayhanRP t
        LEFT JOIN grup_rayhanRP g ON t.grup_id=g.grup_id
        LEFT JOIN akun_rayhanRP a ON t.pembuat_id=a.akun_id
        LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
        WHERE 1=1';
$types = ''; $params = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' AND t.pembuat_id=?'; $types .= 'i'; $params[] = $id_pembuat;
}
if ($fJudul !== '') { $sql .= ' AND t.judul LIKE ?'; $types .= 's'; $params[] = "%$fJudul%"; }
if ($fGrup > 0)     { $sql .= ' AND t.grup_id=?'; $types .= 'i'; $params[] = $fGrup; }
if ($fStatus !== '') { $sql .= ' AND t.status=?'; $types .= 's'; $params[] = $fStatus; }
if ($fMapel > 0)    { $sql .= ' AND t.matpel_id=?'; $types .= 'i'; $params[] = $fMapel; }
if ($fGuru > 0 && in_array($data_admin_rayhanrp['role'],['kepala_sekolah','kurikulum'],true)) {
    $sql .= ' AND t.pembuat_id=?'; $types .= 'i'; $params[] = $fGuru;
}

$total_row = sirey_fetch(sirey_query("SELECT COUNT(*) AS c FROM ($sql) x", $types, ...$params));
$total     = (int)($total_row['c'] ?? 0);
$total_pg  = max(1, (int)ceil($total / $limit));

$sql .= ' ORDER BY t.tenggat DESC LIMIT ? OFFSET ?';
$types .= 'ii'; $params[] = $limit; $params[] = $offset;
$daftar = sirey_fetchAll(sirey_query($sql, $types, ...$params));

// Dropdown data
$daftarGrup   = $data_admin_rayhanrp['role'] === 'guru' ? getGrupDiajarGuru($id_pembuat)
    : sirey_fetchAll(sirey_query('SELECT grup_id,nama_grup FROM grup_rayhanRP WHERE aktif=1 ORDER BY nama_grup ASC'));
$daftarMatpel = $data_admin_rayhanrp['role'] === 'guru' ? getMatpelGuru($id_pembuat)
    : sirey_fetchAll(sirey_query('SELECT matpel_id,kode,nama FROM mata_pelajaran_rayhanRP WHERE aktif=1 ORDER BY nama ASC'));
$daftarGuru   = in_array($data_admin_rayhanrp['role'],['kepala_sekolah','kurikulum'],true)
    ? sirey_fetchAll(sirey_query('SELECT akun_id,nama_lengkap FROM akun_rayhanRP WHERE role="guru" AND aktif=1 ORDER BY nama_lengkap ASC'))
    : [];
$daftarSiswa  = $data_admin_rayhanrp['role'] !== 'guru'
    ? sirey_fetchAll(sirey_query(
        'SELECT a.akun_id,a.nama_lengkap,g.nama_grup FROM akun_rayhanRP a
         LEFT JOIN grup_anggota_rayhanRP ga ON ga.akun_id=a.akun_id AND ga.tipe_keanggotaan="utama" AND ga.aktif=1
         LEFT JOIN grup_rayhanRP g ON ga.grup_id=g.grup_id
         WHERE a.role="siswa" ORDER BY a.nama_lengkap ASC')) : [];

$now = new DateTimeImmutable();

function statusBadge(string $status): string {
    return match($status) {
        'active' => '<span class="badge bg-success">Aktif</span>',
        'closed' => '<span class="badge bg-danger">Non-Aktif</span>',
        default  => '<span class="badge bg-light text-dark">'.$status.'</span>',
    };
}
?>

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-journal-text text-primary me-2"></i>Manajemen Tugas</h2>
    <p><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Guru hanya melihat dan mengelola tugas miliknya sendiri.' : 'Kelola tugas grup dan perorangan.'; ?></p>
  </div>
  <?php if ($bisa_tulis): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuat">
      <i class="bi bi-plus-lg me-1"></i>Buat Tugas
    </button>
  <?php endif; ?>
</div>

<?php if ($pesan !== ''): ?>
  <div class="alert alert-success alert-dismissible fade show alert-auto-dismiss mb-3">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($pesan); ?>
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
      <div class="col-sm-3">
        <label class="form-label">Judul</label>
        <input type="text" name="filter_judul" class="form-control form-control-sm"
               placeholder="Cari judul…" value="<?php echo htmlspecialchars($fJudul); ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label">Kelas</label>
        <select name="filter_grup" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php foreach ($daftarGrup as $g): ?>
            <option value="<?php echo (int)$g['grup_id']; ?>" <?php echo $fGrup===(int)$g['grup_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($g['nama_grup']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label">Status</label>
        <select name="filter_status" class="form-select form-select-sm">
          <option value="">Semua</option>
          <option value="active" <?php echo $fStatus==='active'?'selected':''; ?>>Aktif</option>
          <option value="closed" <?php echo $fStatus==='closed'?'selected':''; ?>>Non-Aktif</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label">Mapel</label>
        <select name="filter_matpel" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php foreach ($daftarMatpel as $mp): ?>
            <option value="<?php echo (int)$mp['matpel_id']; ?>" <?php echo $fMapel===(int)$mp['matpel_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($mp['kode'].' - '.$mp['nama']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($daftarGuru)): ?>
      <div class="col-sm-2">
        <label class="form-label">Guru</label>
        <select name="filter_guru" class="form-select form-select-sm">
          <option value="">Semua</option>
          <?php foreach ($daftarGuru as $g): ?>
            <option value="<?php echo (int)$g['akun_id']; ?>" <?php echo $fGuru===(int)$g['akun_id']?'selected':''; ?>>
              <?php echo htmlspecialchars($g['nama_lengkap']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="tugas.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5>
      <i class="bi bi-table me-2"></i>Daftar Tugas
      <span class="badge bg-primary ms-1"><?php echo $total; ?></span>
    </h5>
    <div class="d-flex gap-2">
      <?php if ($bisa_tulis): ?>
        <button id="btnBulkHapus" class="btn btn-sm btn-danger d-none" onclick="submitBulk()">
          <i class="bi bi-trash me-1"></i>Hapus Terpilih (<span id="selCount">0</span>)
        </button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftar)): ?>
      <div class="empty-state">
        <i class="bi bi-journal-x"></i>
        <p>Belum ada tugas<?php echo ($fJudul || $fGrup || $fStatus || $fMapel) ? ' yang cocok.' : '.'; ?></p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <?php if ($bisa_tulis): ?><th style="width:40px;"><input type="checkbox" class="form-check-input" id="checkAll"></th><?php endif; ?>
              <th>Judul & Mapel</th><th>Tujuan</th><th>Tenggat</th><th class="text-center">Status</th><th class="text-center">Kumpul</th><th class="text-center">Revisi</th><?php if ($bisa_tulis): ?><th class="text-center">Aksi</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $t): ?>
              <?php
                $overdue = strtotime($t['tenggat']) < $now->getTimestamp() && $t['status'] === 'active';
                $milik   = $data_admin_rayhanrp['role'] !== 'guru' || (int)$t['pembuat_id'] === $id_pembuat;
              ?>
              <tr>
                <?php if ($bisa_tulis): ?>
                  <td><input type="checkbox" class="form-check-input tugasCheck" value="<?php echo (int)$t['tugas_id']; ?>" onchange="updateBulk()"></td>
                <?php endif; ?>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($t['judul']); ?></div>
                  <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-dark" style="font-size:10px;"><?php echo htmlspecialchars($t['matpel_kode'] ?? '—'); ?></span>
                    <span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($t['matpel_nama'] ?? '—'); ?></span>
                    <span class="badge bg-light text-dark border ms-1" style="font-size:10px;">
                      <?php echo $t['tipe_tugas'] === 'grup' ? 'Grup' : 'Perorangan'; ?>
                    </span>
                  </div>
                  <?php if (!empty($t['lampiran_url'])): ?>
                    <a href="<?php echo htmlspecialchars($t['lampiran_url']); ?>" target="_blank" class="small text-primary mt-1 d-inline-block">
                      <i class="bi bi-paperclip me-1"></i>Lampiran
                    </a>
                  <?php endif; ?>
                </td>
                <td style="font-size:13px;">
                  <?php if ($t['tipe_tugas'] === 'grup'): ?>
                    <i class="bi bi-mortarboard me-1 text-muted"></i><?php echo htmlspecialchars($t['nama_grup'] ?? '—'); ?>
                  <?php else: ?>
                    <i class="bi bi-people me-1 text-muted"></i><?php echo (int)$t['jml_penerima']; ?> siswa
                  <?php endif; ?>
                  <div class="text-muted" style="font-size:11px;">
                    <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($t['pembuat_nama'] ?? '—'); ?>
                  </div>
                </td>
                <td>
                  <div class="<?php echo $overdue ? 'text-danger fw-600' : ''; ?>" style="font-size:13px;">
                    <?php echo date('d/m/Y', strtotime($t['tenggat'])); ?>
                  </div>
                  <div class="text-muted" style="font-size:11px;"><?php echo date('H:i', strtotime($t['tenggat'])); ?></div>
                  <?php if ($overdue): ?><span class="badge bg-danger" style="font-size:9px;">Lewat</span><?php endif; ?>
                </td>
                <td class="text-center"><?php echo statusBadge($t['status']); ?></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-info"
                          onclick="lihatKumpulan(<?php echo (int)$t['tugas_id']; ?>, '<?php echo htmlspecialchars(addslashes($t['judul'])); ?>')"
                          title="Lihat Pengumpulan">
                    <i class="bi bi-inbox me-1"></i><?php echo (int)$t['jml_submit']; ?>
                  </button>
                </td>
                <td class="text-center">
                  <?php if ($bisa_tulis && $milik): ?>
                    <button id="revBtn<?php echo (int)$t['tugas_id']; ?>"
                            class="btn btn-sm <?php echo (int)$t['izin_revisi'] ? 'btn-success' : 'btn-outline-secondary'; ?>"
                            onclick="toggleRevisi(<?php echo (int)$t['tugas_id']; ?>)"
                            title="Toggle izin revisi">
                      <?php echo (int)$t['izin_revisi'] ? 'Ya' : 'Tidak'; ?>
                    </button>
                  <?php else: ?>
                    <span class="badge <?php echo (int)$t['izin_revisi'] ? 'bg-success' : 'bg-secondary'; ?>">
                      <?php echo (int)$t['izin_revisi'] ? 'Ya' : 'Tidak'; ?>
                    </span>
                  <?php endif; ?>
                </td>
                <?php if ($bisa_tulis): ?>
                  <td class="text-center">
                    <?php if ($milik): ?>
                      <div class="d-flex gap-1 justify-content-center">
                        <button id="stBtn<?php echo (int)$t['tugas_id']; ?>"
                                class="btn btn-sm <?php echo $t['status']==='active'?'btn-success':'btn-outline-secondary'; ?>"
                                onclick="toggleStatus(<?php echo (int)$t['tugas_id']; ?>)"
                                title="Toggle status">
                          <i class="bi bi-toggle-<?php echo $t['status']==='active'?'on':'off'; ?>"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick='openEdit(<?php echo json_encode($t, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                                title="Edit"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="m-0" data-confirm="Hapus tugas '<?php echo htmlspecialchars($t['judul']); ?>'?">
                          <input type="hidden" name="act" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$t['tugas_id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pg > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
          <div class="text-muted small">Halaman <?php echo $page; ?> dari <?php echo $total_pg; ?> (<?php echo $total; ?> tugas)</div>
          <div class="d-flex gap-1">
            <?php
            $qBase = array_merge($_GET, []);
            for ($pg = 1; $pg <= $total_pg; $pg++):
              $qBase['page'] = $pg;
            ?>
              <a href="?<?php echo http_build_query($qBase); ?>"
                 class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <?php echo $pg; ?>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($bisa_tulis): ?>
<!-- ── MODAL BUAT TUGAS ── -->
<div class="modal fade" id="modalBuat" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" id="formBuat">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Buat Tugas Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-3">
              <label class="form-label">Jenis Tugas</label>
              <select name="tipe_tugas" id="tipe_tugas" class="form-select" onchange="toggleTipe()">
                <option value="grup">Grup / Kelas</option>
                <option value="perorang">Perorangan</option>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
              <select name="matpel_id" id="buat_matpel" class="form-select" onchange="loadKelas(this.value)" required>
                <option value="">— Pilih Mapel —</option>
                <?php foreach ($daftarMatpel as $mp): ?>
                  <option value="<?php echo (int)$mp['matpel_id']; ?>"><?php echo htmlspecialchars($mp['kode'].' - '.$mp['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-3" id="fielGrup">
              <label class="form-label">Kelas <span class="text-danger">*</span></label>
              <select name="grup_id" id="buat_grup" class="form-select">
                <option value="">Pilih mapel dahulu</option>
              </select>
            </div>

            <!-- Penerima perorang -->
            <div class="col-12" id="fieldPenerima" style="display:none;">
              <label class="form-label">Penerima Tugas <span class="text-danger">*</span></label>
              <div class="border rounded p-2" style="max-height:200px;overflow-y:auto;" id="listPenerima">
                <?php foreach ($daftarSiswa as $s): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="recipient_ids[]"
                           value="<?php echo (int)$s['akun_id']; ?>" id="s<?php echo (int)$s['akun_id']; ?>">
                    <label class="form-check-label" for="s<?php echo (int)$s['akun_id']; ?>" style="font-size:13px;">
                      <?php echo htmlspecialchars($s['nama_lengkap']); ?>
                      <?php if (!empty($s['nama_grup'])): ?><span class="text-muted">(<?php echo htmlspecialchars($s['nama_grup']); ?>)</span><?php endif; ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="col-sm-8">
              <label class="form-label">Judul Tugas <span class="text-danger">*</span></label>
              <input type="text" name="judul" class="form-control" placeholder="Judul tugas yang jelas" required>
            </div>
            <div class="col-sm-2">
              <label class="form-label">Tenggat <span class="text-danger">*</span></label>
              <input type="datetime-local" name="tenggat" class="form-control" required>
            </div>
            <div class="col-sm-2">
              <label class="form-label">Nilai Maks</label>
              <input type="number" name="poin_maksimal" class="form-control" value="100" min="1" max="100">
            </div>
            <div class="col-12">
              <label class="form-label">Deskripsi</label>
              <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi atau instruksi tugas…"></textarea>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Lampiran URL</label>
              <input type="url" name="lampiran_url" class="form-control" placeholder="https://…">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Buat Tugas</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── MODAL EDIT ── -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="tipe_tugas" id="edit_tipe">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Tugas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-5">
              <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
              <select name="matpel_id" id="edit_matpel" class="form-select" onchange="loadKelasEdit(this.value)" required>
                <option value="">— Pilih Mapel —</option>
                <?php foreach ($daftarMatpel as $mp): ?>
                  <option value="<?php echo (int)$mp['matpel_id']; ?>"><?php echo htmlspecialchars($mp['kode'].' - '.$mp['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-4" id="editFieldGrup">
              <label class="form-label">Kelas</label>
              <select name="grup_id" id="edit_grup" class="form-select"></select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="active">Aktif</option>
                <option value="closed">Non-Aktif</option>
              </select>
            </div>
            <div class="col-sm-8">
              <label class="form-label">Judul <span class="text-danger">*</span></label>
              <input type="text" name="judul" id="edit_judul" class="form-control" required>
            </div>
            <div class="col-sm-2">
              <label class="form-label">Tenggat</label>
              <input type="datetime-local" name="tenggat" id="edit_tenggat" class="form-control" required>
            </div>
            <div class="col-sm-2">
              <label class="form-label">Nilai Maks</label>
              <input type="number" name="poin_maksimal" id="edit_poin" class="form-control" min="1" max="100">
            </div>
            <div class="col-12">
              <label class="form-label">Deskripsi</label>
              <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Lampiran URL</label>
              <input type="url" name="lampiran_url" id="edit_lampiran" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── MODAL PENGUMPULAN ── -->
<div class="modal fade" id="modalKumpulan" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="kumpulanTitle"><i class="bi bi-inbox me-2"></i>Pengumpulan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="kumpulanBody">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Bulk form -->
<form id="formBulk" method="POST" style="display:none;">
  <input type="hidden" name="act" value="bulk_delete">
  <div id="bulkIds"></div>
</form>

<script>
// ── Tipe toggle (buat) ──
function toggleTipe() {
  const tipe = document.getElementById('tipe_tugas').value;
  document.getElementById('fielGrup').style.display    = tipe === 'grup' ? '' : 'none';
  document.getElementById('fieldPenerima').style.display = tipe === 'perorang' ? '' : 'none';
  document.getElementById('buat_grup').required = tipe === 'grup';
}

// ── Load kelas by mapel ──
function loadKelas(matpelId) {
  const sel = document.getElementById('buat_grup');
  sel.innerHTML = '<option>Memuat…</option>';
  if (!matpelId) { sel.innerHTML = '<option value="">Pilih mapel dahulu</option>'; return; }
  const tipe = document.getElementById('tipe_tugas').value;
  if (tipe === 'perorang') {
    loadSiswa(matpelId);
    sel.innerHTML = '<option value="">—</option>';
    return;
  }
  fetch(`./tugas.php?action=get_kelas_by_matpel&matpel_id=${matpelId}`)
    .then(r => r.json())
    .then(d => {
      sel.innerHTML = '<option value="">— Pilih Kelas —</option>';
      (d.items || []).forEach(k => {
        sel.innerHTML += `<option value="${k.grup_id}">${k.nama_grup}</option>`;
      });
    });
}

function loadSiswa(matpelId) {
  const box = document.getElementById('listPenerima');
  box.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
  fetch(`./tugas.php?action=get_siswa_by_matpel&matpel_id=${matpelId}`)
    .then(r => r.json())
    .then(d => {
      if (!d.items || !d.items.length) { box.innerHTML = '<p class="text-muted small">Tidak ada siswa.</p>'; return; }
      box.innerHTML = d.items.map(s =>
        `<div class="form-check"><input class="form-check-input" type="checkbox" name="recipient_ids[]" value="${s.akun_id}" id="rs${s.akun_id}">
         <label class="form-check-label" for="rs${s.akun_id}" style="font-size:13px;">${s.nama_lengkap}${s.nama_grup ? ' <span class="text-muted">('+s.nama_grup+')</span>' : ''}</label></div>`
      ).join('');
    });
}

function loadKelasEdit(matpelId) {
  const sel = document.getElementById('edit_grup');
  sel.innerHTML = '<option>Memuat…</option>';
  if (!matpelId) { sel.innerHTML = '<option value="">—</option>'; return; }
  fetch(`./tugas.php?action=get_kelas_by_matpel&matpel_id=${matpelId}`)
    .then(r => r.json())
    .then(d => {
      sel.innerHTML = '<option value="">— Pilih Kelas —</option>';
      (d.items || []).forEach(k => sel.innerHTML += `<option value="${k.grup_id}">${k.nama_grup}</option>`);
    });
}

// ── Toggle status ──
function toggleStatus(tid) {
  fetch(`./tugas.php?action=toggle_status&tugas_id=${tid}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { Swal.fire({ icon:'error', title:d.message }); return; }
      const btn = document.getElementById(`stBtn${tid}`);
      if (!btn) { location.reload(); return; }
      const isActive = d.status === 'active';
      btn.className = 'btn btn-sm ' + (isActive ? 'btn-success' : 'btn-outline-secondary');
      btn.innerHTML = `<i class="bi bi-toggle-${isActive ? 'on' : 'off'}"></i>`;
      Swal.fire({ icon:'success', title: isActive ? 'Tugas diaktifkan' : 'Tugas ditutup', timer:1200, showConfirmButton:false });
    });
}

// ── Toggle revisi ──
function toggleRevisi(tid) {
  fetch(`./tugas.php?action=toggle_revision&tugas_id=${tid}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { Swal.fire({ icon:'error', title:d.message }); return; }
      const btn = document.getElementById(`revBtn${tid}`);
      if (!btn) { location.reload(); return; }
      const izin = d.izin_revisi === 1;
      btn.className = 'btn btn-sm ' + (izin ? 'btn-success' : 'btn-outline-secondary');
      btn.textContent = izin ? 'Ya' : 'Tidak';
    });
}

// ── Lihat pengumpulan ──
function lihatKumpulan(tid, judul) {
  document.getElementById('kumpulanTitle').innerHTML = `<i class="bi bi-inbox me-2"></i>${judul}`;
  document.getElementById('kumpulanBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  new bootstrap.Modal(document.getElementById('modalKumpulan')).show();
  fetch(`./tugas.php?action=get_submissions&tugas_id=${tid}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success) { document.getElementById('kumpulanBody').innerHTML = '<p class="text-danger">Gagal memuat data.</p>'; return; }
      let html = '';

      // Stats
      if (d.tipe === 'grup') {
        const s = d.stats;
        html += `<div class="row g-2 mb-3">
          <div class="col-4"><div class="stat-card p-3 text-center"><div class="stat-value">${s.total}</div><div class="stat-label">Total Siswa</div></div></div>
          <div class="col-4"><div class="stat-card p-3 text-center" style="border-color:#059669;"><div class="stat-value text-success">${s.kumpul}</div><div class="stat-label">Sudah Kumpul</div></div></div>
          <div class="col-4"><div class="stat-card p-3 text-center" style="border-color:#dc2626;"><div class="stat-value text-danger">${s.belum}</div><div class="stat-label">Belum Kumpul</div></div></div>
        </div>`;

        // Tabel rekap
        html += '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>NIS</th><th>Nama</th><th>Status Kelas</th><th>Status Tugas</th><th>Waktu</th><th>Nilai</th></tr></thead><tbody>';
        (d.rows || []).forEach(r => {
          const st = r.status_rekap === 'Sudah Mengumpulkan' ? 'bg-success' : (r.status_rekap === 'Non-aktif' ? 'bg-secondary' : 'bg-danger');
          html += `<tr><td><code>${r.nis_nip}</code></td><td>${r.nama_lengkap}</td>
            <td><span class="badge ${parseInt(r.aktif_di_kelas)?'bg-success':'bg-secondary'}">${parseInt(r.aktif_di_kelas)?'Aktif':'Non-aktif'}</span></td>
            <td><span class="badge ${st}">${r.status_rekap}</span></td>
            <td class="text-muted small">${r.waktu_kumpul||'—'}</td>
            <td>${r.nilai!==null?r.nilai:'—'}</td></tr>`;
        });
        html += '</tbody></table></div>';
      } else {
        const s = d.stats;
        html += `<div class="row g-2 mb-3">
          <div class="col-4"><div class="stat-card p-3 text-center"><div class="stat-value">${s.total}</div><div class="stat-label">Total Siswa</div></div></div>
          <div class="col-4"><div class="stat-card p-3 text-center" style="border-color:#059669;"><div class="stat-value text-success">${s.kumpul}</div><div class="stat-label">Sudah Kumpul</div></div></div>
          <div class="col-4"><div class="stat-card p-3 text-center" style="border-color:#dc2626;"><div class="stat-value text-danger">${s.belum}</div><div class="stat-label">Belum Kumpul</div></div></div>
        </div>`;

        if (d.belum && d.belum.length) {
          html += '<h6 class="text-danger mb-2">⏳ Belum Mengumpulkan</h6><ul class="list-unstyled mb-3">';
          d.belum.forEach(s => { html += `<li class="small text-muted"><i class="bi bi-dash-circle text-danger me-1"></i>${s.nama_lengkap} (${s.nis_nip})</li>`; });
          html += '</ul>';
        }
        if (d.sudah && d.sudah.length) {
          html += '<h6 class="text-success mb-2">✅ Sudah Mengumpulkan</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Nama</th><th>Status</th><th>Waktu</th><th>Nilai</th></tr></thead><tbody>';
          d.sudah.forEach(s => { html += `<tr><td>${s.nama_lengkap}</td><td><span class="badge bg-success">${s.status}</span></td><td class="small text-muted">${s.waktu_kumpul}</td><td>${s.nilai!==null?s.nilai:'—'}</td></tr>`; });
          html += '</tbody></table></div>';
        }
      }
      document.getElementById('kumpulanBody').innerHTML = html;
    })
    .catch(() => { document.getElementById('kumpulanBody').innerHTML = '<p class="text-danger text-center">Gagal memuat data.</p>'; });
}

// ── Edit modal ──
function openEdit(t) {
  document.getElementById('edit_id').value        = t.tugas_id;
  document.getElementById('edit_tipe').value      = t.tipe_tugas;
  document.getElementById('edit_judul').value     = t.judul;
  document.getElementById('edit_status').value    = t.status;
  document.getElementById('edit_poin').value      = t.poin_maksimal;
  document.getElementById('edit_deskripsi').value = t.deskripsi || '';
  document.getElementById('edit_lampiran').value  = t.lampiran_url || '';
  document.getElementById('edit_tenggat').value   = (t.tenggat || '').replace(' ','T').substring(0,16);
  document.getElementById('edit_matpel').value    = t.matpel_id || '';

  const gruField = document.getElementById('editFieldGrup');
  gruField.style.display = t.tipe_tugas === 'grup' ? '' : 'none';

  if (t.matpel_id) {
    fetch(`./tugas.php?action=get_kelas_by_matpel&matpel_id=${t.matpel_id}`)
      .then(r => r.json())
      .then(d => {
        const sel = document.getElementById('edit_grup');
        sel.innerHTML = '<option value="">— Pilih Kelas —</option>';
        (d.items || []).forEach(k => {
          sel.innerHTML += `<option value="${k.grup_id}" ${k.grup_id==t.grup_id?'selected':''}>${k.nama_grup}</option>`;
        });
      });
  }
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// ── Bulk ──
<?php if ($bisa_tulis): ?>
document.getElementById('checkAll')?.addEventListener('change', function () {
  document.querySelectorAll('.tugasCheck').forEach(cb => cb.checked = this.checked);
  updateBulk();
});
function updateBulk() {
  const n = document.querySelectorAll('.tugasCheck:checked').length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('btnBulkHapus').classList.toggle('d-none', n === 0);
}
function submitBulk() {
  const ids = [...document.querySelectorAll('.tugasCheck:checked')].map(cb => cb.value);
  if (!ids.length) return;
  Swal.fire({ title:`Hapus ${ids.length} tugas?`, text:'Semua data pengumpulan dan penilaian ikut terhapus!', icon:'warning',
    showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    const c = document.getElementById('bulkIds');
    c.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
    document.getElementById('formBulk').submit();
  });
}
<?php endif; ?>
</script>

<?php layoutEnd(); ?>