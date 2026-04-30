<?php
declare(strict_types=1);

/**
 * Tangani semua tombol menu utama.
 * Return true jika sudah ditangani.
 */
function handleMenu(string $text, int $chat, array $user): bool {
    $role = $user['role'];
    $uid  = (int)$user['akun_id'];

    // ── JADWAL HARI INI ─────────────────────────────────────────────────────
    if ($text === '📅 Jadwal Hari Ini') {
        $hari   = hariIni();
        $tgl    = date('d/m/Y');
        $jadwal = $role === 'guru'
            ? getJadwalGuruHari($uid, $hari)
            : getJadwalHari($uid, $hari);

        if (empty($jadwal)) {
            $pesan = "📅 *Jadwal Hari Ini*\n\n_(Tidak ada jadwal {$hari} ini)_";
        } else {
            $pesan = formatJadwalHari($jadwal, $hari, $tgl);
        }
        sendMsg($chat, $pesan, mainKeyboard($role));
        return true;
    }

    // ── JADWAL MINGGU INI ───────────────────────────────────────────────────
    if ($text === '📆 Jadwal Minggu Ini') {
        $hariList = daftarHari();
        $pesan    = "📆 *Jadwal Minggu Ini*\n\n";
        $kosong   = true;

        // Hitung tanggal Senin minggu ini
        $senin  = new DateTime();
        $dow    = (int)$senin->format('N'); // 1=Senin, 7=Minggu
        $senin->modify('-' . ($dow - 1) . ' days');

        foreach ($hariList as $i => $hari) {
            $tgl    = (clone $senin)->modify("+{$i} days")->format('d/m');
            $jadwal = $role === 'guru'
                ? getJadwalGuruHari($uid, $hari)
                : getJadwalHari($uid, $hari);

            if (empty($jadwal)) continue;
            $kosong = false;

            $pesan .= "━━ *{$hari} ({$tgl})* ━━\n";
            foreach ($jadwal as $j) {
                $mulai   = substr($j['jam_mulai'],   0, 5);
                $selesai = substr($j['jam_selesai'], 0, 5);
                $pesan  .= "  🕐 {$mulai}–{$selesai} | {$j['matpel']}";
                if ($role !== 'guru' && !empty($j['guru_nama'])) {
                    $pesan .= " ({$j['guru_nama']})";
                }
                if ($role === 'guru' && !empty($j['nama_grup'])) {
                    $pesan .= " | {$j['nama_grup']}";
                }
                $pesan .= "\n";
            }
            $pesan .= "\n";
        }

        if ($kosong) $pesan .= "_(Tidak ada jadwal minggu ini)_";

        sendMsg($chat, rtrim($pesan), mainKeyboard($role));
        return true;
    }

    // ── PENGUMUMAN ──────────────────────────────────────────────────────────
    if ($text === '📢 Pengumuman') {
        $list  = getPengumuman($uid);
        $pesan = "📢 *Pengumuman Terbaru*\n\n";

        if (empty($list)) {
            $pesan .= "_(Belum ada pengumuman)_";
        } else {
            foreach ($list as $p) {
                $tgl    = date('d/m/Y', strtotime($p['dibuat_pada']));
                $pesan .= "📌 *{$p['judul']}*\n";
                $pesan .= "{$p['isi']}\n";
                $pesan .= "_— {$p['pembuat']}, {$tgl}_\n\n";
            }
        }

        sendMsg($chat, $pesan, mainKeyboard($role));
        return true;
    }

    // ── TUGAS ───────────────────────────────────────────────────────────────
    if ($text === '📝 Tugas') {
        if ($role === 'guru') {
            $list  = getTugasGuru($uid);
            $pesan = "📝 *Tugas yang Anda Buat*\n\n";

            if (empty($list)) {
                $pesan .= "_(Belum ada tugas aktif)_";
            } else {
                foreach ($list as $t) {
                    $tgl     = date('d/m/Y', strtotime($t['tenggat']));
                    $kumpul  = (int)$t['jml_kumpul'];
                    $pesan  .= "📌 *{$t['judul']}*\n";
                    $pesan  .= "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n";
                    $pesan  .= "   📅 Deadline: {$tgl}\n";
                    $pesan  .= "   📊 Pengumpulan: {$kumpul} siswa\n\n";
                }
            }
        } else {
            $list  = getTugasSiswa($uid);
            $pesan = "📝 *Tugas Anda*\n\n";

            if (empty($list)) {
                $pesan .= "_(Tidak ada tugas aktif)_";
            } else {
                foreach ($list as $t) {
                    $tgl     = date('d/m/Y H:i', strtotime($t['tenggat']));
                    $status  = (int)$t['sudah_kumpul'] > 0 ? '✅ Sudah' : '⏳ Belum';
                    $sisa    = sisa_waktu($t['tenggat']);
                    $pesan  .= "📌 *{$t['judul']}*\n";
                    $pesan  .= "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n";
                    $pesan  .= "   📅 Deadline: {$tgl} _(sisa {$sisa})_\n";
                    $pesan  .= "   {$status} dikumpulkan\n\n";
                }
            }
        }

        sendMsg($chat, $pesan, mainKeyboard($role));
        return true;
    }

    // ── BUAT TUGAS (guru) ───────────────────────────────────────────────────
    if ($text === '✏️ Buat Tugas' && $role === 'guru') {
        $mapelList = getMatpelGuru($uid);
        if (empty($mapelList)) {
            sendMsg($chat, "❌ Anda belum memiliki assignment mapel. Hubungi admin.", mainKeyboard($role));
            return true;
        }
        $keyboard = array_chunk(array_map(fn($m) => $m['nama'], $mapelList), 2);
        $keyboard[] = ['🔙 Kembali'];

        setState($chat, ['step' => 'tugas_pilih_mapel']);
        sendMsg($chat, "✏️ *Buat Tugas Baru*\n\nPilih *mata pelajaran*:", $keyboard);
        return true;
    }

    // ── KIRIM PENGUMUMAN (guru) ─────────────────────────────────────────────
    if ($text === '📢 Kirim Pengumuman' && $role === 'guru') {
        $kelasList = getGrupAjarGuru($uid);
        if (empty($kelasList)) {
            sendMsg($chat, "❌ Anda belum mengajar kelas apapun. Hubungi admin.", mainKeyboard($role));
            return true;
        }

        $keyboard = array_chunk(array_map(fn($k) => $k['nama_grup'], $kelasList), 2);
        $keyboard[] = ['🔙 Kembali'];

        setState($chat, ['step' => 'pengumuman_pilih_kelas']);
        sendMsg($chat, "📢 *Kirim Pengumuman*\n\nPilih *kelas* penerima:", $keyboard);
        return true;
    }

    // ── KELAS SAYA (guru - kelas yang diwalikan) ────────────────────────────
    if ($text === '🎓 Kelas Saya' && $role === 'guru') {
        $kelasList = getKelasWaliKelas($uid);
        $pesan     = "🎓 *Kelas Saya (Wali Kelas)*\n\n";

        if (empty($kelasList)) {
            $pesan .= "_(Anda belum menjadi wali kelas apapun)_";
        } else {
            foreach ($kelasList as $k) {
                $statusWali = "👨‍🏫 Wali Kelas\n";
                $pct        = $k['jml_tugas_dibuat'] > 0
                    ? round(($k['jml_kumpul'] / ($k['jml_tugas_dibuat'] * max(1, $k['jml_siswa']))) * 100)
                    : 0;
                
                $pesan .= "━━━━━━━━━━━━━━━━━━\n";
                $pesan .= "🎓 *{$k['nama_grup']}*\n";
                $pesan .= "   📌 {$k['jurusan']}\n";
                $pesan .= "   {$statusWali}";
                $pesan .= "   👥 Jumlah Siswa: {$k['jml_siswa']}\n";
                $pesan .= "   📝 Tugas Aktif: {$k['jml_tugas_dibuat']}\n";
                $pesan .= "   📈 Rate Pengumpulan: {$pct}%\n";
                $pesan .= "\n";
            }
            $pesan .= "━━━━━━━━━━━━━━━━━━\n";
        }

        sendMsg($chat, $pesan, mainKeyboard($role));
        return true;
    }

    // ── ANALISIS TUGAS (guru) ───────────────────────────────────────────────
    if ($text === '📋 Analisis Tugas' && $role === 'guru') {
        $tugasList = getTugasAnalisisForGuru($uid);
        if (empty($tugasList)) {
            sendMsg($chat, "❌ Belum ada tugas aktif untuk dianalisis.", mainKeyboard($role));
            return true;
        }

        $pesan = "📋 *Analisis Tugas*\n\nDaftar tugas aktif yang Anda buat:\n\n";
        
        foreach ($tugasList as $idx => $t) {
            $no = $idx + 1;
            $tgl = date('d/m', strtotime($t['tenggat']));
            $waliKelas = $t['wali_kelas'] !== '-' ? "👨‍🏫 Wali: {$t['wali_kelas']}" : "";
            
            $pesan .= "*{$no}.* {$t['judul']}\n";
            $pesan .= "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n";
            $pesan .= "   📅 Deadline: {$tgl}\n";
            if ($waliKelas) $pesan .= "   {$waliKelas}\n";
            $pesan .= "\n";
        }

        $keyboard = array_map(fn($idx) => [(string)($idx + 1)], range(0, count($tugasList) - 1));
        // Group 2 per row
        $keyboardGrouped = [];
        foreach ($keyboard as $idx => $row) {
            if ($idx % 2 === 0) {
                $keyboardGrouped[] = $row;
            } else {
                $keyboardGrouped[count($keyboardGrouped) - 1][] = $row[0];
            }
        }
        $keyboardGrouped[] = ['🔙 Kembali'];

        setState($chat, ['step' => 'analisis_tugas_pilih', 'tugasList' => $tugasList]);
        sendMsg($chat, $pesan, $keyboardGrouped);
        return true;
    }

    // ── NILAI TUGAS (guru) ──────────────────────────────────────────────────
    if ($text === '⭐ Nilai Tugas' && $role === 'guru') {
        $tugasList = getTugasUntukNilai($uid);
        
        if (empty($tugasList)) {
            sendMsg($chat, "❌ Tidak ada tugas yang perlu dinilai.\n\nSemua tugas sudah dinilai atau belum ada pengumpulan.", mainKeyboard($role));
            return true;
        }

        $pesan = "⭐ *Nilai Tugas*\n\n";
        $pesan .= "Pilih tugas untuk menilai pengumpulan:\n\n";

        foreach ($tugasList as $idx => $t) {
            $no = $idx + 1;
            $belumDinilai = (int)$t['jml_pengumpulan'] - (int)$t['jml_sudah_dinilai'];
            $emoji = $belumDinilai > 0 ? '🔴' : '✅';
            $pesan .= "{$emoji} *{$no}.* {$t['judul']}\n"
                    . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                    . "   📊 {$t['jml_sudah_dinilai']}/{$t['jml_pengumpulan']} dinilai\n\n";
        }

        $keyboard = array_map(fn($idx) => [(string)($idx + 1)], range(0, count($tugasList) - 1));
        // Group 2 per row
        $keyboardGrouped = [];
        foreach ($keyboard as $idx => $row) {
            if ($idx % 2 === 0) {
                $keyboardGrouped[] = $row;
            } else {
                $keyboardGrouped[count($keyboardGrouped) - 1][] = $row[0];
            }
        }
        $keyboardGrouped[] = ['🔙 Kembali'];

        setState($chat, [
            'step'          => 'nilai_pilih_tugas',
            'tugas_list'    => $tugasList,
            'user_cache'    => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        sendMsg($chat, $pesan, $keyboardGrouped);
        return true;
    }

    // ── PENGATURAN ──────────────────────────────────────────────────────────
    if ($text === '⚙️ Pengaturan') {
        setState($chat, [
            'step'       => 'pengaturan_pilih_menu',
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]);
        $pesan = "⚙️ *Pengaturan*\n\nPilih pengaturan yang ingin diubah:";
        sendMsg($chat, $pesan, settingsKeyboard());
        return true;
    }

    // ── KUMPULKAN TUGAS (siswa) ─────────────────────────────────────────────
    if ($text === '🔄 Kumpulkan Tugas' && $role === 'siswa') {
        $daftarTugas = getTugasBelumDikumpul((int)$user['akun_id']);
        $daftarRevisi = getTugasRevisiPending((int)$user['akun_id']);
        
        if (empty($daftarTugas) && empty($daftarRevisi)) {
            sendMsg($chat, "✅ Semua tugas sudah dikumpulkan!\n\nAtau tidak ada tugas yang aktif.", mainKeyboard($role));
            return true;
        }

        $pesan = "🔄 *Kumpulkan Tugas*\n\n";
        
        // Gabungkan dan track tipe tugas (baru vs revisi)
        $daftarGabung = [];
        $no_urut = 0;
        
        // Tampilkan tugas revisi dulu (priority)
        if (!empty($daftarRevisi)) {
            $pesan .= "*📝 REVISI DIMINTA:*\n\n";
            foreach ($daftarRevisi as $t) {
                $no_urut++;
                $tgl = date('d/m', strtotime($t['tenggat']));
                $sisa = sisa_waktu($t['tenggat']);
                $pesan .= "*{$no_urut}.* {$t['judul']} *(REVISI)*\n"
                        . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                        . "   📅 {$tgl} | ⏳ {$sisa}\n";
                if (!empty($t['catatan_guru'])) {
                    $pesan .= "   💬 Catatan: {$t['catatan_guru']}\n";
                }
                $pesan .= "\n";
                $t['tipe'] = 'revisi';
                $daftarGabung[] = $t;
            }
        }
        
        // Tampilkan tugas baru
        if (!empty($daftarTugas)) {
            if (!empty($daftarRevisi)) {
                $pesan .= "\n*🆕 TUGAS BARU:*\n\n";
            } else {
                $pesan .= "Pilih nomor tugas:\n\n";
            }
            foreach ($daftarTugas as $t) {
                $no_urut++;
                $tgl = date('d/m', strtotime($t['tenggat']));
                $sisa = sisa_waktu($t['tenggat']);
                $pesan .= "*{$no_urut}.* {$t['judul']}\n"
                        . "   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n"
                        . "   📅 {$tgl} | ⏳ {$sisa}\n\n";
                $t['tipe'] = 'baru';
                $daftarGabung[] = $t;
            }
        }

        $keyboard = [];
        foreach ($daftarGabung as $idx => $t) {
            $no = $idx + 1;
            // Group 2 per row
            if ($idx % 2 === 0) {
                $keyboard[] = [(string)$no];
            } else {
                $keyboard[count($keyboard) - 1][] = (string)$no;
            }
        }
        $keyboard[] = ['🔙 Kembali'];

        setState($chat, [
            'step'          => 'kumpul_pilih_tugas',
            'daftar_tugas'  => $daftarGabung, // Cache daftar untuk lookup di next step
            'user_cache'    => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── LIHAT PENILAIAN (siswa) ────────────────────────────────────────────
    if ($text === '📊 Lihat Penilaian' && $role === 'siswa') {
        $nilaiList = getNilaiSiswa($uid);
        $pesan     = formatNilaiSiswa($nilaiList);

        sendMsg($chat, $pesan, mainKeyboard($role));
        return true;
    }

    // ── KEMBALI ke menu ─────────────────────────────────────────────────────
    if ($text === '🔙 Kembali') {
        setState($chat, [
            'step'       => 'menu',
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]);
        sendMsg($chat, "↩️ Menu utama:", mainKeyboard($role));
        return true;
    }

    // ── LOGOUT ──────────────────────────────────────────────────────────────
    if ($text === '🚪 Logout') {
        invalidateTelegramSession($chat);
        
        // Clear state
        setState($chat, null);
        
        // Kirim pesan logout
        sendMsgRemoveKeyboard(
            $chat,
            "✅ Anda berhasil logout.\n\nTerima kasih telah menggunakan Bot SiRey! 👋\n\nKetik /start untuk login kembali."
        );
        return true;
    }

    return false;
}


// ============================================================
// HELPER
// ============================================================

/**
 * Hitung sisa waktu dalam format manusiawi.
 */
function sisa_waktu(string $tenggat): string {
    $sisa = strtotime($tenggat) - time();
    if ($sisa <= 0) return "sudah lewat";
    if ($sisa < 3600) return round($sisa / 60) . " menit";
    if ($sisa < 86400) return round($sisa / 3600) . " jam";
    return round($sisa / 86400) . " hari";
}
