<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

$admin = [
    'id'   => (int)($_SESSION['admin_id'] ?? 0),
    'role' => (string)($_SESSION['admin_role'] ?? ''),
    'name' => (string)($_SESSION['admin_name'] ?? ''),
];

// ═══ AJAX HANDLERS ═══
$aksi_ajax = (string)($_GET['action'] ?? '');
$id_grup   = (int)($_GET['grup_id'] ?? 0);

if ($aksi_ajax !== '') {
    if ($admin['id'] <= 0 || !can('view_grup', $admin)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    $bisa_kelola = can('update_grup', $admin);

    if ($aksi_ajax === 'get_members') {
        header('Content-Type: text/html; charset=utf-8');
        $cari = trim((string)($_GET['search'] ?? ''));
        $sql  = "SELECT a.akun_id,a.nis_nip,a.nama_lengkap,a.role,a.jenis_kelamin
                 FROM akun_rayhanrp a JOIN grup_anggota_rayhanrp ga ON a.akun_id=ga.akun_id
                 WHERE ga.grup_id=?";
        $types = 'i'; $params = [$id_grup];
        if ($cari !== '') {
            $sql .= " AND (a.nis_nip LIKE ? OR a.nama_lengkap LIKE ?)";
            $types .= 'ss'; $params[] = "%$cari%"; $params[] = "%$cari%";
        }
        $sql .= " ORDER BY a.nama_lengkap ASC";
        $rows = sirey_fetchAll(sirey_query($sql, $types, ...$params));
        include __DIR__ . '/partials/_member_list.php';
        exit;
    }

    if ($aksi_ajax === 'get_available_users') {
        header('Content-Type: text/html; charset=utf-8');
        if (!$bisa_kelola) { echo ''; exit; }
        $rows = sirey_fetchAll(sirey_query(
            "SELECT a.akun_id,a.nis_nip,a.nama_lengkap FROM akun_rayhanrp a
             WHERE a.akun_id NOT IN (SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id=?)
             ORDER BY a.nama_lengkap ASC", 'i', $id_grup
        ));
        if (empty($rows)) { echo '<p class="text-muted fst-italic small">Semua pengguna sudah menjadi anggota grup.</p>'; exit; }
        echo '<select id="addMemberSelect" class="form-select form-select-sm"><option value="">— Pilih Anggota —</option>';
        foreach ($rows as $u) {
            echo '<option value="'.(int)$u['akun_id'].'">'.htmlspecialchars($u['nis_nip'].' - '.$u['nama_lengkap']).'</option>';
        }
        echo '</select>';
        exit;
    }

    if ($aksi_ajax === 'get_jadwal') {
        header('Content-Type: text/html; charset=utf-8');
        $rows = sirey_fetchAll(sirey_query(
            "SELECT gm.id AS jadwal_id,gm.hari,gm.jam_mulai,gm.jam_selesai,
                    a.nama_lengkap AS guru_nama,mp.nama AS nama_mapel
             FROM guru_mengajar_rayhanrp gm
             LEFT JOIN akun_rayhanrp a ON gm.akun_id=a.akun_id
             LEFT JOIN mata_pelajaran_rayhanrp mp ON gm.matpel_id=mp.matpel_id
             WHERE gm.grup_id=? AND gm.hari IS NOT NULL
             ORDER BY FIELD(gm.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'),gm.jam_mulai ASC",
            'i', $id_grup
        ));
        ob_start();
        if (empty($rows)) { echo '<p class="text-center text-muted py-4">Tidak ada jadwal.</p>'; }
        else {
            echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
                  <th>Hari</th><th>Jam</th><th>Guru</th><th>Mapel</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr><td><span class="badge bg-primary">'.$r['hari'].'</span></td>'
                    .'<td>'.substr($r['jam_mulai'],0,5).' – '.substr($r['jam_selesai'],0,5).'</td>'
                    .'<td>'.htmlspecialchars($r['guru_nama'] ?? '-').'</td>'
                    .'<td>'.htmlspecialchars($r['nama_mapel'] ?? '-').'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<div class="alert alert-info mt-3 py-2 small"><i class="bi bi-info-circle me-1"></i>Jadwal dikelola melalui menu <strong>Guru Mengajar</strong>.</div>';
        echo ob_get_clean();
        exit;
    }

    if ($aksi_ajax === 'get_tugas') {
        header('Content-Type: text/html; charset=utf-8');
        $rows = sirey_fetchAll(sirey_query(
            "SELECT t.tugas_id,t.judul,t.tenggat,mp.nama AS matpel_nama
             FROM tugas_rayhanRP t LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id=mp.matpel_id
             WHERE t.grup_id=? ORDER BY t.tenggat DESC", 'i', $id_grup
        ));
        if (empty($rows)) { echo '<p class="text-center text-muted py-4">Belum ada tugas.</p>'; }
        else {
            echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
                  <th>Judul</th><th>Mapel</th><th>Tenggat</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                $over = strtotime($r['tenggat']) < time();
                echo '<tr><td>'.htmlspecialchars($r['judul']).'</td>'
                    .'<td>'.htmlspecialchars($r['matpel_nama'] ?? '-').'</td>'
                    .'<td><span class="badge '.($over ? 'bg-danger' : 'bg-success').'">'.date('d/m/Y', strtotime($r['tenggat'])).'</span></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<div class="alert alert-info mt-3 py-2 small"><i class="bi bi-info-circle me-1"></i>Tugas dikelola melalui menu <strong>Tugas</strong>.</div>';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    if ($aksi_ajax === 'delete_member') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $akunId = (int)($_GET['akun_id'] ?? 0);
        $primary = getPrimaryGroupId($akunId) ?? 0;
        removeUserMembership($akunId, $id_grup);
        if ($primary === $id_grup) syncPrimaryGroup($akunId, null);
        echo json_encode(['success'=>true,'message'=>'Anggota berhasil dihapus']);
        exit;
    }

    if ($aksi_ajax === 'add_member') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $akunId = (int)($_GET['akun_id'] ?? 0);
        $check  = sirey_fetch(sirey_query("SELECT akun_id FROM grup_anggota_rayhanRP WHERE grup_id=? AND akun_id=?",'ii',$id_grup,$akunId));
        if ($check) { echo json_encode(['success'=>false,'message'=>'Anggota sudah ada di grup ini']); exit; }
        $primary = getPrimaryGroupId($akunId) ?? 0;
        if ($primary <= 0) syncPrimaryGroup($akunId, $id_grup);
        else ensureUserMembership($akunId, $id_grup, 'tambahan');
        echo json_encode(['success'=>true,'message'=>'Anggota berhasil ditambahkan']);
        exit;
    }

    if ($aksi_ajax === 'toggle_status') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $status = (int)($_POST['status'] ?? 0);
        sirey_execute('UPDATE grup_rayhanrp SET aktif=? WHERE grup_id=?','ii',$status,$id_grup);
        auditLog($admin['id'],'toggle_grup','grup',$id_grup,['aktif'=>$status]);
        echo json_encode(['success'=>true,'message'=>'Status grup diperbarui','status'=>$status]);
        exit;
    }

    if ($aksi_ajax === 'bulk_delete_members') {
        if (!$bisa_kelola) { echo json_encode(['success'=>false,'message'=>'Akses ditolak']); exit; }
        $ids = array_filter(array_map('intval',(array)($_POST['member_ids'] ?? [])));
        foreach ($ids as $aid) {
            $p = getPrimaryGroupId($aid) ?? 0;
            removeUserMembership($aid, $id_grup);
            if ($p === $id_grup) syncPrimaryGroup($aid, null);
        }
        echo json_encode(['success'=>true,'message'=>count($ids).' anggota dihapus']);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal']);
    exit;
}

// ═══ NORMAL PAGE ═══
$judul_halaman_rayhanrp = 'Grup / Kelas';
$menu_aktif_rayhanrp    = 'grup';
require_once __DIR__ . '/_layout.php';

if (!can('view_grup', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses'); exit;
}

$bisa_buat   = can('create_grup', $data_admin_rayhanrp);
$bisa_ubah   = can('update_grup', $data_admin_rayhanrp);
$bisa_hapus  = can('delete_grup', $data_admin_rayhanrp);
$bisa_tulis  = $bisa_buat || $bisa_ubah || $bisa_hapus;
$id_pembuat  = (int)($data_admin_rayhanrp['id'] ?? 0);
$pesan = $error = '';

$jurusan_list = ['Teknik Pemesinan','Teknik Mekatronika','Teknik Kimia Industri',
                 'Pengembangan Perangkat Lunak dan Gim','Desain Komunikasi Visual','Animasi'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string)($_POST['act'] ?? '');

    if ($aksi === 'create' && $bisa_buat) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $nama    = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat = (int)($_POST['tingkat'] ?? 0);
        $jurusan = (string)($_POST['jurusan'] ?? '');
        $desk    = trim((string)($_POST['deskripsi'] ?? ''));
        if ($nama === '') { $error = 'Nama grup tidak boleh kosong.'; }
        elseif ($tingkat < 10 || $tingkat > 12) { $error = 'Tingkat harus 10–12.'; }
        elseif (!in_array($jurusan, $jurusan_list, true)) { $error = 'Jurusan tidak valid.'; }
        else {
            $h = sirey_execute('INSERT INTO grup_rayhanrp (nama_grup,tingkat,jurusan,deskripsi,pembuat_id) VALUES (?,?,?,?,?)',
                'sissi', $nama, $tingkat, $jurusan, $desk ?: null, $id_pembuat);
            if ($h >= 1) { auditLog($id_pembuat,'create_grup','grup',sirey_lastInsertId(),['tingkat'=>$tingkat,'jurusan'=>$jurusan]); $pesan = "Grup '$nama' berhasil dibuat."; }
            else { $error = 'Gagal membuat grup. Nama mungkin sudah dipakai.'; }
        }

    } elseif ($aksi === 'update' && $bisa_ubah) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $gid     = (int)($_POST['id'] ?? 0);
        $nama    = trim((string)($_POST['nama_grup'] ?? ''));
        $tingkat = (int)($_POST['tingkat'] ?? 0);
        $jurusan = (string)($_POST['jurusan'] ?? '');
        $desk    = trim((string)($_POST['deskripsi'] ?? ''));
        if ($gid <= 0 || $nama === '') { $error = 'Data tidak valid.'; }
        elseif ($tingkat < 10 || $tingkat > 12) { $error = 'Tingkat harus 10–12.'; }
        elseif (!in_array($jurusan, $jurusan_list, true)) { $error = 'Jurusan tidak valid.'; }
        else {
            sirey_execute('UPDATE grup_rayhanrp SET nama_grup=?,tingkat=?,jurusan=?,deskripsi=? WHERE grup_id=?',
                'sissi', $nama, $tingkat, $jurusan, $desk ?: null, $gid);
            auditLog($id_pembuat,'update_grup','grup',$gid,['tingkat'=>$tingkat,'jurusan'=>$jurusan]);
            $pesan = 'Grup berhasil diperbarui.';
        }

    } elseif ($aksi === 'delete' && $bisa_hapus) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $gid = (int)($_POST['id'] ?? 0);
        if ($gid > 0) {
            sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id=?','i',$gid);
            auditLog($id_pembuat,'delete_grup','grup',$gid);
            $pesan = 'Grup berhasil dihapus.';
        }

    } elseif ($aksi === 'delete_multiple' && $bisa_hapus) {
        requireNotReadonly($data_admin_rayhanrp, 'grup.php');
        $ids = array_filter(array_map('intval',(array)($_POST['selected_ids'] ?? [])));
        $n = 0;
        foreach ($ids as $gid) {
            if (sirey_execute('DELETE FROM grup_rayhanrp WHERE grup_id=?','i',$gid) >= 1) $n++;
        }
        auditLog($id_pembuat,'bulk_delete_grup','grup',null,['ids'=>$ids]);
        $pesan = "$n grup berhasil dihapus.";
    }
}

// ── Query daftar grup ──
$cari = trim((string)($_POST['search'] ?? ''));
$sql  = 'SELECT g.grup_id,g.nama_grup,g.tingkat,g.jurusan,g.deskripsi,g.aktif,
                a.nama_lengkap AS pembuat_nama,
                COUNT(DISTINCT ga.akun_id) AS jml_anggota,
                COUNT(DISTINCT CASE WHEN gm.hari IS NOT NULL THEN gm.id END) AS jml_jadwal,
                COUNT(DISTINCT t.tugas_id) AS jml_tugas
         FROM grup_rayhanrp g
         LEFT JOIN akun_rayhanrp a ON g.pembuat_id=a.akun_id
         LEFT JOIN grup_anggota_rayhanrp ga ON g.grup_id=ga.grup_id
         LEFT JOIN guru_mengajar_rayhanrp gm ON g.grup_id=gm.grup_id AND gm.aktif=1
         LEFT JOIN tugas_rayhanrp t ON g.grup_id=t.grup_id';

if ($data_admin_rayhanrp['role'] === 'guru') {
    $sql .= ' INNER JOIN guru_mengajar_rayhanrp gm_s ON g.grup_id=gm_s.grup_id AND gm_s.akun_id='.(int)$data_admin_rayhanrp['id'].' AND gm_s.aktif=1';
}

$whereArr = []; $types = ''; $params = [];
if ($cari !== '') { $whereArr[] = 'g.nama_grup LIKE ?'; $types .= 's'; $params[] = "%$cari%"; }
if ($whereArr) $sql .= ' WHERE '.implode(' AND ', $whereArr);
$sql .= ' GROUP BY g.grup_id ORDER BY g.nama_grup ASC';

$daftarGrup = $params ? sirey_fetchAll(sirey_query($sql,$types,...$params)) : sirey_fetchAll(sirey_query($sql));
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h2><i class="bi bi-mortarboard-fill text-primary me-2"></i>Manajemen Grup / Kelas</h2>
    <p><?php echo match($data_admin_rayhanrp['role']) {
      'guru' => 'Hanya kelas yang Anda ajar yang ditampilkan. Mode baca saja.',
      'kepala_sekolah' => 'Mode baca saja untuk pemantauan.',
      default => 'Kelola grup, anggota, dan jadwal per kelas.'
    }; ?></p>
  </div>
  <?php if ($bisa_buat): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuat">
      <i class="bi bi-plus-lg me-1"></i>Buat Grup Baru
    </button>
  <?php endif; ?>
</div>

<!-- Flash -->
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

<?php if (!$bisa_tulis): ?>
  <div class="alert alert-info mb-3"><i class="bi bi-info-circle me-2"></i>Mode baca saja aktif.</div>
<?php endif; ?>

<!-- Filter + Tabel -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5><i class="bi bi-table me-2"></i>Daftar Grup <span class="badge bg-primary ms-1"><?php echo count($daftarGrup); ?></span></h5>
    <div class="d-flex gap-2">
      <?php if ($bisa_hapus): ?>
        <button id="btnBulkHapus" class="btn btn-sm btn-danger d-none" onclick="submitBulkHapus()">
          <i class="bi bi-trash me-1"></i>Hapus Terpilih (<span id="selCount">0</span>)
        </button>
      <?php endif; ?>
      <!-- Search inline -->
      <form method="POST" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama grup…"
               value="<?php echo htmlspecialchars($cari); ?>" style="min-width:180px;">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        <?php if ($cari !== ''): ?><a href="grup.php" class="btn btn-sm btn-outline-secondary">✕</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($daftarGrup)): ?>
      <div class="empty-state"><i class="bi bi-mortarboard"></i><p><?php echo $cari ? 'Tidak ada grup yang cocok.' : 'Belum ada grup.'; ?></p></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="tblGrup">
          <thead>
            <tr>
              <?php if ($bisa_hapus): ?><th style="width:40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th><?php endif; ?>
              <th>Nama Grup</th><th>Tingkat & Jurusan</th><th>Pembuat</th>
              <th class="text-center">Anggota</th><th class="text-center">Jadwal</th><th class="text-center">Tugas</th>
              <th class="text-center">Status</th><th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarGrup as $g): ?>
              <tr>
                <?php if ($bisa_hapus): ?>
                  <td><input type="checkbox" class="form-check-input grupCheck" value="<?php echo (int)$g['grup_id']; ?>" onchange="updateBulk()"></td>
                <?php endif; ?>
                <td>
                  <div class="fw-600"><?php echo htmlspecialchars($g['nama_grup']); ?></div>
                  <?php if (!empty($g['deskripsi'])): ?>
                    <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($g['deskripsi']); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-info text-dark me-1">Kelas <?php echo (int)$g['tingkat']; ?></span>
                  <span class="badge bg-light text-dark border" style="font-size:10px;"><?php echo htmlspecialchars($g['jurusan']); ?></span>
                </td>
                <td class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($g['pembuat_nama'] ?? '-'); ?></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_anggota']; ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_jadwal']; ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?php echo (int)$g['jml_tugas']; ?></span></td>
                <td class="text-center">
                  <?php if ($bisa_ubah): ?>
                    <button class="btn btn-sm <?php echo (int)$g['aktif'] ? 'btn-success' : 'btn-secondary'; ?>"
                            onclick="toggleStatus(<?php echo (int)$g['grup_id']; ?>, <?php echo (int)$g['aktif']; ?>)">
                      <?php echo (int)$g['aktif'] ? 'Aktif' : 'Nonaktif'; ?>
                    </button>
                  <?php else: ?>
                    <span class="badge <?php echo (int)$g['aktif'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$g['aktif'] ? 'Aktif' : 'Nonaktif'; ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex gap-1 justify-content-center flex-wrap">
                    <button class="btn btn-sm btn-outline-info" title="Anggota"
                            onclick="openModal(<?php echo (int)$g['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($g['nama_grup']), ENT_QUOTES); ?>, 'anggota')">
                      <i class="bi bi-people"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" title="Jadwal"
                            onclick="openModal(<?php echo (int)$g['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($g['nama_grup']), ENT_QUOTES); ?>, 'jadwal')">
                      <i class="bi bi-calendar3"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" title="Tugas"
                            onclick="openModal(<?php echo (int)$g['grup_id']; ?>, <?php echo htmlspecialchars(json_encode($g['nama_grup']), ENT_QUOTES); ?>, 'tugas')">
                      <i class="bi bi-journal-text"></i>
                    </button>
                    <?php if ($bisa_ubah): ?>
                      <button class="btn btn-sm btn-outline-primary" title="Edit"
                              onclick='openEdit(<?php echo json_encode($g, JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                        <i class="bi bi-pencil"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($bisa_hapus): ?>
                      <form method="POST" class="m-0" data-confirm="Hapus grup '<?php echo htmlspecialchars($g['nama_grup']); ?>'? Semua data terkait ikut terhapus.">
                        <input type="hidden" name="act" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$g['grup_id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php endif; ?>
                  </div>
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
<!-- MODAL BUAT GRUP -->
<div class="modal fade" id="modalBuat" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="create">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Buat Grup Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Grup / Kelas <span class="text-danger">*</span></label>
            <input type="text" name="nama_grup" class="form-control" placeholder="Contoh: XI PPLG A" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Tingkat <span class="text-danger">*</span></label>
              <select name="tingkat" class="form-select" required>
                <option value="">— Pilih —</option>
                <option value="10">Kelas X</option>
                <option value="11">Kelas XI</option>
                <option value="12">Kelas XII</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jurusan <span class="text-danger">*</span></label>
              <select name="jurusan" class="form-select" required>
                <option value="">— Pilih —</option>
                <?php foreach ($jurusan_list as $j): ?>
                  <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="deskripsi" class="form-control" placeholder="Opsional">
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

<?php if ($bisa_ubah): ?>
<!-- MODAL EDIT -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Grup</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nama Grup / Kelas <span class="text-danger">*</span></label>
            <input type="text" name="nama_grup" id="edit_nama" class="form-control" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Tingkat <span class="text-danger">*</span></label>
              <select name="tingkat" id="edit_tingkat" class="form-select" required>
                <option value="">— Pilih —</option>
                <option value="10">Kelas X</option>
                <option value="11">Kelas XI</option>
                <option value="12">Kelas XII</option>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Jurusan <span class="text-danger">*</span></label>
              <select name="jurusan" id="edit_jurusan" class="form-select" required>
                <option value="">— Pilih —</option>
                <?php foreach ($jurusan_list as $j): ?>
                  <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Deskripsi</label>
            <input type="text" name="deskripsi" id="edit_deskripsi" class="form-control">
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

<!-- MODAL DETAIL (Anggota / Jadwal / Tugas) -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailTitle">Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <!-- Tab nav -->
      <div class="px-3 pt-3 border-bottom">
        <ul class="nav nav-tabs" id="detailTabs">
          <li class="nav-item">
            <button class="nav-link active" id="tabAnggotaBtn" onclick="switchTab('anggota')">
              <i class="bi bi-people me-1"></i>Anggota
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="tabJadwalBtn" onclick="switchTab('jadwal')">
              <i class="bi bi-calendar3 me-1"></i>Jadwal
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" id="tabTugasBtn" onclick="switchTab('tugas')">
              <i class="bi bi-journal-text me-1"></i>Tugas
            </button>
          </li>
        </ul>
      </div>
      <div class="modal-body" id="detailBody">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden bulk delete form -->
<form id="formBulk" method="POST" style="display:none;">
  <input type="hidden" name="act" value="delete_multiple">
  <div id="bulkContainer"></div>
</form>

<script>
let currentGrupId = 0, currentTab = 'anggota';

// ── Open modal detail ──
function openModal(gid, gname, tab) {
  currentGrupId = gid;
  document.getElementById('detailTitle').textContent = gname;
  new bootstrap.Modal(document.getElementById('modalDetail')).show();
  switchTab(tab || 'anggota');
}

function switchTab(tab) {
  currentTab = tab;
  ['anggota','jadwal','tugas'].forEach(t => {
    document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1) + 'Btn').classList.toggle('active', t === tab);
  });
  loadTab(tab);
}

function loadTab(tab) {
  const body = document.getElementById('detailBody');
  body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
  const action = tab === 'anggota' ? 'get_members' : tab === 'jadwal' ? 'get_jadwal' : 'get_tugas';
  fetch(`./grup.php?action=${action}&grup_id=${currentGrupId}`)
    .then(r => r.text())
    .then(html => {
      body.innerHTML = html;
      // Tambah form tambah anggota jika tab anggota
      if (tab === 'anggota') loadAddMember();
    })
    .catch(() => { body.innerHTML = '<p class="text-danger text-center py-4"><i class="bi bi-exclamation-triangle me-2"></i>Gagal memuat data.</p>'; });
}

function loadAddMember() {
  fetch(`./grup.php?action=get_available_users&grup_id=${currentGrupId}`)
    .then(r => r.text())
    .then(html => {
      if (!html.trim()) return;
      const section = document.createElement('div');
      section.className = 'mt-3 p-3 bg-light rounded';
      section.innerHTML = '<label class="form-label fw-semibold">➕ Tambah Anggota</label>'
        + '<div class="d-flex gap-2 mt-1">'
        + html
        + '<button class="btn btn-sm btn-success" onclick="addMember()"><i class="bi bi-plus"></i> Tambah</button>'
        + '</div>';
      document.getElementById('detailBody').appendChild(section);
    });
}

function addMember() {
  const sel = document.getElementById('addMemberSelect');
  if (!sel || !sel.value) return Swal.fire('Pilih anggota terlebih dahulu', '', 'warning');
  fetch(`./grup.php?action=add_member&grup_id=${currentGrupId}&akun_id=${sel.value}`)
    .then(r => r.json())
    .then(d => {
      if (d.success) { Swal.fire({ icon:'success', title:d.message, timer:1500, showConfirmButton:false }); loadTab('anggota'); }
      else Swal.fire({ icon:'error', title:d.message });
    });
}

function deleteMember(akunId, nama) {
  Swal.fire({ title:`Hapus ${nama}?`, text:'Anggota akan dikeluarkan dari grup ini.', icon:'warning',
    showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'Hapus', cancelButtonText:'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    fetch(`./grup.php?action=delete_member&grup_id=${currentGrupId}&akun_id=${akunId}`)
      .then(r => r.json())
      .then(d => {
        if (d.success) { Swal.fire({ icon:'success', title:d.message, timer:1200, showConfirmButton:false }); loadTab('anggota'); }
        else Swal.fire({ icon:'error', title:d.message });
      });
  });
}

// ── Toggle status grup ──
function toggleStatus(gid, current) {
  const newStatus = current ? 0 : 1;
  const label = newStatus ? 'Aktif' : 'Nonaktif';
  Swal.fire({ title:`Ubah status menjadi ${label}?`, icon:'question',
    showCancelButton:true, confirmButtonText:'Ya', cancelButtonText:'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    const fd = new FormData(); fd.append('status', newStatus);
    fetch(`./grup.php?action=toggle_status&grup_id=${gid}`, { method:'POST', body:fd })
      .then(r => r.json())
      .then(d => { if (d.success) location.reload(); else Swal.fire({ icon:'error', title:d.message }); });
  });
}

// ── Edit modal ──
function openEdit(g) {
  document.getElementById('edit_id').value       = g.grup_id;
  document.getElementById('edit_nama').value     = g.nama_grup;
  document.getElementById('edit_tingkat').value  = g.tingkat;
  document.getElementById('edit_jurusan').value  = g.jurusan;
  document.getElementById('edit_deskripsi').value = g.deskripsi || '';
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
}

// ── Bulk delete ──
<?php if ($bisa_hapus): ?>
document.getElementById('checkAll')?.addEventListener('change', function () {
  document.querySelectorAll('.grupCheck').forEach(cb => cb.checked = this.checked);
  updateBulk();
});
function updateBulk() {
  const n = document.querySelectorAll('.grupCheck:checked').length;
  document.getElementById('selCount').textContent = n;
  document.getElementById('btnBulkHapus').classList.toggle('d-none', n === 0);
}
function submitBulkHapus() {
  const ids = [...document.querySelectorAll('.grupCheck:checked')].map(cb => cb.value);
  Swal.fire({ title:`Hapus ${ids.length} grup?`, text:'Semua data terkait ikut terhapus!', icon:'warning',
    showCancelButton:true, confirmButtonColor:'#dc2626', confirmButtonText:'Ya Hapus', cancelButtonText:'Batal'
  }).then(r => {
    if (!r.isConfirmed) return;
    const c = document.getElementById('bulkContainer');
    c.innerHTML = ids.map(id => `<input type="hidden" name="selected_ids[]" value="${id}">`).join('');
    document.getElementById('formBulk').submit();
  });
}
<?php endif; ?>
</script>

<?php layoutEnd(); ?>