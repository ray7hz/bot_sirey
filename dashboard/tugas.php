<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();

$aksi_ajax_rayhanrp = (string)($_GET['action'] ?? '');
$id_tugas_ajax_rayhanrp = (int)($_GET['tugas_id'] ?? 0);
$admin_ajax_rayhanrp = [
    'id' => (int)($_SESSION['admin_id'] ?? 0),
    'role' => (string)($_SESSION['admin_role'] ?? ''),
    'name' => (string)($_SESSION['admin_name'] ?? ''),
];

if ($aksi_ajax_rayhanrp !== '') {
    if ($admin_ajax_rayhanrp['id'] <= 0 || !can('view_tugas', $admin_ajax_rayhanrp)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    if ($aksi_ajax_rayhanrp === 'get_kelas_by_matpel') {
        header('Content-Type: application/json; charset=utf-8');
        $id_matpel_rayhanrp = (int)($_GET['matpel_id'] ?? 0);
        $baris_rayhanrp = $id_matpel_rayhanrp > 0
            ? getTeachingCoverageByMatpel($id_matpel_rayhanrp, $admin_ajax_rayhanrp['role'] === 'guru' ? $admin_ajax_rayhanrp['id'] : null)
            : [];

        echo json_encode([
            'success' => true,
            'items' => $baris_rayhanrp,
            'count' => count($baris_rayhanrp),
        ]);
        exit;
    }

    if ($aksi_ajax_rayhanrp === 'get_siswa_by_matpel') {
        header('Content-Type: application/json; charset=utf-8');
        $id_matpel_rayhanrp = (int)($_GET['matpel_id'] ?? 0);
        
        if ($admin_ajax_rayhanrp['role'] === 'guru' && $id_matpel_rayhanrp > 0) {
            // Untuk guru: ambil siswa dari kelas yang diajar guru untuk mapel ini
            $daftar_siswa_rayhanrp = getSiswaFromGuruKelas($admin_ajax_rayhanrp['id'], $id_matpel_rayhanrp);
        } else {
            // Untuk admin/non-guru: ambil semua siswa
            $daftar_siswa_rayhanrp = $id_matpel_rayhanrp > 0
                ? sirey_fetchAll(sirey_query(
                    'SELECT a.akun_id, a.nama_lengkap, g.nama_grup
                     FROM akun_rayhanRP a
                     LEFT JOIN grup_anggota_rayhanRP ga_utama
                            ON ga_utama.akun_id = a.akun_id
                           AND ga_utama.tipe_keanggotaan = "utama"
                           AND ga_utama.aktif = 1
                     LEFT JOIN grup_rayhanRP g ON ga_utama.grup_id = g.grup_id
                     WHERE a.role = "siswa"
                     ORDER BY a.nama_lengkap ASC'
                ))
                : [];
        }

        echo json_encode([
            'success' => true,
            'items' => $daftar_siswa_rayhanrp,
            'count' => count($daftar_siswa_rayhanrp),
        ]);
        exit;
    }

    if ($id_tugas_ajax_rayhanrp <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'ID tugas tidak valid']);
        exit;
    }

    $tugas_rayhanrp = sirey_fetch(sirey_query(
        'SELECT tugas_id, judul, grup_id, pembuat_id, status, izin_revisi, tipe_tugas
         FROM tugas_rayhanRP WHERE tugas_id = ?',
        'i',
        $id_tugas_ajax_rayhanrp
    ));

    $bisa_kelola_tugas_rayhanrp = $tugas_rayhanrp && ($admin_ajax_rayhanrp['role'] !== 'guru' || (int)$tugas_rayhanrp['pembuat_id'] === (int)$admin_ajax_rayhanrp['id']);

    if ($aksi_ajax_rayhanrp === 'toggle_status') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$tugas_rayhanrp || !$bisa_kelola_tugas_rayhanrp) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        $status_baru_rayhanrp = $tugas_rayhanrp['status'] === 'active' ? 'closed' : 'active';
        sirey_execute('UPDATE tugas_rayhanRP SET status = ? WHERE tugas_id = ?', 'si', $status_baru_rayhanrp, $id_tugas_ajax_rayhanrp);
        auditLog($admin_ajax_rayhanrp['id'], 'toggle_status_tugas', 'tugas', $id_tugas_ajax_rayhanrp, ['status' => $status_baru_rayhanrp]);

        echo json_encode(['success' => true, 'status' => $status_baru_rayhanrp]);
        exit;
    }

    if ($aksi_ajax_rayhanrp === 'toggle_revision') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$tugas_rayhanrp || !$bisa_kelola_tugas_rayhanrp) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            exit;
        }

        $nilai_baru_rayhanrp = (int)$tugas_rayhanrp['izin_revisi'] === 1 ? 0 : 1;
        sirey_execute('UPDATE tugas_rayhanRP SET izin_revisi = ? WHERE tugas_id = ?', 'ii', $nilai_baru_rayhanrp, $id_tugas_ajax_rayhanrp);
        auditLog($admin_ajax_rayhanrp['id'], 'toggle_revision_tugas', 'tugas', $id_tugas_ajax_rayhanrp, ['izin_revisi' => $nilai_baru_rayhanrp]);

        echo json_encode(['success' => true, 'izin_revisi' => $nilai_baru_rayhanrp]);
        exit;
    }

    if ($aksi_ajax_rayhanrp === 'get_submissions') {
        header('Content-Type: text/html; charset=utf-8');
        if (!$tugas_rayhanrp || !$bisa_kelola_tugas_rayhanrp) {
            echo '<p style="color:red;">Akses ditolak.</p>';
            exit;
        }

        $data_tugas_rayhanrp = sirey_fetch(sirey_query(
            'SELECT t.tugas_id, t.tipe_tugas, t.judul, t.tenggat, t.grup_id, g.nama_grup
             FROM tugas_rayhanRP t
             LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
             WHERE t.tugas_id = ?',
            'i',
            $id_tugas_ajax_rayhanrp
        ));

        if (!$data_tugas_rayhanrp) {
            echo '<p style="color:red;">Tugas tidak ditemukan.</p>';
            exit;
        }

        if ($data_tugas_rayhanrp['tipe_tugas'] === 'grup' && (int)($data_tugas_rayhanrp['grup_id'] ?? 0) > 0) {
            $daftar_siswa_rayhanrp = sirey_fetchAll(sirey_query(
                'SELECT a.akun_id, a.nama_lengkap, a.nis_nip
                 FROM akun_rayhanRP a
                 INNER JOIN grup_anggota_rayhanRP ga ON a.akun_id = ga.akun_id
                 WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"
                 ORDER BY a.nama_lengkap ASC',
                'i',
                (int)$data_tugas_rayhanrp['grup_id']
            ));
        } else {
            $daftar_siswa_rayhanrp = sirey_fetchAll(sirey_query(
                'SELECT a.akun_id, a.nama_lengkap, a.nis_nip
                 FROM akun_rayhanRP a
                 INNER JOIN tugas_perorang_rayhanRP tp ON a.akun_id = tp.akun_id
                 WHERE tp.tugas_id = ? AND a.role = "siswa"
                 ORDER BY a.nama_lengkap ASC',
                'i',
                $id_tugas_ajax_rayhanrp
            ));
        }

        $daftar_dikumpulkan_rayhanrp = sirey_fetchAll(sirey_query(
            'SELECT p.akun_id, a.nama_lengkap, a.nis_nip, p.status, p.waktu_kumpul, p.via,
                    pn.nilai
             FROM pengumpulan_rayhanRP p
             INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
             LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
             WHERE p.tugas_id = ?
             ORDER BY p.waktu_kumpul ASC',
            'i',
            $id_tugas_ajax_rayhanrp
        ));

        $daftar_id_dikumpulkan_rayhanrp = array_column($daftar_dikumpulkan_rayhanrp, 'akun_id');
        $belum_dikumpulkan_rayhanrp = array_filter($daftar_siswa_rayhanrp, static fn(array $row): bool => !in_array($row['akun_id'], $daftar_id_dikumpulkan_rayhanrp, true));
        ?>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:18px;">
          <div style="padding:14px; background:#eff6ff; border-radius:8px;">
            <strong><?php echo count($daftar_siswa_rayhanrp); ?></strong><br><small>Total siswa</small>
          </div>
          <div style="padding:14px; background:#ecfdf5; border-radius:8px;">
            <strong><?php echo count($daftar_dikumpulkan_rayhanrp); ?></strong><br><small>Sudah mengumpulkan</small>
          </div>
          <div style="padding:14px; background:#fef2f2; border-radius:8px;">
            <strong><?php echo count($belum_dikumpulkan_rayhanrp); ?></strong><br><small>Belum mengumpulkan</small>
          </div>
        </div>

        <h4 style="margin:0 0 10px;">Belum Mengumpulkan</h4>
        <?php if ($belum_dikumpulkan_rayhanrp === []): ?>
          <p style="color:#15803d;">Semua siswa sudah mengumpulkan.</p>
        <?php else: ?>
          <ul style="margin:0 0 18px 18px;">
            <?php foreach (array_slice($belum_dikumpulkan_rayhanrp, 0, 20) as $siswa_rayhanrp): ?>
              <li><?php echo htmlspecialchars($siswa_rayhanrp['nama_lengkap'] . ' (' . $siswa_rayhanrp['nis_nip'] . ')'); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <h4 style="margin:0 0 10px;">Sudah Mengumpulkan</h4>
        <?php if ($daftar_dikumpulkan_rayhanrp === []): ?>
          <p style="color:#64748b;">Belum ada pengumpulan.</p>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
              <tr>
                <th style="text-align:left; padding:8px;">Nama</th>
                <th style="text-align:left; padding:8px;">Status</th>
                <th style="text-align:left; padding:8px;">Waktu</th>
                <th style="text-align:left; padding:8px;">Nilai</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daftar_dikumpulkan_rayhanrp as $baris_rayhanrp): ?>
                <tr>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)$baris_rayhanrp['nama_lengkap']); ?></td>
                  <td style="padding:8px;"><?php echo htmlspecialchars((string)$baris_rayhanrp['status']); ?></td>
                  <td style="padding:8px;"><?php echo formatDatetime((string)$baris_rayhanrp['waktu_kumpul']); ?></td>
                  <td style="padding:8px;"><?php echo $baris_rayhanrp['nilai'] !== null ? (int)$baris_rayhanrp['nilai'] : '-'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif;
        exit;
    }
}

$judul_halaman_rayhanrp = 'Manajemen Tugas';
$menu_aktif_rayhanrp = 'tugas';
require_once __DIR__ . '/_layout.php';

if (!can('view_tugas', $data_admin_rayhanrp)) {
    header('Location: dashboard.php?err=akses');
    exit;
}

$bisa_tulis_rayhanrp = can('create_tugas', $data_admin_rayhanrp);
$database_rayhanrp = sirey_getDatabase();
$pesan_rayhanrp = '';
$error_rayhanrp = '';
$id_pembuat_rayhanrp = (int)$data_admin_rayhanrp['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi_rayhanrp = (string)($_POST['act'] ?? '');

    if (!$bisa_tulis_rayhanrp) {
        $error_rayhanrp = 'Anda tidak memiliki izin mengelola tugas.';
    } else {
        $id_tugas_rayhanrp = (int)($_POST['id'] ?? 0);
        $tipe_tugas_rayhanrp = (string)($_POST['tipe_tugas'] ?? 'grup');
        $id_grup_rayhanrp = (int)($_POST['grup_id'] ?? 0);
        $judul_rayhanrp = trim((string)($_POST['judul'] ?? ''));
        $deskripsi_rayhanrp = trim((string)($_POST['deskripsi'] ?? ''));
        $id_matpel_rayhanrp = (int)($_POST['matpel_id'] ?? 0);
        $tenggat_rayhanrp = trim((string)($_POST['tenggat'] ?? ''));
        $poin_maksimal_rayhanrp = max(1, min(100, (int)($_POST['poin_maksimal'] ?? 100)));
        $lampiran_rayhanrp = trim((string)($_POST['lampiran_url'] ?? ''));
        $status_rayhanrp = in_array($_POST['status'] ?? '', ['draft', 'active', 'closed'], true)
            ? (string)$_POST['status']
            : 'active';

        $data_matpel_rayhanrp = $id_matpel_rayhanrp > 0
            ? sirey_fetch(sirey_query('SELECT kode, nama FROM mata_pelajaran_rayhanRP WHERE matpel_id = ?', 'i', $id_matpel_rayhanrp))
            : null;
        $nama_matpel_rayhanrp = $data_matpel_rayhanrp['nama'] ?? '';

        // Validate tipe tugas untuk guru
        if ($data_admin_rayhanrp['role'] === 'guru' && $tipe_tugas_rayhanrp !== 'grup' && $tipe_tugas_rayhanrp !== 'perorang') {
            $error_rayhanrp = 'Tipe tugas tidak valid. Guru hanya dapat membuat tugas grup atau perorangan.';
        } elseif ($id_matpel_rayhanrp > 0 && !$data_matpel_rayhanrp) {
            $error_rayhanrp = 'Mata pelajaran yang dipilih tidak ditemukan.';
        } elseif ($judul_rayhanrp === '' || $tenggat_rayhanrp === '' || $id_matpel_rayhanrp <= 0) {
            $error_rayhanrp = 'Mapel, judul, dan tenggat wajib diisi.';
        } elseif ($tipe_tugas_rayhanrp === 'grup' && $id_grup_rayhanrp <= 0) {
            $error_rayhanrp = 'Kelas tujuan wajib dipilih untuk tugas grup.';
        } elseif ($tipe_tugas_rayhanrp === 'grup' && $data_admin_rayhanrp['role'] === 'guru' && !guruHasScopeToGrup($data_admin_rayhanrp['id'], $id_grup_rayhanrp, $id_matpel_rayhanrp > 0 ? $id_matpel_rayhanrp : null)) {
            $error_rayhanrp = 'Anda tidak memiliki scope ke kelas/mapel tersebut.';
        } elseif ($data_admin_rayhanrp['role'] !== 'guru' && $tipe_tugas_rayhanrp === 'grup' && !grupHasMatpelAssignment($id_grup_rayhanrp, $id_matpel_rayhanrp)) {
            $error_rayhanrp = 'Kelas tersebut belum memiliki assignment aktif untuk mapel yang dipilih.';
        } elseif ($tipe_tugas_rayhanrp === 'perorang' && empty($_POST['recipient_ids'])) {
            $error_rayhanrp = 'Pilih minimal satu siswa untuk tugas perorangan.';
        } else {
            // Validate bahwa semua recipient adalah siswa dari kelas guru (untuk guru)
            if ($data_admin_rayhanrp['role'] === 'guru' && $tipe_tugas_rayhanrp === 'perorang') {
                $recipient_ids_rayhanrp = array_filter(array_map('intval', (array)($_POST['recipient_ids'] ?? [])));
                foreach ($recipient_ids_rayhanrp as $id_akun_rayhanrp) {
                    if (!guruHasAccessToSiswa($data_admin_rayhanrp['id'], $id_akun_rayhanrp, $id_matpel_rayhanrp)) {
                        $error_rayhanrp = "Siswa dengan ID {$id_akun_rayhanrp} bukan bagian dari kelas Anda.";
                        break;
                    }
                }
            }
        }
        
        if ($error_rayhanrp === '') {
            if ($aksi_rayhanrp === 'create') {
                $hasil_rayhanrp = sirey_execute(
                    'INSERT INTO tugas_rayhanRP
                       (grup_id, judul, deskripsi, matpel_id, tenggat,
                        poin_maksimal, lampiran_url, status, tipe_tugas, pembuat_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'issisiissi',
                    $tipe_tugas_rayhanrp === 'grup' ? $id_grup_rayhanrp : null,
                    $judul_rayhanrp,
                    $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
                    $id_matpel_rayhanrp > 0 ? $id_matpel_rayhanrp : null,
                    $tenggat_rayhanrp,
                    $poin_maksimal_rayhanrp,
                    $lampiran_rayhanrp !== '' ? $lampiran_rayhanrp : null,
                    $status_rayhanrp,
                    $tipe_tugas_rayhanrp,
                    $id_pembuat_rayhanrp
                );

                if ($hasil_rayhanrp >= 1) {
                    $id_tugas_baru_rayhanrp = sirey_lastInsertId();

                    if ($tipe_tugas_rayhanrp === 'perorang') {
                        foreach (array_map('intval', (array)($_POST['recipient_ids'] ?? [])) as $id_akun_rayhanrp) {
                            if ($id_akun_rayhanrp > 0) {
                                sirey_execute('INSERT INTO tugas_perorang_rayhanRP (tugas_id, akun_id) VALUES (?, ?)', 'ii', $id_tugas_baru_rayhanrp, $id_akun_rayhanrp);
                            }
                        }
                    }

                    if ($status_rayhanrp === 'active') {
                        $tenggat_format_rayhanrp = date('d/m/Y H:i', strtotime($tenggat_rayhanrp));
                        $pesan_rayhanrp = "Tugas Baru\n\n" . $judul_rayhanrp . "\n" .
                            ($nama_matpel_rayhanrp !== '' ? "Mapel: " . $nama_matpel_rayhanrp . "\n" : '') .
                            "Deadline: " . $tenggat_format_rayhanrp . "\n";

                        if ($tipe_tugas_rayhanrp === 'grup' && $id_grup_rayhanrp > 0) {
                            // Kirim notifikasi ke semua siswa di kelas
                            $targets = sirey_fetchAll(sirey_query(
                                'SELECT DISTINCT at.telegram_chat_id
                                 FROM akun_telegram_rayhanRP at
                                 INNER JOIN grup_anggota_rayhanRP ga ON at.akun_id = ga.akun_id
                                 INNER JOIN akun_rayhanRP a ON a.akun_id = ga.akun_id
                                 WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = "siswa"',
                                'i',
                                $id_grup_rayhanrp
                            ));

                            $sentCount = 0;
                            foreach ($targets as $target) {
                                $chatId = (int)($target['telegram_chat_id'] ?? 0);
                                if ($chatId > 0 && sendTelegramMessage($chatId, $pesan_rayhanrp)) {
                                    $sentCount++;
                                }
                            }

                            if ($sentCount > 0) {
                                sirey_execute(
                                    'INSERT INTO notifikasi_rayhanRP (tipe, grup_id, pesan, jumlah_terkirim)
                                     VALUES ("tugas", ?, ?, ?)',
                                    'isi',
                                    $id_grup_rayhanrp,
                                    'Tugas baru: ' . $judul_rayhanrp,
                                    $sentCount
                                );
                            }
                        } elseif ($tipe_tugas_rayhanrp === 'perorang') {
                            // Kirim notifikasi ke siswa yang ditunjuk saja
                            $recipient_ids_rayhanrp = array_filter(array_map('intval', (array)($_POST['recipient_ids'] ?? [])));
                            
                            if (!empty($recipient_ids_rayhanrp)) {
                                $targets = sirey_fetchAll(sirey_query(
                                    'SELECT DISTINCT a.akun_id, at.telegram_chat_id
                                     FROM akun_telegram_rayhanRP at
                                     INNER JOIN akun_rayhanRP a ON at.akun_id = a.akun_id
                                     WHERE a.akun_id IN (' . implode(',', array_map('intval', $recipient_ids_rayhanrp)) . ')
                                       AND a.role = "siswa"'
                                ));

                                $sentCount = 0;
                                foreach ($targets as $target) {
                                    $chatId = (int)($target['telegram_chat_id'] ?? 0);
                                    if ($chatId > 0 && sendTelegramMessage($chatId, $pesan_rayhanrp)) {
                                        $sentCount++;
                                    }
                                }

                                if ($sentCount > 0) {
                                    sirey_execute(
                                        'INSERT INTO notifikasi_rayhanRP (tipe, pesan, jumlah_terkirim)
                                         VALUES ("tugas", ?, ?)',
                                        'si',
                                        'Tugas perorang baru: ' . $judul_rayhanrp,
                                        $sentCount
                                    );
                                }
                            }
                        }
                    }

                    auditLog($data_admin_rayhanrp['id'], 'create_tugas', 'tugas', $id_tugas_baru_rayhanrp, [
                        'grup_id' => $id_grup_rayhanrp > 0 ? $id_grup_rayhanrp : null,
                        'matpel_id' => $id_matpel_rayhanrp > 0 ? $id_matpel_rayhanrp : null,
                        'tipe_tugas' => $tipe_tugas_rayhanrp,
                    ]);
                    $pesan_rayhanrp = 'Tugas berhasil dibuat.';
                } else {
                    $error_rayhanrp = 'Gagal menyimpan tugas.';
                }
            } elseif ($aksi_rayhanrp === 'update') {
                $data_tugas_rayhanrp = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id = ?', 'i', $id_tugas_rayhanrp));
                if (!$data_tugas_rayhanrp || ($data_admin_rayhanrp['role'] === 'guru' && (int)$data_tugas_rayhanrp['pembuat_id'] !== (int)$data_admin_rayhanrp['id'])) {
                    $error_rayhanrp = 'Anda tidak berhak mengubah tugas ini.';
                } else {
                    sirey_execute(
                        'UPDATE tugas_rayhanRP
                         SET grup_id = ?, judul = ?, deskripsi = ?, matpel_id = ?,
                             tenggat = ?, poin_maksimal = ?, lampiran_url = ?, status = ?
                         WHERE tugas_id = ?',
                        'issisissi',
                        $id_grup_rayhanrp > 0 ? $id_grup_rayhanrp : null,
                        $judul_rayhanrp,
                        $deskripsi_rayhanrp !== '' ? $deskripsi_rayhanrp : null,
                        $id_matpel_rayhanrp > 0 ? $id_matpel_rayhanrp : null,
                        $tenggat_rayhanrp,
                        $poin_maksimal_rayhanrp,
                        $lampiran_rayhanrp !== '' ? $lampiran_rayhanrp : null,
                        $status_rayhanrp,
                        $id_tugas_rayhanrp
                    );
                    auditLog($data_admin_rayhanrp['id'], 'update_tugas', 'tugas', $id_tugas_rayhanrp, ['matpel_id' => $id_matpel_rayhanrp > 0 ? $id_matpel_rayhanrp : null]);
                    $pesan_rayhanrp = 'Tugas berhasil diperbarui.';
                }
            } elseif ($aksi_rayhanrp === 'delete') {
                $data_tugas_rayhanrp = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id = ?', 'i', $id_tugas_rayhanrp));
                if (!$data_tugas_rayhanrp || ($data_admin_rayhanrp['role'] === 'guru' && (int)$data_tugas_rayhanrp['pembuat_id'] !== (int)$data_admin_rayhanrp['id'])) {
                    $error_rayhanrp = 'Anda tidak berhak menghapus tugas ini.';
                } else {
                    sirey_execute('DELETE FROM tugas_perorang_rayhanRP WHERE tugas_id = ?', 'i', $id_tugas_rayhanrp);
                    sirey_execute('DELETE FROM tugas_rayhanRP WHERE tugas_id = ?', 'i', $id_tugas_rayhanrp);
                    auditLog($data_admin_rayhanrp['id'], 'delete_tugas', 'tugas', $id_tugas_rayhanrp);
                    $pesan_rayhanrp = 'Tugas berhasil dihapus.';
                }
            } elseif ($aksi_rayhanrp === 'bulk_delete') {
                $daftar_id_rayhanrp = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
                foreach ($daftar_id_rayhanrp as $id_item_rayhanrp) {
                    $data_tugas_rayhanrp = sirey_fetch(sirey_query('SELECT pembuat_id FROM tugas_rayhanRP WHERE tugas_id = ?', 'i', $id_item_rayhanrp));
                    if (!$data_tugas_rayhanrp || ($data_admin_rayhanrp['role'] === 'guru' && (int)$data_tugas_rayhanrp['pembuat_id'] !== (int)$data_admin_rayhanrp['id'])) {
                        continue;
                    }
                    sirey_execute('DELETE FROM tugas_perorang_rayhanRP WHERE tugas_id = ?', 'i', $id_item_rayhanrp);
                    sirey_execute('DELETE FROM tugas_rayhanRP WHERE tugas_id = ?', 'i', $id_item_rayhanrp);
                }
                auditLog($data_admin_rayhanrp['id'], 'bulk_delete_tugas', 'tugas', null, ['ids' => $daftar_id_rayhanrp]);
                $pesan_rayhanrp = 'Tugas terpilih berhasil dihapus.';
            }
        }
    }
}

$filter_judul_rayhanrp = trim((string)($_GET['filter_judul'] ?? ''));
$filter_id_grup_rayhanrp = (int)($_GET['filter_grup'] ?? 0);
$filter_status_rayhanrp = trim((string)($_GET['filter_status'] ?? ''));
$filter_id_matpel_rayhanrp = (int)($_GET['filter_matpel'] ?? 0);
$filter_id_guru_rayhanrp = (int)($_GET['filter_guru'] ?? 0);

$pernyataan_sql_rayhanrp = 'SELECT t.tugas_id, t.tipe_tugas, t.grup_id, g.nama_grup,
               t.judul, t.matpel_id, mp.kode AS matpel_kode, mp.nama AS matpel_nama,
               t.deskripsi, t.tenggat, t.poin_maksimal, t.status, t.izin_revisi, t.lampiran_url,
               t.pembuat_id, a.nama_lengkap AS pembuat_nama,
               (SELECT COUNT(*) FROM pengumpulan_rayhanRP WHERE tugas_id = t.tugas_id) AS jml_submit,
               (SELECT COUNT(*) FROM tugas_perorang_rayhanRP WHERE tugas_id = t.tugas_id) AS jml_penerima
        FROM tugas_rayhanRP t
        LEFT JOIN grup_rayhanRP g ON t.grup_id = g.grup_id
        LEFT JOIN akun_rayhanRP a ON t.pembuat_id = a.akun_id
        LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
        WHERE 1=1';
$tipe_rayhanrp = '';
$param_rayhanrp = [];

if ($data_admin_rayhanrp['role'] === 'guru') {
    $pernyataan_sql_rayhanrp .= ' AND t.pembuat_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $data_admin_rayhanrp['id'];
}

if ($filter_id_grup_rayhanrp > 0) {
    $pernyataan_sql_rayhanrp .= ' AND t.grup_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $filter_id_grup_rayhanrp;
}

if ($filter_judul_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND t.judul LIKE ?';
    $tipe_rayhanrp .= 's';
    $param_rayhanrp[] = '%' . $filter_judul_rayhanrp . '%';
}

if ($filter_status_rayhanrp !== '') {
    $pernyataan_sql_rayhanrp .= ' AND t.status = ?';
    $tipe_rayhanrp .= 's';
    $param_rayhanrp[] = $filter_status_rayhanrp;
}

if ($filter_id_matpel_rayhanrp > 0) {
    $pernyataan_sql_rayhanrp .= ' AND t.matpel_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $filter_id_matpel_rayhanrp;
}

if ($filter_id_guru_rayhanrp > 0 && in_array($data_admin_rayhanrp['role'], ['kepala_sekolah', 'kurikulum'], true)) {
    $pernyataan_sql_rayhanrp .= ' AND t.pembuat_id = ?';
    $tipe_rayhanrp .= 'i';
    $param_rayhanrp[] = $filter_id_guru_rayhanrp;
}

$pernyataan_sql_rayhanrp .= ' ORDER BY t.tenggat DESC';

$daftar_tugas_rayhanrp = $tipe_rayhanrp !== ''
    ? sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp, $tipe_rayhanrp, ...$param_rayhanrp))
    : sirey_fetchAll(sirey_query($pernyataan_sql_rayhanrp));

$daftar_grup_rayhanrp = $data_admin_rayhanrp['role'] === 'guru'
    ? getGrupDiajarGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT grup_id, nama_grup, jurusan FROM grup_rayhanRP WHERE aktif = 1 ORDER BY nama_grup ASC'));

$daftar_matpel_rayhanrp = $data_admin_rayhanrp['role'] === 'guru'
    ? getMatpelGuru($data_admin_rayhanrp['id'])
    : sirey_fetchAll(sirey_query('SELECT matpel_id, kode, nama FROM mata_pelajaran_rayhanRP WHERE aktif = 1 ORDER BY nama ASC'));

$daftar_guru_rayhanrp = in_array($data_admin_rayhanrp['role'], ['kepala_sekolah', 'kurikulum'], true)
    ? sirey_fetchAll(sirey_query('SELECT akun_id, nama_lengkap FROM akun_rayhanRP WHERE role = "guru" AND aktif = 1 ORDER BY nama_lengkap ASC'))
    : [];

// Untuk guru: ambil siswa dari kelas yang diajar guru
// Untuk non-guru: ambil semua siswa untuk flexible assignment
if ($data_admin_rayhanrp['role'] === 'guru') {
    $daftar_siswa_rayhanrp = [];
    // Siswa akan di-load dynamically via AJAX berdasarkan matpel yang dipilih
} else {
    $daftar_siswa_rayhanrp = sirey_fetchAll(sirey_query(
        'SELECT a.akun_id, a.nama_lengkap, g.nama_grup
         FROM akun_rayhanRP a
         LEFT JOIN grup_anggota_rayhanRP ga_utama
                ON ga_utama.akun_id = a.akun_id
               AND ga_utama.tipe_keanggotaan = "utama"
               AND ga_utama.aktif = 1
         LEFT JOIN grup_rayhanRP g ON ga_utama.grup_id = g.grup_id
         WHERE a.role = "siswa"
         ORDER BY a.nama_lengkap ASC'
    ));
}

$now = new DateTimeImmutable();
?>

<div class="page-header">
  <h2>Manajemen Tugas</h2>
  <p><?php echo $data_admin_rayhanrp['role'] === 'guru' ? 'Guru hanya melihat dan mengelola tugas miliknya sendiri.' : 'Kelola tugas grup dan perorangan.'; ?></p>
</div>

<?php if ($pesan_rayhanrp !== ''): ?>
  <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_rayhanrp); ?></div>
<?php endif; ?>
<?php if ($error_rayhanrp !== ''): ?>
  <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_rayhanrp); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h3>Filter Tugas</h3></div>
  <form method="GET" style="display:grid; grid-template-columns:1.2fr 1fr 1fr 1fr auto auto; gap:12px; align-items:flex-end;">
    <div class="form-group" style="margin:0;">
      <label class="form-label">Judul</label>
      <input type="text" name="filter_judul" class="form-control" value="<?php echo htmlspecialchars($filter_judul_rayhanrp); ?>" placeholder="Cari judul">
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Kelas</label>
      <select name="filter_grup" class="form-control">
        <option value="">Semua kelas</option>
        <?php foreach ($daftar_grup_rayhanrp as $item_grup_rayhanrp): ?>
          <option value="<?php echo (int)$item_grup_rayhanrp['grup_id']; ?>" <?php echo $filter_id_grup_rayhanrp === (int)$item_grup_rayhanrp['grup_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$item_grup_rayhanrp['nama_grup']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Status</label>
      <select name="filter_status" class="form-control">
        <option value="">Semua status</option>
        <?php foreach (['draft', 'active', 'closed'] as $status): ?>
          <option value="<?php echo $status; ?>" <?php echo $filter_status_rayhanrp === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Mapel</label>
      <select name="filter_matpel" class="form-control">
        <option value="">Semua mapel</option>
        <?php foreach ($daftar_matpel_rayhanrp as $item_matpel_rayhanrp): ?>
          <option value="<?php echo (int)$item_matpel_rayhanrp['matpel_id']; ?>" <?php echo $filter_id_matpel_rayhanrp === (int)$item_matpel_rayhanrp['matpel_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($item_matpel_rayhanrp['kode'] . ' - ' . $item_matpel_rayhanrp['nama']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (in_array($data_admin_rayhanrp['role'], ['kepala_sekolah', 'kurikulum'], true)): ?>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Guru</label>
      <select name="filter_guru" class="form-control">
        <option value="">Semua guru</option>
        <?php foreach ($daftar_guru_rayhanrp as $item_guru_rayhanrp): ?>
          <option value="<?php echo (int)$item_guru_rayhanrp['akun_id']; ?>" <?php echo $filter_id_guru_rayhanrp === (int)$item_guru_rayhanrp['akun_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars((string)$item_guru_rayhanrp['nama_lengkap']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="tugas.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<?php if ($bisa_tulis_rayhanrp): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Buat Tugas</h3></div>
    <form method="POST" id="createTaskForm">
      <input type="hidden" name="act" value="create">

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label">Jenis</label>
          <select name="tipe_tugas" id="tipe_tugas" class="form-control" onchange="toggleTaskType()">
            <option value="grup">Grup</option>
            <option value="perorang">Perorangan</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mata Pelajaran *</label>
          <select name="matpel_id" id="create_matpel_id" class="form-control" onchange="loadKelasByMatpel(this.value, 'create')" required>
            <option value="">Pilih mapel</option>
            <?php foreach ($daftar_matpel_rayhanrp as $item_matpel_rayhanrp): ?>
              <option value="<?php echo (int)$item_matpel_rayhanrp['matpel_id']; ?>"><?php echo htmlspecialchars($item_matpel_rayhanrp['kode'] . ' - ' . $item_matpel_rayhanrp['nama']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="createGrupField" style="display:none;">
          <label class="form-label">Kelas untuk Mapel Ini</label>
          <select name="grup_id" id="create_grup_id" class="form-control" disabled>
            <option value="">Pilih mapel terlebih dahulu</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status Awal</label>
          <select name="status" class="form-control">
            <option value="active">Aktif</option>
            <option value="draft">Draft</option>
          </select>
        </div>
      </div>

      <div id="createScopeField" style="display:none; margin-bottom:14px;">
        <label class="form-label">Cakupan Pengajaran untuk Mapel Ini</label>
        <div id="createScopeSummary" style="display:flex; flex-wrap:wrap; gap:8px; padding:12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; min-height:48px;"></div>
      </div>

      <div class="form-group" id="recipientField" style="display:none;">
        <label class="form-label">Penerima Tugas</label>
        <div id="recipientContainer" style="max-height:220px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
          <?php if ($data_admin_rayhanrp['role'] === 'guru'): ?>
            <p style="color:#7c3aed; font-size:0.9em; margin:0;">Pilih mapel dan tipe perorangan untuk menampilkan daftar siswa dari kelas Anda.</p>
          <?php else: ?>
            <?php foreach ($daftar_siswa_rayhanrp as $item_siswa_rayhanrp): ?>
              <label style="display:block; margin-bottom:8px;">
                <input type="checkbox" name="recipient_ids[]" value="<?php echo (int)$item_siswa_rayhanrp['akun_id']; ?>">
                <?php echo htmlspecialchars($item_siswa_rayhanrp['nama_lengkap'] . (!empty($item_siswa_rayhanrp['nama_grup']) ? ' (' . $item_siswa_rayhanrp['nama_grup'] . ')' : '')); ?>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:14px; margin-bottom:14px;">
        <div class="form-group">
          <label class="form-label">Judul</label>
          <input type="text" name="judul" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tenggat</label>
          <input type="datetime-local" name="tenggat" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nilai Maksimal</label>
          <input type="number" name="poin_maksimal" class="form-control" min="1" max="100" value="100">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="deskripsi" class="form-control" rows="4"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Lampiran URL</label>
        <input type="url" name="lampiran_url" class="form-control" placeholder="https://...">
      </div>

      <button type="submit" class="btn btn-primary">Buat Tugas</button>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3>Daftar Tugas (<?php echo count($daftar_tugas_rayhanrp); ?>)</h3>
    <?php if ($bisa_tulis_rayhanrp): ?>
      <button type="button" id="bulkDeleteBtn" class="btn btn-danger btn-sm" style="display:none;" onclick="bulkDeleteTasks()">Hapus Terpilih</button>
    <?php endif; ?>
  </div>

  <?php if (empty($daftar_tugas_rayhanrp)): ?>
    <div class="empty-state">
      <div class="empty-icon">Tugas</div>
      <p>Belum ada tugas.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <?php if ($bisa_tulis_rayhanrp): ?><th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th><?php endif; ?>
          <th>Judul</th>
          <th>Mapel</th>
          <th>Tujuan</th>
          <th>Deadline</th>
          <th>Status</th>
          <th>Kumpul</th>
          <th>Revisi</th>
          <?php if ($bisa_tulis_rayhanrp): ?><th>Aksi</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftar_tugas_rayhanrp as $item_tugas_rayhanrp): ?>
          <?php $isOverdue = strtotime((string)$item_tugas_rayhanrp['tenggat']) < $now->getTimestamp(); ?>
          <tr>
            <?php if ($bisa_tulis_rayhanrp): ?>
              <td><input type="checkbox" class="taskCheckbox" value="<?php echo (int)$item_tugas_rayhanrp['tugas_id']; ?>" onchange="updateBulkState()"></td>
            <?php endif; ?>
            <td>
              <strong><?php echo htmlspecialchars((string)$item_tugas_rayhanrp['judul']); ?></strong>
              <?php if (!empty($item_tugas_rayhanrp['lampiran_url'])): ?>
                <br><small><a href="<?php echo htmlspecialchars((string)$item_tugas_rayhanrp['lampiran_url']); ?>" target="_blank" rel="noreferrer">Lampiran</a></small>
              <?php endif; ?>
            </td>
            <td>
              <?php echo htmlspecialchars((string)($item_tugas_rayhanrp['matpel_nama'] ?? '-')); ?>
              <?php if (!empty($item_tugas_rayhanrp['matpel_kode'])): ?>
                <br><small><?php echo htmlspecialchars((string)$item_tugas_rayhanrp['matpel_kode']); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($item_tugas_rayhanrp['tipe_tugas'] === 'grup'): ?>
                <?php echo htmlspecialchars((string)($item_tugas_rayhanrp['nama_grup'] ?? '-')); ?>
              <?php else: ?>
                <?php echo (int)$item_tugas_rayhanrp['jml_penerima']; ?> siswa
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap; color:<?php echo $isOverdue && $item_tugas_rayhanrp['status'] === 'active' ? '#b91c1c' : 'inherit'; ?>;">
              <?php echo formatDatetime((string)$item_tugas_rayhanrp['tenggat']); ?>
            </td>
            <td><span class="badge badge-default"><?php echo htmlspecialchars((string)$item_tugas_rayhanrp['status']); ?></span></td>
            <td>
              <button type="button" class="btn btn-info btn-sm" onclick="openSubmissionsModal(<?php echo (int)$item_tugas_rayhanrp['tugas_id']; ?>, <?php echo json_encode((string)$item_tugas_rayhanrp['judul']); ?>)">
                <?php echo (int)$item_tugas_rayhanrp['jml_submit']; ?>
              </button>
            </td>
            <td>
              <?php if ($bisa_tulis_rayhanrp && ($data_admin_rayhanrp['role'] !== 'guru' || (int)$item_tugas_rayhanrp['pembuat_id'] === (int)$data_admin_rayhanrp['id'])): ?>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRevision(<?php echo (int)$item_tugas_rayhanrp['tugas_id']; ?>, this)"><?php echo (int)$item_tugas_rayhanrp['izin_revisi'] === 1 ? 'Ya' : 'Tidak'; ?></button>
              <?php else: ?>
                <?php echo (int)$item_tugas_rayhanrp['izin_revisi'] === 1 ? 'Ya' : 'Tidak'; ?>
              <?php endif; ?>
            </td>
            <?php if ($bisa_tulis_rayhanrp): ?>
              <td style="white-space:nowrap;">
                <?php if ($data_admin_rayhanrp['role'] !== 'guru' || (int)$item_tugas_rayhanrp['pembuat_id'] === (int)$data_admin_rayhanrp['id']): ?>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="toggleStatus(<?php echo (int)$item_tugas_rayhanrp['tugas_id']; ?>)">Toggle</button>
                  <button type="button" class="btn btn-info btn-sm" onclick='openEditModal(<?php echo json_encode($item_tugas_rayhanrp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus tugas ini?')">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$item_tugas_rayhanrp['tugas_id']; ?>">
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

<?php if ($bisa_tulis_rayhanrp): ?>
  <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:28px; border-radius:8px; width:min(760px, 92vw); max-height:90vh; overflow:auto;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">Edit Tugas</h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditModal()">Tutup</button>
      </div>
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="tipe_tugas" value="grup">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
          <div class="form-group">
            <label class="form-label">Mapel *</label>
            <select name="matpel_id" id="edit_matpel_id" class="form-control" onchange="loadKelasByMatpel(this.value, 'edit')" required>
              <option value="">Pilih mapel</option>
              <?php foreach ($daftar_matpel_rayhanrp as $item_matpel_rayhanrp): ?>
                <option value="<?php echo (int)$item_matpel_rayhanrp['matpel_id']; ?>"><?php echo htmlspecialchars($item_matpel_rayhanrp['kode'] . ' - ' . $item_matpel_rayhanrp['nama']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="editGrupField" style="display:none;">
            <label class="form-label">Kelas untuk Mapel Ini</label>
            <select name="grup_id" id="edit_grup_id" class="form-control" disabled>
              <option value="">Pilih mapel terlebih dahulu</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-control">
              <option value="draft">Draft</option>
              <option value="active">Aktif</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
        <div id="editScopeField" style="display:none; margin-bottom:14px;">
          <label class="form-label">Cakupan Pengajaran untuk Mapel Ini</label>
          <div id="editScopeSummary" style="display:flex; flex-wrap:wrap; gap:8px; padding:12px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; min-height:48px;"></div>
        </div>
        <div style="display:grid; grid-template-columns:1.5fr 1fr 1fr; gap:14px;">
          <div class="form-group">
            <label class="form-label">Judul</label>
            <input type="text" name="judul" id="edit_judul" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tenggat</label>
            <input type="datetime-local" name="tenggat" id="edit_tenggat" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai Maks</label>
            <input type="number" name="poin_maksimal" id="edit_poin" class="form-control" min="1" max="100">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deskripsi</label>
          <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="4"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Lampiran URL</label>
          <input type="url" name="lampiran_url" id="edit_lampiran" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </form>
    </div>
  </div>

  <div id="submissionsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:28px; border-radius:8px; width:min(920px, 94vw); max-height:90vh; overflow:auto;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h3 style="margin:0;">Pengumpulan: <span id="submissionsTitle"></span></h3>
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeSubmissionsModal()">Tutup</button>
      </div>
      <div id="submissionsContent">Memuat...</div>
    </div>
  </div>

  <script>
    function toggleTaskType() {
      const tipe = document.getElementById('tipe_tugas').value;
      const recipientField = document.getElementById('recipientField');
      const groupField = document.getElementById('createGrupField');
      const groupSelect = document.getElementById('create_grup_id');
      
      if (recipientField) {
        recipientField.style.display = tipe === 'perorang' ? 'block' : 'none';
        
        // Load siswa jika tipe berubah ke perorang dan ada matpel yang dipilih
        if (tipe === 'perorang') {
          const matpelId = document.getElementById('create_matpel_id')?.value;
          if (matpelId) {
            loadKelasByMatpel(matpelId, 'create');
          }
        }
      }
      
      if (groupField) {
        const hasMapel = document.getElementById('create_matpel_id')?.value !== '';
        groupField.style.display = tipe === 'grup' && hasMapel ? 'block' : 'none';
      }
      
      if (groupSelect) {
        groupSelect.required = tipe === 'grup' && !groupSelect.disabled;
      }
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function getScopeDom(prefix) {
      return {
        select: document.getElementById(prefix + '_grup_id'),
        field: document.getElementById(prefix === 'create' ? 'createGrupField' : 'editGrupField'),
        wrap: document.getElementById(prefix === 'create' ? 'createScopeField' : 'editScopeField'),
        summary: document.getElementById(prefix === 'create' ? 'createScopeSummary' : 'editScopeSummary'),
      };
    }

    function resetScope(prefix, message = 'Pilih mapel untuk memuat kelas yang tersedia.') {
      const dom = getScopeDom(prefix);
      if (!dom.select || !dom.field || !dom.wrap || !dom.summary) return;

      dom.select.innerHTML = '<option value="">Pilih mapel terlebih dahulu</option>';
      dom.select.disabled = true;
      dom.select.required = false;
      dom.summary.innerHTML = '<span style="color:#64748b; font-size:13px;">' + escapeHtml(message) + '</span>';
      dom.wrap.style.display = 'none';

      if (prefix === 'create') {
        toggleTaskType();
      } else {
        dom.field.style.display = 'none';
      }
    }

    function renderScope(prefix, items, selectedValue = '') {
      const dom = getScopeDom(prefix);
      if (!dom.select || !dom.field || !dom.wrap || !dom.summary) return;

      dom.wrap.style.display = 'block';
      dom.select.innerHTML = '<option value="">Pilih kelas</option>';

      if (!items.length) {
        dom.select.disabled = true;
        dom.select.required = false;
        dom.summary.innerHTML = '<span style="color:#b91c1c; font-size:13px;">Belum ada assignment aktif untuk mapel ini.</span>';
        if (prefix === 'create') {
          toggleTaskType();
        } else {
          dom.field.style.display = 'block';
        }
        return;
      }

      dom.select.disabled = false;
      dom.select.required = true;
      dom.summary.innerHTML = items.map((item) => {
        const guruLabel = item.guru_nama
          ? '<small style="display:block; color:#64748b;">Guru: ' + escapeHtml(item.guru_nama) + '</small>'
          : '';
        return '<div style="padding:8px 10px; border-radius:999px; background:#e2e8f0; color:#1e293b; font-size:12px;">'
          + '<strong>' + escapeHtml(item.nama_grup) + '</strong>'
          + guruLabel
          + '</div>';
      }).join('');

      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.grup_id;
        option.textContent = item.nama_grup + (item.guru_nama ? ' • ' + item.guru_nama : '');
        dom.select.appendChild(option);
      });

      if (selectedValue) {
        dom.select.value = String(selectedValue);
      }

      if (prefix === 'create') {
        toggleTaskType();
      } else {
        dom.field.style.display = 'block';
      }
    }

    function loadKelasByMatpel(matpelId, prefix = 'create', selectedValue = '') {
      const trimmed = String(matpelId || '').trim();
      if (trimmed === '') {
        resetScope(prefix);
        return Promise.resolve();
      }

      // Determine task type
      const tipeSelect = document.getElementById('tipe_tugas') || document.getElementById('edit_tipe_tugas');
      const tipe = tipeSelect ? tipeSelect.value : 'grup';

      // If task type is perorang (individual), load students instead
      if (tipe === 'perorang') {
        return fetch('tugas.php?action=get_siswa_by_matpel&matpel_id=' + encodeURIComponent(trimmed))
          .then((response) => response.json())
          .then((data) => {
            renderRecipients(data.items || []);
          })
          .catch(() => {
            const container = document.getElementById('recipientContainer');
            if (container) {
              container.innerHTML = '<p style="color:#b91c1c;">Gagal memuat daftar siswa.</p>';
            }
          });
      }

      // Otherwise load classes (grup)
      return fetch('tugas.php?action=get_kelas_by_matpel&matpel_id=' + encodeURIComponent(trimmed))
        .then((response) => response.json())
        .then((data) => {
          renderScope(prefix, data.items || [], selectedValue);
        })
        .catch(() => {
          resetScope(prefix, 'Gagal memuat cakupan kelas untuk mapel ini.');
        });
    }

    function renderRecipients(items = []) {
      const container = document.getElementById('recipientContainer');
      if (!container) return;

      if (!items.length) {
        container.innerHTML = '<p style="color:#b91c1c;">Tidak ada siswa untuk dipilih di kelas Anda.</p>';
        return;
      }

      let html = '';
      items.forEach((siswa) => {
        const akun_id = parseInt(siswa.akun_id, 10);
        const nama = escapeHtml(siswa.nama_lengkap);
        const grup = siswa.nama_grup ? ' (' + escapeHtml(siswa.nama_grup) + ')' : '';
        
        html += '<label style="display:block; margin-bottom:8px;">' +
          '<input type="checkbox" name="recipient_ids[]" value="' + akun_id + '">' +
          nama + grup +
          '</label>';
      });
      container.innerHTML = html;
    }

    function toggleSelectAll(source) {
      document.querySelectorAll('.taskCheckbox').forEach((checkbox) => {
        checkbox.checked = source.checked;
      });
      updateBulkState();
    }

    function updateBulkState() {
      const selected = document.querySelectorAll('.taskCheckbox:checked').length;
      const button = document.getElementById('bulkDeleteBtn');
      if (button) {
        button.style.display = selected > 0 ? 'inline-flex' : 'none';
      }
    }

    function bulkDeleteTasks() {
      const ids = Array.from(document.querySelectorAll('.taskCheckbox:checked')).map((el) => el.value);
      if (!ids.length) return;
      if (!confirm('Hapus ' + ids.length + ' tugas?')) return;

      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = '<input type="hidden" name="act" value="bulk_delete">';
      ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();
    }

    function openEditModal(task) {
      document.getElementById('edit_id').value = task.tugas_id || '';
      document.getElementById('edit_matpel_id').value = task.matpel_id || '';
      document.getElementById('edit_status').value = task.status || 'draft';
      document.getElementById('edit_judul').value = task.judul || '';
      document.getElementById('edit_tenggat').value = (task.tenggat || '').replace(' ', 'T').substring(0, 16);
      document.getElementById('edit_poin').value = task.poin_maksimal || 100;
      document.getElementById('edit_deskripsi').value = task.deskripsi || '';
      document.getElementById('edit_lampiran').value = task.lampiran_url || '';
      document.getElementById('editModal').style.display = 'flex';
      loadKelasByMatpel(task.matpel_id || '', 'edit', task.grup_id || '');
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function toggleStatus(taskId) {
      fetch('tugas.php?action=toggle_status&tugas_id=' + taskId)
        .then((response) => response.json())
        .then((data) => {
          if (data.success) window.location.reload();
        });
    }

    function toggleRevision(taskId) {
      fetch('tugas.php?action=toggle_revision&tugas_id=' + taskId)
        .then((response) => response.json())
        .then((data) => {
          if (data.success) window.location.reload();
        });
    }

    function openSubmissionsModal(taskId, title) {
      document.getElementById('submissionsTitle').textContent = title;
      document.getElementById('submissionsContent').textContent = 'Memuat...';
      document.getElementById('submissionsModal').style.display = 'flex';

      fetch('tugas.php?action=get_submissions&tugas_id=' + taskId)
        .then((response) => response.text())
        .then((html) => {
          document.getElementById('submissionsContent').innerHTML = html;
        });
    }

    function closeSubmissionsModal() {
      document.getElementById('submissionsModal').style.display = 'none';
    }

    document.getElementById('editModal')?.addEventListener('click', function (event) {
      if (event.target === this) closeEditModal();
    });

    document.getElementById('submissionsModal')?.addEventListener('click', function (event) {
      if (event.target === this) closeSubmissionsModal();
    });

    resetScope('create');
    resetScope('edit');
    toggleTaskType();
  </script>
<?php endif; ?>

<?php layoutEnd(); ?>
