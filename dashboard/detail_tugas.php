<?php
declare(strict_types=1);

$judul_halaman_rayhanrp  = 'Kirim Pengumuman';
$menu_aktif_rayhanrp = 'pengumuman';
require_once __DIR__ . '/_layout.php';

$pesan_rayhanrp = '';
$error_rayhanrp = '';

// Ambil daftar grup untuk pilihan tujuan pengiriman.
$daftar_grup_rayhanrp = sirey_fetchAll(
    sirey_query('SELECT grup_id, nama_grup FROM grup_rayhanRP ORDER BY nama_grup ASC')
);

// Proses form saat tombol kirim ditekan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isi_pesan_rayhanrp = trim((string)($_POST['pesan'] ?? ''));
    $tipe_notifikasi_rayhanrp = (string)($_POST['tipe'] ?? 'pengumuman');
    $input_id_grup_rayhanrp = trim((string)($_POST['grup_id'] ?? ''));
    $id_grup_rayhanrp = ($input_id_grup_rayhanrp === '') ? null : (int)$input_id_grup_rayhanrp;

    if ($isi_pesan_rayhanrp === '') {
        $error_rayhanrp = 'Pesan tidak boleh kosong.';
    } else {
        // Tentukan target pengguna.
        if ($id_grup_rayhanrp !== null) {
            $daftar_pengguna_rayhanrp = sirey_fetchAll(
                sirey_query(
                    'SELECT at.telegram_chat_id
                     FROM akun_telegram_rayhanRP at
                     INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
                     WHERE ga.grup_id = ?',
                    'i',
                    $id_grup_rayhanrp
                )
            );
        } else {
            $daftar_pengguna_rayhanrp = sirey_fetchAll(
                sirey_query('SELECT telegram_chat_id FROM akun_telegram_rayhanRP')
            );
        }

        // Kirim pesan satu per satu.
        $jumlah_terkirim_rayhanrp = 0;
        foreach ($daftar_pengguna_rayhanrp as $pengguna_rayhanrp) {
            $id_chat_rayhanrp = (int)$pengguna_rayhanrp['telegram_chat_id'];
            $terkirim_rayhanrp = sendTelegramMessage($id_chat_rayhanrp, $isi_pesan_rayhanrp);

            if ($terkirim_rayhanrp) {
                $jumlah_terkirim_rayhanrp++;
            }
        }

        // Simpan log pengiriman.
        sirey_execute(
            'INSERT INTO notifikasi_rayhanRP (tipe, grup_id, pesan, jumlah_terkirim) VALUES (?,?,?,?)',
            'sisi',
            $tipe_notifikasi_rayhanrp,
            $id_grup_rayhanrp,
            $isi_pesan_rayhanrp,
            $jumlah_terkirim_rayhanrp
        );

        $pesan_rayhanrp = 'Pengumuman berhasil dikirim ke ' . $jumlah_terkirim_rayhanrp . ' pengguna.';
    }
}
?>

<div class="page-header">
  <h2>📣 Kirim Pengumuman</h2>
  <p>Broadcast notifikasi ke pengguna via Telegram.</p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>

<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<div class="card" style="max-width:640px;">
  <div class="card-header">
    <h3>Form Pengiriman</h3>
  </div>

  <form method="POST">
    <div class="form-group">
      <label class="form-label">Tipe Notifikasi</label>
      <select name="tipe" class="form-control">
        <option value="pengumuman">📣 Pengumuman</option>
        <option value="jadwal">📅 Jadwal</option>
        <option value="tugas">📝 Tugas</option>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Kirim ke</label>
      <select name="grup_id" class="form-control">
        <option value="">Semua Pengguna</option>
        <?php foreach ($daftar_grup_rayhanrp as $grup_rayhanrp): ?>
          <option value="<?php echo (int)$grup_rayhanrp['grup_id']; ?>"><?php echo htmlspecialchars($grup_rayhanrp['nama_grup']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Isi Pesan</label>
      <textarea name="pesan" class="form-control" rows="6" placeholder="Ketik pesan notifikasi…" required></textarea>
      <small style="color:var(--clr-muted);">Format Markdown Telegram didukung: *tebal*, _miring_, dll.</small>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">Kirim Sekarang</button>
      <a href="notifikasi.php" class="btn btn-secondary">Batal</a>
    </div>
  </form>
</div>

<?php layoutEnd(); ?>
