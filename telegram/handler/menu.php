<?php

declare(strict_types=1);

// ================================================================
// handler/menu.php
// Menangani semua tombol menu utama (Reply Keyboard).
// Mengembalikan true jika sudah ditangani.
// ================================================================

function handleMenu(string $text, int $chatId, array $user): bool
{
    $role = (string) $user['role'];
    $uid  = (int)    $user['akun_id'];

    // ── 📅 JADWAL HARI INI ───────────────────────────────────────
    if ($text === '📅 Jadwal Hari Ini') {
        $hari   = hariIni();
        $tanggal = date('d/m/Y');

        $jadwal = $role === 'guru'
            ? getJadwalGuruHari($uid, $hari)
            : getJadwalHari($uid, $hari);

        $pesan = formatJadwalHari($jadwal, $hari, $tanggal, $role);
        sendMsg($chatId, $pesan, mainKeyboard($role));
        return true;
    }

    // ── 📆 JADWAL MINGGU INI ─────────────────────────────────────
    if ($text === '📆 Jadwal Minggu Ini') {
        $daftarHari = daftarHari();
        $pesan      = "📆 *Jadwal Minggu Ini*\n";
        $pesan     .= str_repeat('─', 24) . "\n\n";
        $adaJadwal  = false;

        // Hitung tanggal Senin minggu ini
        $senin = new DateTime();
        $dow   = (int) $senin->format('N'); // 1=Senin ... 7=Minggu
        $senin->modify('-' . ($dow - 1) . ' days');

        foreach ($daftarHari as $i => $hari) {
            $tglHari = (clone $senin)->modify("+{$i} days")->format('d/m');

            $jadwal = $role === 'guru'
                ? getJadwalGuruHari($uid, $hari)
                : getJadwalHari($uid, $hari);

            if (empty($jadwal)) {
                continue;
            }

            $adaJadwal = true;
            $pesan    .= "📌 *{$hari}* ({$tglHari})\n";

            foreach ($jadwal as $j) {
                $mulai   = substr((string) $j['jam_mulai'],   0, 5);
                $selesai = substr((string) $j['jam_selesai'], 0, 5);
                $pesan  .= "  🕐 {$mulai}–{$selesai} | {$j['matpel']}";

                if ($role === 'siswa' && !empty($j['guru_nama'])) {
                    $pesan .= " ({$j['guru_nama']})";
                }

                if ($role === 'guru' && !empty($j['nama_grup'])) {
                    $pesan .= " | 🎓 {$j['nama_grup']}";
                }

                $pesan .= "\n";
            }

            $pesan .= "\n";
        }

        if (!$adaJadwal) {
            $pesan .= "_(Tidak ada jadwal minggu ini)_ 😴";
        }

        sendMsg($chatId, rtrim($pesan), mainKeyboard($role));
        return true;
    }

    // ── 📢 PENGUMUMAN ────────────────────────────────────────────
    if ($text === '📢 Pengumuman') {
        $list  = getPengumuman($uid);
        $pesan = "📢 *Pengumuman Terbaru*\n";
        $pesan .= str_repeat('─', 24) . "\n\n";

        if (empty($list)) {
            $pesan .= "_(Belum ada pengumuman)_ 📭";
        } else {
            foreach ($list as $p) {
                $tgl      = formatTgl((string) $p['dibuat_pada']);
                $prioIcon = match ((string) ($p['prioritas'] ?? 'biasa')) {
                    'penting' => '🔴',
                    'darurat' => '🚨',
                    default   => '📌',
                };

                $pesan .= "{$prioIcon} *{$p['judul']}*\n";
                $pesan .= potongTeks((string) $p['isi'], 200) . "\n";
                $pesan .= "_— {$p['pembuat']}, {$tgl}_\n\n";
            }
        }

        sendMsg($chatId, $pesan, mainKeyboard($role));
        return true;
    }

    // ── 📝 TUGAS ─────────────────────────────────────────────────
    if ($text === '📝 Tugas') {
        if ($role === 'guru') {
            _menuTugasGuru($chatId, $uid, $role);
        } else {
            _menuTugasSiswa($chatId, $uid, $role);
        }
        return true;
    }

    // ── ✏️ BUAT TUGAS (guru) ─────────────────────────────────────
    if ($text === '✏️ Buat Tugas' && $role === 'guru') {
        $mapelList = getMatpelGuru($uid);

        if (empty($mapelList)) {
            sendMsg(
                $chatId,
                "❌ Anda belum memiliki assignment mata pelajaran.\n\nHubungi admin untuk mengaturnya.",
                mainKeyboard($role)
            );
            return true;
        }

        $keyboard = array_chunk(
            array_map(fn($m) => $m['nama'], $mapelList),
            2
        );
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, [
            'step'        => 'tugas_pilih_mapel',
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);

        sendMsg(
            $chatId,
            "✏️ *Buat Tugas Baru*\n\nPilih *mata pelajaran* untuk tugas ini:",
            $keyboard
        );
        return true;
    }

    // ── 📢 KIRIM PENGUMUMAN (guru) ───────────────────────────────
    if ($text === '📢 Kirim Pengumuman' && $role === 'guru') {
        $kelasList = getGrupAjarGuru($uid);

        if (empty($kelasList)) {
            sendMsg(
                $chatId,
                "❌ Anda belum mengajar kelas manapun.\n\nHubungi admin untuk mengaturnya.",
                mainKeyboard($role)
            );
            return true;
        }

        $keyboard = array_chunk(
            array_map(fn($k) => $k['nama_grup'], $kelasList),
            2
        );
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, [
            'step'        => 'pengumuman_pilih_kelas',
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);

        sendMsg(
            $chatId,
            "📢 *Kirim Pengumuman*\n\nPilih *kelas* yang akan menerima pengumuman:",
            $keyboard
        );
        return true;
    }

    // ── 🎓 KELAS SAYA (guru – wali kelas) ───────────────────────
    if ($text === '🎓 Kelas Saya' && $role === 'guru') {
        $kelasList = getKelasWaliKelas($uid);
        $pesan     = "🎓 *Kelas yang Anda Walikan*\n";
        $pesan    .= str_repeat('─', 24) . "\n\n";

        if (empty($kelasList)) {
            $pesan .= "_(Anda belum menjadi wali kelas)_";
        } else {
            foreach ($kelasList as $k) {
                $pct    = (int) $k['jml_tugas_dibuat'] > 0 && (int) $k['jml_siswa'] > 0
                    ? round((int) $k['jml_kumpul'] / ((int) $k['jml_tugas_dibuat'] * (int) $k['jml_siswa']) * 100)
                    : 0;

                $pesan .= "🏫 *{$k['nama_grup']}*\n";
                $pesan .= "   📌 {$k['jurusan']}\n";
                $pesan .= "   👥 Siswa  : {$k['jml_siswa']} orang\n";
                $pesan .= "   📝 Tugas  : {$k['jml_tugas_dibuat']} aktif\n";
                $pesan .= "   📈 Kumpul : " . progressBar($pct, 100, 8) . "\n\n";
            }
        }

        sendMsg($chatId, $pesan, mainKeyboard($role));
        return true;
    }

    // ── 📋 ANALISIS TUGAS (guru) ─────────────────────────────────
    if ($text === '📋 Analisis Tugas' && $role === 'guru') {
        $tugasList = getTugasAnalisisForGuru($uid);

        if (empty($tugasList)) {
            sendMsg(
                $chatId,
                "📋 *Analisis Tugas*\n\n_(Belum ada tugas aktif untuk dianalisis)_",
                mainKeyboard($role)
            );
            return true;
        }

        $pesan = "📋 *Analisis Tugas*\n\nPilih nomor tugas:\n\n";

        foreach ($tugasList as $idx => $t) {
            $no  = $idx + 1;
            $tgl = date('d/m', strtotime((string) $t['tenggat']));
            $pesan .= "*{$no}.* {$t['judul']}\n"
                . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                . "   📅 Deadline: {$tgl}\n\n";
        }

        $keyboard = _buildNomorKeyboard(count($tugasList));
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, [
            'step'        => 'analisis_tugas_pilih',
            'tugas_list'  => $tugasList,
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);

        sendMsg($chatId, $pesan, $keyboard);
        return true;
    }

    // ── ⭐ NILAI TUGAS (guru) ────────────────────────────────────
    if ($text === '⭐ Nilai Tugas' && $role === 'guru') {
        $tugasList = getTugasUntukNilai($uid);

        if (empty($tugasList)) {
            sendMsg(
                $chatId,
                "⭐ *Nilai Tugas*\n\n"
                    . "Tidak ada pengumpulan yang perlu dinilai saat ini.\n\n"
                    . "_Semua tugas sudah dinilai atau belum ada siswa yang mengumpulkan._",
                mainKeyboard($role)
            );
            return true;
        }

        $pesan = "⭐ *Nilai Tugas*\n\nPilih tugas yang akan dinilai:\n\n";

        foreach ($tugasList as $idx => $t) {
            $no           = $idx + 1;
            $belumDinilai = (int) $t['jml_pengumpulan'] - (int) $t['jml_sudah_dinilai'];
            $emoji        = $belumDinilai > 0 ? '🔴' : '✅';
            $bar          = progressBar((int) $t['jml_sudah_dinilai'], (int) $t['jml_pengumpulan']);

            $pesan .= "{$emoji} *{$no}.* {$t['judul']}\n"
                . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                . "   📊 {$bar} ({$t['jml_sudah_dinilai']}/{$t['jml_pengumpulan']})\n\n";
        }

        $keyboard = _buildNomorKeyboard(count($tugasList));
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, [
            'step'        => 'nilai_pilih_tugas',
            'tugas_list'  => $tugasList,
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);

        sendMsg($chatId, $pesan, $keyboard);
        return true;
    }

    // ── 🔄 KUMPULKAN TUGAS (siswa) ───────────────────────────────
    if ($text === '🔄 Kumpulkan Tugas' && $role === 'siswa') {
        $tugasBaru   = getTugasBelumDikumpul($uid);
        $tugasRevisi = getTugasRevisiPending($uid);

        if (empty($tugasBaru) && empty($tugasRevisi)) {
            sendMsg(
                $chatId,
                "✅ *Semua tugas sudah dikumpulkan!*\n\n"
                    . "_Tidak ada tugas yang perlu dikumpulkan saat ini._\n\n"
                    . "Tetap semangat belajar! 🌟",
                mainKeyboard($role)
            );
            return true;
        }

        $pesan       = "🔄 *Kumpulkan Tugas*\n\nPilih nomor tugas:\n\n";
        $daftarGabung = [];
        $no           = 0;

        // Tampilkan revisi duluan (prioritas)
        if (!empty($tugasRevisi)) {
            $pesan .= "📝 *REVISI DIMINTA:*\n\n";
            foreach ($tugasRevisi as $t) {
                $no++;
                $sisa  = sisaWaktu((string) $t['tenggat']);
                $pesan .= "*{$no}.* ⚠️ {$t['judul']} *(REVISI)*\n"
                    . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                    . "   ⏳ {$sisa}";

                if (!empty($t['catatan_guru'])) {
                    $pesan .= "\n   💬 _{$t['catatan_guru']}_";
                }

                $pesan .= "\n\n";
                $t['tipe']    = 'revisi';
                $daftarGabung[] = $t;
            }
        }

        // Tampilkan tugas baru
        if (!empty($tugasBaru)) {
            if (!empty($tugasRevisi)) {
                $pesan .= "🆕 *TUGAS BARU:*\n\n";
            }

            foreach ($tugasBaru as $t) {
                $no++;
                $sisa  = sisaWaktu((string) $t['tenggat']);
                $pesan .= "*{$no}.* {$t['judul']}\n"
                    . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                    . "   ⏳ {$sisa}\n\n";
                $t['tipe']    = 'baru';
                $daftarGabung[] = $t;
            }
        }

        $keyboard = _buildNomorKeyboard(count($daftarGabung));
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, [
            'step'         => 'kumpul_pilih_tugas',
            'daftar_tugas' => $daftarGabung,
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'   => _buildUserCache($user),
        ]);

        sendMsg($chatId, $pesan, $keyboard);
        return true;
    }

    // ── 📊 LIHAT PENILAIAN (siswa) ───────────────────────────────
    if ($text === '📊 Lihat Penilaian' && $role === 'siswa') {
        $nilaiList = getNilaiSiswa($uid);
        $pesan     = formatNilaiSiswa($nilaiList);
        sendMsg($chatId, $pesan, mainKeyboard($role));
        return true;
    }

    // ── ⚙️ PENGATURAN ────────────────────────────────────────────
    if ($text === '⚙️ Pengaturan') {
        $jamNotif = getJamNotifikasi($uid);

        $pesan = "⚙️ *Pengaturan*\n\n"
            . "Jam notifikasi saat ini:\n"
            . ($role === 'guru' ? "📅 Jadwal : *{$jamNotif['jam_jadwal']}*\n\n" :
                "📅 Jadwal : *{$jamNotif['jam_jadwal']}*\n"
                .   "📝 Tugas  : *{$jamNotif['jam_tugas']}*\n\n")
            . "Pilih pengaturan yang ingin diubah:";

        setState($chatId, [
            'step'        => 'pengaturan_pilih_menu',
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);

        sendMsg($chatId, $pesan, settingsKeyboard());
        return true;
    }

    // ── 🔙 KEMBALI KE MENU ───────────────────────────────────────
    if ($text === '🔙 Kembali ke Menu') {
        setState($chatId, [
            'step'        => 'menu',
            'session_token' => getState($chatId)['session_token'] ?? '',
            'user_cache'  => _buildUserCache($user),
        ]);
        sendMsg($chatId, "↩️ Menu utama:", mainKeyboard($role));
        return true;
    }

    // ── 🚪 LOGOUT ────────────────────────────────────────────────
    if ($text === '🚪 Logout') {
        invalidateTelegramSession($chatId);
        setState($chatId, null);
        sendMsgRemoveKeyboard(
            $chatId,
            "✅ Anda berhasil *logout*.\n\n"
                . "Terima kasih telah menggunakan SKADACI BOT! 👋\n\n"
                . "Ketik /start untuk login kembali."
        );
        return true;
    }

    return false;
}


// ================================================================
// Helper privat (hanya dipakai di file ini)
// ================================================================

/**
 * Tampilkan menu tugas untuk guru.
 */
function _menuTugasGuru(int $chatId, int $uid, string $role): void
{
    $list  = getTugasGuru($uid);
    $pesan = "📝 *Tugas yang Anda Buat*\n";
    $pesan .= str_repeat('─', 24) . "\n\n";

    if (empty($list)) {
        $pesan .= "_(Belum ada tugas aktif)_";
    } else {
        foreach ($list as $t) {
            $tgl   = date('d/m/Y H:i', strtotime((string) $t['tenggat']));
            $sisa  = sisaWaktu((string) $t['tenggat']);
            $kumpul = (int) $t['jml_kumpul'];

            $pesan .= "📌 *{$t['judul']}*\n"
                . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                . "   📅 {$tgl} _({$sisa})_\n"
                . "   📬 {$kumpul} sudah mengumpulkan\n\n";
        }
    }

    sendMsg($chatId, $pesan, mainKeyboard($role));
}

/**
 * Tampilkan menu tugas untuk siswa.
 */
function _menuTugasSiswa(int $chatId, int $uid, string $role): void
{
    $list  = getTugasSiswa($uid);
    $pesan = "📝 *Tugas Anda*\n";
    $pesan .= str_repeat('─', 24) . "\n\n";

    if (empty($list)) {
        $pesan .= "_(Tidak ada tugas aktif saat ini)_ ✅";
    } else {
        foreach ($list as $t) {
            $tgl    = date('d/m/Y H:i', strtotime((string) $t['tenggat']));
            $sisa   = sisaWaktu((string) $t['tenggat']);
            $status = (int) $t['sudah_kumpul'] > 0 ? '✅ Sudah' : '⏳ Belum';

            $pesan .= "📌 *{$t['judul']}*\n"
                . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                . "   📅 {$tgl} _({$sisa})_\n"
                . "   {$status} dikumpulkan\n\n";
        }
    }

    sendMsg($chatId, $pesan, mainKeyboard($role));
}

/**
 * Buat keyboard nomor berjajar 2 per baris.
 * Contoh 5 item → [[1,2],[3,4],[5]]
 */
function _buildNomorKeyboard(int $jumlah): array
{
    $angka    = array_map('strval', range(1, $jumlah));
    $keyboard = [];

    foreach (array_chunk($angka, 2) as $baris) {
        $keyboard[] = $baris;
    }

    return $keyboard;
}

/**
 * Buat array user_cache dari data user.
 */
if (!function_exists('_buildUserCache')) {
    function _buildUserCache(array $user): array
    {
        return [
            'akun_id'      => $user['akun_id'],
            'nama_lengkap' => $user['nama_lengkap'],
            'role'         => $user['role'],
            'nis_nip'      => $user['nis_nip'],
        ];
    }
}
