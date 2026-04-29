<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Pengumuman';
$menu_aktif_rayhanrp = 'pengumuman';

require_once __DIR__ . '/_layout.php';

if (!can('view_pengumuman', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$bisa_buat_rayhanrp = can('create_pengumuman', $data_admin_rayhanrp);
$database_rayhanrp = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';

$opsi_grup_rayhanrp = $data_admin_rayhanrp['role'] === 'guru'
    ? getGrupDiajarGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT grup_id, nama_grup, jurusan FROM grup_rayhanRP WHERE aktif = 1 ORDER BY nama_grup ASC'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_rayhanrp = (string)($_POST['act'] ?? 'kirim');

    if ($aksi_rayhanrp === 'delete') {
        $id_rayhanrp = (int)($_POST['id'] ?? 0);

        $allowed = false;
        if ($id_rayhanrp > 0) {
            $data_pengumuman_rayhanrp = sirey_fetch(sirey_query(
                'SELECT pengumuman_id, pembuat_id FROM pengumuman_rayhanRP WHERE pengumuman_id = ?',
                'i',
                $id_rayhanrp
            ));
            $allowed = $data_pengumuman_rayhanrp && ($data_admin_rayhanrp['role'] === 'admin' || $data_admin_rayhanrp['role'] === 'kurikulum' || (int)$data_pengumuman_rayhanrp['pembuat_id'] === (int)$data_admin_rayhanrp['id']);
        }

        if (!$allowed) {
            $error_rayhanrp = 'Anda tidak berhak menghapus pengumuman ini.';
        } else {
            sirey_execute('DELETE FROM pengumuman_rayhanRP WHERE pengumuman_id = ?', 'i', $id_rayhanrp);
            auditLog($data_admin_rayhanrp['id'], 'delete_pengumuman', 'pengumuman', $id_rayhanrp);
            $pesan_rayhanrp = 'Pengumuman berhasil dihapus.';
        }
    } elseif ($bisa_buat_rayhanrp) {
        requireNotReadonly($data_admin_rayhanrp, 'pengumuman.php');

        $judul_rayhanrp = trim((string)($_POST['judul'] ?? ''));
        $isi_pesan_rayhanrp = trim((string)($_POST['pesan'] ?? ''));
        $id_grup_rayhanrp = (int)($_POST['grup_id'] ?? 0);
        $prioritas_rayhanrp = in_array($_POST['prioritas'] ?? '', ['biasa', 'penting', 'darurat'], true)
            ? (string)$_POST['prioritas']
            : 'biasa';
        $target_role_rayhanrp = in_array($_POST['target_role'] ?? '', ['all', 'guru', 'siswa'], true)
            ? (string)$_POST['target_role']
            : 'all';
        
        // Guru hanya bisa mengirim ke siswa
        if ($data_admin_rayhanrp['role'] === 'guru') {
            $target_role_rayhanrp = 'siswa';
        }
        
        $tanggal_tayang_rayhanrp = trim((string)($_POST['tanggal_tayang'] ?? date('Y-m-d')));
        $id_grup_target_rayhanrp = $id_grup_rayhanrp > 0 ? $id_grup_rayhanrp : null;

        if ($judul_rayhanrp === '' || $isi_pesan_rayhanrp === '') {
            $error_rayhanrp = 'Judul dan isi pengumuman wajib diisi.';
        } elseif ($data_admin_rayhanrp['role'] === 'guru' && $id_grup_target_rayhanrp === null) {
            $error_rayhanrp = 'Guru wajib memilih salah satu kelas yang diajar.';
        } elseif ($data_admin_rayhanrp['role'] === 'guru' && $id_grup_target_rayhanrp !== null && !guruHasScopeToGrup($data_admin_rayhanrp['id'], $id_grup_target_rayhanrp)) {
            $error_rayhanrp = 'Anda tidak memiliki scope ke kelas tersebut.';
        } else {
            $sql_pengguna_rayhanrp = 'SELECT DISTINCT at.telegram_chat_id
                         FROM akun_telegram_rayhanRP at
                         INNER JOIN akun_rayhanRP a ON at.akun_id = a.akun_id';
            $tipe_rayhanrp = '';
            $parameter_rayhanrp = [];

            if ($id_grup_target_rayhanrp !== null) {
                $sql_pengguna_rayhanrp .= ' INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
                               WHERE ga.grup_id = ? AND ga.aktif = 1';
                $tipe_rayhanrp .= 'i';
                $parameter_rayhanrp[] = $id_grup_target_rayhanrp;
            } else {
                $sql_pengguna_rayhanrp .= ' WHERE 1=1';
            }

            if ($target_role_rayhanrp !== 'all') {
                $sql_pengguna_rayhanrp .= ' AND a.role = ?';
                $tipe_rayhanrp .= 's';
                $parameter_rayhanrp[] = $target_role_rayhanrp;
            }

            $targets_rayhanrp = $tipe_rayhanrp !== ''
                ? sirey_fetchAll(sirey_query($sql_pengguna_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp))
                : sirey_fetchAll(sirey_query($sql_pengguna_rayhanrp));

            $prefix_rayhanrp = match ($prioritas_rayhanrp) {
                'penting' => '[PENTING]',
                'darurat' => '[DARURAT]',
                default => '[Pengumuman]',
            };

            $telegram_text_rayhanrp = $prefix_rayhanrp . "\n\n" . $judul_rayhanrp . "\n\n" . $isi_pesan_rayhanrp . "\n\n- Bot SiRey";
            $sent_rayhanrp = 0;

            foreach ($targets_rayhanrp as $target_rayhanrp) {
                $chat_id_rayhanrp = (int)($target_rayhanrp['telegram_chat_id'] ?? 0);
                if ($chat_id_rayhanrp > 0 && sendTelegramMessage($chat_id_rayhanrp, $telegram_text_rayhanrp)) {
                    $sent_rayhanrp++;
                }
            }

            $hasil_rayhanrp = sirey_execute(
                'INSERT INTO pengumuman_rayhanRP
                   (judul, isi, grup_id, prioritas, target_role, status, tanggal_tayang,
                    pembuat_id, via_telegram)
                 VALUES (?, ?, ?, ?, ?, "published", ?, ?, 1)',
                'ssisssi',
                $judul_rayhanrp,
                $isi_pesan_rayhanrp,
                $id_grup_target_rayhanrp,
                $prioritas_rayhanrp,
                $target_role_rayhanrp,
                $tanggal_tayang_rayhanrp,
                $data_admin_rayhanrp['id']
            );

            if ($hasil_rayhanrp >= 1) {
                $id_baru_rayhanrp = sirey_lastInsertId();
                sirey_execute(
                    'INSERT INTO notifikasi_rayhanRP (tipe, sumber_tipe, sumber_id, grup_id, pesan, jumlah_terkirim)
                     VALUES ("pengumuman", "pengumuman", ?, ?, ?, ?)',
                    'iisi',
                    $id_baru_rayhanrp,
                    $id_grup_target_rayhanrp,
                    '[' . $judul_rayhanrp . '] ' . $isi_pesan_rayhanrp,
                    $sent_rayhanrp
                );

                auditLog($data_admin_rayhanrp['id'], 'create_pengumuman', 'pengumuman', $id_baru_rayhanrp, [
                    'grup_id' => $id_grup_target_rayhanrp,
                    'target_role' => $target_role_rayhanrp,
                    'jumlah_terkirim' => $sent_rayhanrp,
                ]);
                $pesan_rayhanrp = 'Pengumuman berhasil dikirim ke ' . $sent_rayhanrp . ' akun Telegram.';
            } else {
                $error_rayhanrp = 'Gagal menyimpan pengumuman.';
            }
        }
    }
}

$pernyataan_sql_rayhanrp = 'SELECT p.pengumuman_id, p.judul, p.isi, p.prioritas, p.target_role, p.status,
               p.tanggal_tayang, p.dibuat_pada,
               COALESCE(n.jumlah_terkirim, 0) as jumlah_terkirim,
               COALESCE(n.waktu_kirim, p.dibuat_pada) as waktu_kirim_telegram,
               g.nama_grup, a.nama_lengkap AS pembuat_nama, a.akun_id AS pembuat_id
        FROM pengumuman_rayhanRP p
        LEFT JOIN notifikasi_rayhanRP n ON n.sumber_tipe = "pengumuman" AND n.sumber_id = p.pengumuman_id
        LEFT JOIN grup_rayhanRP g ON p.grup_id = g.grup_id
        LEFT JOIN akun_rayhanRP a ON p.pembuat_id = a.akun_id
        WHERE p.status = "published"';
$tipe_rayhanrp = '';
$parameter_rayhanrp = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $pernyataan_sql_rayhanrp .= ' AND p.pembuat_id = ?';
    $tipe_rayhanrp .= 'i';
    $parameter_rayhanrp[] = $data_admin_rayhanrp['id'];
}

$pernyataan_sql_rayhanrp .= ' ORDER BY p.dibuat_pada DESC LIMIT 100';

$daftar_pengumuman_rayhanrp = $tipe_rayhanrp !== ''
    ? sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$parameter_rayhanrp))
    : sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp));
?>

<div class="page-header">
  <h2>Pengumuman</h2>
  <p><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Guru hanya bisa mengirim ke kelas yang diajar.' : 'Kelola pengumuman resmi untuk Telegram.'; ?></p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<?php if ($bisa_buat_rayhanrp): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Buat Pengumuman</h3></div>
    <form method="POST" style="display:grid; grid-template-columns:1.5fr 1fr 1fr 1fr; gap:14px;">
      <input type="hidden" name="act" value="kirim">
      <div class="form-group" style="grid-column:1 / -1; margin:0;">
        <label class="form-label">Judul</label>
        <input type="text" name="judul" class="form-control" required placeholder="Contoh: Informasi Ujian Tengah Semester">
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Kelas</label>
        <select name="grup_id" class="form-control" <?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'required' : ''; ?>>
          <option value=""><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Pilih kelas' : 'Semua pengguna'; ?></option>
          <?php foreach ($opsi_grup_rayhanrp as $group_rayhanrp): ?>
            <option value="<?php echo (int)$group_rayhanrp['grup_id']; ?>"><?php echo htmlspecialchars((string)$group_rayhanrp['nama_grup']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Target Role</label>
        <?php if ($data_admin_rayhanrp['role'] === 'guru'): ?>
          <select name="target_role" class="form-control" disabled>
            <option value="siswa">Siswa</option>
          </select>
          <input type="hidden" name="target_role" value="siswa">
          <small style="color:#64748b;">Guru hanya dapat mengirim ke siswa</small>
        <?php else: ?>
          <select name="target_role" class="form-control">
            <option value="all">Semua</option>
            <option value="siswa">Siswa</option>
            <option value="guru">Guru</option>
          </select>
        <?php endif; ?>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Prioritas</label>
        <select name="prioritas" class="form-control">
          <option value="biasa">Biasa</option>
          <option value="penting">Penting</option>
          <option value="darurat">Darurat</option>
        </select>
      </div>

      <div class="form-group" style="margin:0;">
        <label class="form-label">Tanggal Tayang</label>
        <input type="date" name="tanggal_tayang" class="form-control" value="<?php echo date('Y-m-d'); ?>">
      </div>

      <div class="form-group" style="grid-column:1 / -1; margin:0;">
        <label class="form-label">Isi Pengumuman</label>
        <textarea name="pesan" class="form-control" rows="6" required placeholder="Tulis pengumuman yang akan dikirim ke Telegram..."></textarea>
      </div>

      <div style="grid-column:1 / -1;">
        <button type="submit" class="btn btn-primary">Kirim Pengumuman</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3>Riwayat Pengumuman (<?php echo count($daftar_pengumuman_rayhanrp); ?>)</h3>
  </div>

  <?php if (empty($daftar_pengumuman_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">Pengumuman</div>
      <p>Belum ada pengumuman.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Judul</th>
          <th>Target</th>
          <th>Prioritas</th>
          <th>Terkirim</th>
          <th>Pembuat</th>
          <th>Tanggal</th>
          <?php if ($bisa_buat_rayhanrp): ?><th>Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_pengumuman_rayhanrp as $item_pengumuman_rayhanrp): ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars((string)$item_pengumuman_rayhanrp['judul']); ?></strong>
              <br>
              <small style="color:var(--clr-muted); white-space:pre-wrap;"><?php echo htmlspecialchars(mb_substr((string)$item_pengumuman_rayhanrp['isi'], 0, 120)); ?>...</small>
            </td>
            <td>
              <?php echo htmlspecialchars((string)($item_pengumuman_rayhanrp['nama_grup'] ?? 'Semua')); ?>
              <br><small><?php echo htmlspecialchars((string)$item_pengumuman_rayhanrp['target_role']); ?></small>
            </td>
            <td><span class="badge badge-default"><?php echo htmlspecialchars((string)$item_pengumuman_rayhanrp['prioritas']); ?></span></td>
            <td>
              <?php echo (int)($item_pengumuman_rayhanrp['jumlah_terkirim'] ?? 0); ?> akun
              <?php if (!empty($item_pengumuman_rayhanrp['waktu_kirim_telegram'])): ?>
                <br><small><?php echo formatDatetime((string)$item_pengumuman_rayhanrp['waktu_kirim_telegram']); ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars((string)($item_pengumuman_rayhanrp['pembuat_nama'] ?? '-')); ?></td>
            <td><?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$item_pengumuman_rayhanrp['tanggal_tayang']))); ?></td>
            <?php if ($bisa_buat_rayhanrp): ?>
              <td>
                <?php $bisa_hapus_rayhanrp = $data_admin_rayhanrp['role'] !== 'guru' || (int)$item_pengumuman_rayhanrp['pembuat_id'] === (int)$data_admin_rayhanrp['id']; ?>
                <?php if ($bisa_hapus_rayhanrp): ?>
                  <form method="POST" onsubmit="return confirm('Hapus pengumuman ini?')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$item_pengumuman_rayhanrp['pengumuman_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                  </form>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php layoutEnd(); ?>
