<?php
declare(strict_types=1);

// ================================================================
// handler/state.php
// Menangani alur percakapan beruntun (state machine).
// Mengembalikan true jika step sudah ditangani.
// ================================================================

function handleState(string $step, string $text, int $chatId, array $state, ?array $user): bool
{
    // ── LOGIN: MINTA NIS/NIP ─────────────────────────────────────
    if ($step === 'ask_nis') {
        if ($text === '') {
            sendMsg($chatId, "Masukkan *NIS/NIP* Anda:");
            return true;
        }

        setState($chatId, ['step' => 'ask_password', 'nis' => $text]);
        sendMsg($chatId, "🔑 Masukkan *password* Anda:");
        return true;
    }

    // ── LOGIN: MINTA PASSWORD ────────────────────────────────────
    if ($step === 'ask_password') {
        $nis   = (string) ($state['nis'] ?? '');
        $akun  = sirey_fetch(sirey_query(
            'SELECT akun_id, nis_nip, password, role, nama_lengkap, aktif
             FROM akun_rayhanrp WHERE nis_nip = ? LIMIT 1',
            's',
            $nis
        ));

        $loginOk = $akun !== null
            && (int) $akun['aktif'] === 1
            && in_array($akun['role'], ['guru', 'siswa', 'admin', 'kurikulum', 'kepala_sekolah'], true)
            && verifyPassword($akun, $text);

        if (!$loginOk) {
            setState($chatId, ['step' => 'ask_nis']);
            sendMsg(
                $chatId,
                "❌ NIS/NIP atau password salah, atau akun tidak aktif.\n\n"
                . "Coba lagi. Masukkan *NIS/NIP* Anda:"
            );
            return true;
        }

        $sessionToken = createTelegramLoginSession((int) $akun['akun_id'], $chatId);
        $sapaan       = sapaanWaktu();
        $labelRole    = labelRole((string) $akun['role']);

        setState($chatId, [
            'step'          => 'menu',
            'session_token' => $sessionToken,
            'user_cache'    => [
                'akun_id'      => $akun['akun_id'],
                'nama_lengkap' => $akun['nama_lengkap'],
                'role'         => $akun['role'],
                'nis_nip'      => $akun['nis_nip'],
            ],
        ]);

        sendMsg(
            $chatId,
            "✅ *Login berhasil!*\n\n"
            . "{$sapaan}, *{$akun['nama_lengkap']}*! 👋\n"
            . "Role: _{$labelRole}_\n\n"
            . "Silakan pilih menu di bawah ini:",
            mainKeyboard((string) $akun['role'])
        );

        return true;
    }

    // Semua state di bawah ini memerlukan user sudah login
    if ($user === null) {
        return false;
    }

    $role = (string) $user['role'];
    $uid  = (int)    $user['akun_id'];

    // ── BUAT TUGAS: PILIH MAPEL ──────────────────────────────────
    if ($step === 'tugas_pilih_mapel') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $mapel = sirey_fetch(sirey_query(
            'SELECT matpel_id, nama FROM mata_pelajaran_rayhanrp WHERE nama = ? AND aktif = 1 LIMIT 1',
            's',
            $text
        ));

        if (!$mapel) {
            sendMsg($chatId, "❌ Mata pelajaran tidak valid. Pilih dari tombol yang tersedia.");
            return true;
        }

        $kelasList = getKelasGuruByMatpel($uid, (int) $mapel['matpel_id']);

        if (empty($kelasList)) {
            sendMsg($chatId, "❌ Anda tidak mengajar kelas manapun untuk mata pelajaran ini.");
            return true;
        }

        $keyboard   = array_chunk(array_map(fn($k) => $k['nama_grup'], $kelasList), 2);
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, array_merge($state, [
            'step'       => 'tugas_pilih_kelas',
            'matpel_id'  => (int) $mapel['matpel_id'],
            'matpel'     => $mapel['nama'],
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg($chatId, "Pilih *kelas* untuk tugas *{$mapel['nama']}*:", $keyboard);
        return true;
    }

    // ── BUAT TUGAS: PILIH KELAS ──────────────────────────────────
    if ($step === 'tugas_pilih_kelas') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $kelas = sirey_fetch(sirey_query(
            'SELECT g.grup_id, g.nama_grup
             FROM grup_rayhanrp g
             INNER JOIN guru_mengajar_rayhanrp gm ON g.grup_id = gm.grup_id
             WHERE g.nama_grup = ? AND gm.akun_id = ? AND gm.matpel_id = ? AND gm.aktif = 1 LIMIT 1',
            'sii',
            $text,
            $uid,
            (int) ($state['matpel_id'] ?? 0)
        ));

        if (!$kelas) {
            sendMsg($chatId, "❌ Kelas tidak valid. Pilih dari tombol yang tersedia.");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'       => 'tugas_input_judul',
            'grup_id'    => (int) $kelas['grup_id'],
            'kelas'      => $kelas['nama_grup'],
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsgRemoveKeyboard(
            $chatId,
            "📚 Mapel : *{$state['matpel']}*\n"
            . "🎓 Kelas : *{$kelas['nama_grup']}*\n\n"
            . "Masukkan *judul tugas*:"
        );

        return true;
    }

    // ── BUAT TUGAS: INPUT JUDUL ──────────────────────────────────
    if ($step === 'tugas_input_judul') {
        if (mb_strlen($text) < 3) {
            sendMsg($chatId, "❌ Judul terlalu pendek (minimal 3 karakter). Coba lagi:");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'       => 'tugas_input_deskripsi',
            'judul'      => $text,
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg($chatId, "Masukkan *deskripsi tugas*:\n\n_(Ketik `-` untuk melewati)_");
        return true;
    }

    // ── BUAT TUGAS: INPUT DESKRIPSI ──────────────────────────────
    if ($step === 'tugas_input_deskripsi') {
        $deskripsi = $text === '-' ? '' : $text;

        setState($chatId, array_merge($state, [
            'step'       => 'tugas_input_deadline',
            'deskripsi'  => $deskripsi,
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg(
            $chatId,
            "Masukkan *deadline* tugas:\n\n"
            . "Format: `DD/MM/YYYY HH:MM`\n"
            . "Contoh: `25/12/2025 23:59`"
        );

        return true;
    }

    // ── BUAT TUGAS: INPUT DEADLINE ───────────────────────────────
    if ($step === 'tugas_input_deadline') {
        $dt = DateTimeImmutable::createFromFormat('d/m/Y H:i', $text);

        if (!$dt) {
            sendMsg(
                $chatId,
                "❌ Format salah. Gunakan: `DD/MM/YYYY HH:MM`\n"
                . "Contoh: `25/12/2025 23:59`"
            );
            return true;
        }

        if ($dt->getTimestamp() < time()) {
            sendMsg($chatId, "❌ Deadline tidak boleh di masa lalu. Masukkan tanggal yang akan datang:");
            return true;
        }

        $preview = "✅ *Konfirmasi Tugas Baru*\n\n"
                 . "📚 Mapel    : {$state['matpel']}\n"
                 . "🎓 Kelas    : {$state['kelas']}\n"
                 . "📝 Judul    : {$state['judul']}\n"
                 . (($state['deskripsi'] ?? '') !== '' ? "📋 Deskripsi: {$state['deskripsi']}\n" : '')
                 . "📅 Deadline : " . $dt->format('d/m/Y H:i') . "\n\n"
                 . "Buat tugas ini?";

        setState($chatId, array_merge($state, [
            'step'       => 'tugas_konfirmasi',
            'tenggat'    => $dt->format('Y-m-d H:i:s'),
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg($chatId, $preview, [['✅ Ya, Buat', '❌ Batal']]);
        return true;
    }

    // ── BUAT TUGAS: KONFIRMASI ───────────────────────────────────
    if ($step === 'tugas_konfirmasi') {
        if ($text === '❌ Batal') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '✅ Ya, Buat') {
            $ok = simpanTugasBot([
                'grup_id'    => (int)    $state['grup_id'],
                'judul'      => (string) $state['judul'],
                'deskripsi'  => (string) ($state['deskripsi'] ?? ''),
                'matpel_id'  => (int)    $state['matpel_id'],
                'tenggat'    => (string) $state['tenggat'],
                'pembuat_id' => $uid,
            ]);

            _resetKeMenu($chatId, $user);

            if ($ok) {
                sendMsg(
                    $chatId,
                    "✅ *Tugas berhasil dibuat!*\n\nSiswa sudah mendapat notifikasi. 📬",
                    mainKeyboard($role)
                );
            } else {
                sendMsg($chatId, "❌ Gagal menyimpan tugas. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Ya, Buat* atau *❌ Batal*", [['✅ Ya, Buat', '❌ Batal']]);
        return true;
    }

    // ── LIHAT DAFTAR TUGAS: PILIH NOMOR ──────────────────────────
    if ($step === 'tugas_lihat_daftar') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $daftarTugas = (array) ($state['daftar_tugas'] ?? []);
        $no          = (int) $text;

        if ($no < 1 || $no > count($daftarTugas)) {
            sendMsg($chatId, "❌ Nomor tidak valid. Pilih dari keyboard di bawah.");
            return true;
        }

        $tugas = $daftarTugas[$no - 1];
        $tugas_id = (int) ($tugas['tugas_id'] ?? 0);
        
        if ($tugas_id <= 0) {
            sendMsg($chatId, "❌ Data tugas tidak valid.");
            return true;
        }

        // Ambil detail lengkap tugas
        $detail = getTugasDetail($tugas_id);
        if (!$detail) {
            sendMsg($chatId, "❌ Tugas tidak ditemukan.");
            return true;
        }

        $tgl        = date('d/m/Y H:i', strtotime((string) $detail['tenggat']));
        $sisa       = sisaWaktu((string) $detail['tenggat']);
        $poin       = (int) $detail['poin_maksimal'];
        $status     = (int) $tugas['sudah_kumpul'] > 0 ? '✅ Sudah' : '⏳ Belum';

        $pesan = "📝 *Tugas #{$no}*\n\n";
        $pesan .= "*Judul:* {$detail['judul']}\n\n";
        
        if (!empty($detail['deskripsi'])) {
            $pesan .= "*Deskripsi:*\n{$detail['deskripsi']}\n\n";
        }
        
        $pesan .= "📚 *Mata Pelajaran:* {$detail['matpel']}\n";
        $pesan .= "🎓 *Kelas:* {$detail['nama_grup']}\n";
        $pesan .= "📅 *Deadline:* {$tgl}\n";
        $pesan .= "⏳ *Sisa:* {$sisa}\n";
        
        if ($poin > 0) {
            $pesan .= "⭐ *Poin Maksimal:* {$poin}\n";
        }
        
        $pesan .= "\n{$status} dikumpulkan\n\n";
        
        // Kirim detail tugas
        sendMsg($chatId, $pesan, mainKeyboard((string) $user['role']));
        
        // Reset state langsung ke menu
        _resetKeMenu($chatId, $user);
        return true;
    }

    // ── KUMPULKAN TUGAS: PILIH TUGAS ─────────────────────────────
    if ($step === 'kumpul_pilih_tugas') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $daftarTugas = (array) ($state['daftar_tugas'] ?? []);
        $no          = (int) $text;

        if ($no < 1 || $no > count($daftarTugas)) {
            sendMsg($chatId, "❌ Nomor tidak valid. Pilih nomor dari daftar.");
            return true;
        }

        $tugas      = $daftarTugas[$no - 1];
        $tipeKumpul = (string) ($tugas['tipe'] ?? 'baru');
        $sisa       = sisaWaktu((string) $tugas['tenggat']);
        $tgl        = date('d/m/Y H:i', strtotime((string) $tugas['tenggat']));

        $pesan  = "📝 *{$tugas['judul']}*\n\n"
                . "📚 {$tugas['matpel']} | 🎓 {$tugas['nama_grup']}\n"
                . "📅 Deadline: {$tgl}\n"
                . "⏳ Sisa: {$sisa}\n\n";

        if ($tipeKumpul === 'revisi') {
            $pesan .= "⚠️ *INI ADALAH PENGUMPULAN REVISI*\n"
                    . "Perbaiki jawaban sesuai catatan guru.\n\n";
        }

        $pesan .= "Pilih cara pengumpulan:";

        setState($chatId, array_merge($state, [
            'step'        => 'kumpul_pilih_tipe',
            'tugas_id'    => (int) $tugas['tugas_id'],
            'tugas'       => $tugas,
            'tipe_kumpul' => $tipeKumpul,
            'user_cache'  => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, [['📄 Teks Jawaban', '📎 File/Foto'], ['🔙 Kembali ke Menu']]);
        return true;
    }

    // ── KUMPULKAN TUGAS: PILIH TIPE JAWABAN ──────────────────────
    if ($step === 'kumpul_pilih_tipe') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '📄 Teks Jawaban') {
            setState($chatId, array_merge($state, [
                'step'       => 'kumpul_input_teks',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsgRemoveKeyboard($chatId, "📄 Ketikkan *jawaban tugas* Anda di bawah ini:");
            return true;
        }

        if ($text === '📎 File/Foto') {
            setState($chatId, array_merge($state, [
                'step'       => 'kumpul_input_file',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsg(
                $chatId,
                "📎 Kirim *file atau foto* jawaban Anda.\n\n"
                . "Format diterima: PDF, DOCX, XLSX, JPG, PNG, GIF\n\n"
                . "_Kirim langsung ke chat ini._",
                [['🔙 Kembali ke Menu']]
            );
            return true;
        }

        sendMsg($chatId, "Pilih tipe pengumpulan yang tersedia.");
        return true;
    }

    // ── KUMPULKAN TUGAS: INPUT TEKS JAWABAN ──────────────────────
    if ($step === 'kumpul_input_teks') {
        if (mb_strlen($text) < 5) {
            sendMsg($chatId, "❌ Jawaban terlalu pendek (minimal 5 karakter). Coba lagi:");
            return true;
        }

        $tugas  = (array) ($state['tugas'] ?? []);
        $pesan  = formatPreviewPengumpulan($tugas, $text, null);

        setState($chatId, array_merge($state, [
            'step'         => 'kumpul_konfirmasi',
            'teks_jawaban' => $text,
            'user_cache'   => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    // ── KUMPULKAN TUGAS: INPUT FILE (tombol teks saja) ───────────
    // Upload file/foto ditangani di index.php sebelum handler ini.
    // Di sini hanya menangani kalau user ketik teks saat diminta file.
    if ($step === 'kumpul_input_file') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        // Teks lain diarahkan balik
        sendMsg(
            $chatId,
            "❌ Silakan kirim *file atau foto*, bukan teks.\n\n"
            . "Atau tekan 🔙 Kembali ke Menu untuk membatalkan.",
            [['🔙 Kembali ke Menu']]
        );

        return false; // false agar index.php tidak berhenti di sini
    }

    // ── KUMPULKAN TUGAS: KONFIRMASI FILE ─────────────────────────
    if ($step === 'kumpul_konfirmasi_file') {
        if ($text === '❌ Batal') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '✅ Kirim') {
            $fileId   = (string) ($state['file_id']   ?? '');
            $fileType = (string) ($state['file_type'] ?? 'document');
            $filePath = null;

            if ($fileId !== '') {
                sendMsg($chatId, "⏳ Mengunggah file, harap tunggu...");
                $filePath = downloadTelegramFile($fileId, $fileType, $uid);
            }

            if ($filePath === null) {
                sendMsg(
                    $chatId,
                    "❌ Gagal mengunduh file dari Telegram. Coba kirim ulang.",
                    [['✅ Kirim', '❌ Batal']]
                );
                return true;
            }

            $tipeKumpul = (string) ($state['tipe_kumpul'] ?? 'baru');
            $tugas      = (array)  ($state['tugas'] ?? []);

            $ok = $tipeKumpul === 'revisi'
                ? simpanRevisiTugas($uid, (int) $state['tugas_id'], null, $filePath)
                : simpanPengumpulanTugas($uid, (int) $state['tugas_id'], null, $filePath);

            _resetKeMenu($chatId, $user);

            $judulTugas = (string) ($tugas['judul'] ?? '');
            $namaFile   = (string) ($state['file_nama'] ?? '');

            if ($ok) {
                $pesanOk = $tipeKumpul === 'revisi'
                    ? "✅ *Revisi berhasil dikirim!*\n\n📝 {$judulTugas}\n📎 {$namaFile}\n\nGuru akan meninjau revisi Anda."
                    : "✅ *Tugas berhasil dikumpulkan!*\n\n📝 {$judulTugas}\n📎 {$namaFile}\n\n_Guru akan menilai tugas Anda._";
                sendMsg($chatId, $pesanOk, mainKeyboard($role));
            } else {
                sendMsg($chatId, "❌ Gagal menyimpan pengumpulan. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Kirim* atau *❌ Batal*", [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    // ── KUMPULKAN TUGAS: KONFIRMASI TEKS ─────────────────────────
    if ($step === 'kumpul_konfirmasi') {
        if ($text === '❌ Batal') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '✅ Kirim') {
            $tipeKumpul = (string) ($state['tipe_kumpul'] ?? 'baru');
            $tugas      = (array)  ($state['tugas'] ?? []);

            $ok = $tipeKumpul === 'revisi'
                ? simpanRevisiTugas($uid, (int) $state['tugas_id'], $state['teks_jawaban'] ?? null, null)
                : simpanPengumpulanTugas($uid, (int) $state['tugas_id'], $state['teks_jawaban'] ?? null, null);

            _resetKeMenu($chatId, $user);

            $judulTugas = (string) ($tugas['judul'] ?? '');

            if ($ok) {
                $pesanOk = $tipeKumpul === 'revisi'
                    ? "✅ *Revisi berhasil dikirim!*\n\n📝 {$judulTugas}\n\nGuru akan meninjau revisi Anda."
                    : "✅ *Tugas berhasil dikumpulkan!*\n\n📝 {$judulTugas}\n\n_Guru akan menilai tugas Anda._";
                sendMsg($chatId, $pesanOk, mainKeyboard($role));
            } else {
                sendMsg($chatId, "❌ Gagal menyimpan pengumpulan. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Kirim* atau *❌ Batal*", [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    // ── ANALISIS TUGAS: PILIH NOMOR ──────────────────────────────
    if ($step === 'analisis_tugas_pilih') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $tugasList = (array) ($state['tugas_list'] ?? []);
        $no        = (int) $text;

        if ($no < 1 || $no > count($tugasList)) {
            sendMsg($chatId, "❌ Nomor tidak valid. Pilih nomor dari daftar.");
            return true;
        }

        $tugasPilih = $tugasList[$no - 1];
        $analisis   = getTugasAnalisisDetail((int) $tugasPilih['tugas_id']);

        if (empty($analisis['tugas'])) {
            sendMsg($chatId, "❌ Gagal memuat data tugas.");
            return true;
        }

        $t            = $analisis['tugas'];
        $totalSiswa   = count($analisis['all_siswa']);
        $sudahKumpul  = count($analisis['sudah_kumpul']);
        $belumKumpul  = count($analisis['belum_kumpul']);
        $bar          = progressBar($sudahKumpul, $totalSiswa);

        $pesan  = "📋 *Analisis Tugas*\n\n"
                . "*{$t['judul']}*\n"
                . "📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n";

        if ($t['wali_kelas'] !== '-') {
            $pesan .= "👨‍💼 Wali: {$t['wali_kelas']}\n";
        }

        $pesan .= "\n📊 *Statistik:*\n"
                . "👥 Total    : {$totalSiswa} siswa\n"
                . "✅ Kumpul   : {$sudahKumpul} siswa\n"
                . "⏳ Belum    : {$belumKumpul} siswa\n"
                . "📈 Progress : {$bar}\n";

        if (!empty($analisis['sudah_kumpul'])) {
            $pesan .= "\n✅ *Sudah Mengumpulkan:*\n";
            foreach ($analisis['sudah_kumpul'] as $s) {
                $pesan .= "• {$s['nama_lengkap']} ({$s['nis_nip']})\n";
            }
        }

        if (!empty($analisis['belum_kumpul'])) {
            $pesan .= "\n⏳ *Belum Mengumpulkan:*\n";
            // Batasi tampilan agar pesan tidak terlalu panjang
            $ditampilkan = array_slice($analisis['belum_kumpul'], 0, 15);
            foreach ($ditampilkan as $s) {
                $pesan .= "• {$s['nama_lengkap']} ({$s['nis_nip']})\n";
            }
            if (count($analisis['belum_kumpul']) > 15) {
                $sisa = count($analisis['belum_kumpul']) - 15;
                $pesan .= "...dan {$sisa} siswa lainnya.\n";
            }
        } else {
            $pesan .= "\n🎉 Semua siswa sudah mengumpulkan!\n";
        }

        _resetKeMenu($chatId, $user);
        sendMsg($chatId, $pesan, mainKeyboard($role));
        return true;
    }

    // ── NILAI TUGAS: PILIH TUGAS ─────────────────────────────────
    if ($step === 'nilai_pilih_tugas') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $tugasList = (array) ($state['tugas_list'] ?? []);
        $no        = (int) $text;

        if ($no < 1 || $no > count($tugasList)) {
            sendMsg($chatId, "❌ Nomor tidak valid. Pilih dari daftar.");
            return true;
        }

        $tugasPilih      = $tugasList[$no - 1];
        $pengumpulanList = getPengumpulanTugas((int) $tugasPilih['tugas_id']);

        if (empty($pengumpulanList)) {
            sendMsg($chatId, "❌ Belum ada siswa yang mengumpulkan tugas ini.");
            return true;
        }

        $pesan  = "📝 *{$tugasPilih['judul']}*\n";
        $pesan .= "💯 Poin Maksimal: {$tugasPilih['poin_maksimal']}\n\n";
        $pesan .= "Pilih siswa yang akan dinilai:\n\n";

        foreach ($pengumpulanList as $idx => $p) {
            $no2       = $idx + 1;
            $statusEmoji = $p['nilai'] !== null ? '✅' : '⏳';
            $nilaiStr    = $p['nilai'] !== null ? " — Nilai: {$p['nilai']}" : '';
            $pesan      .= "{$statusEmoji} *{$no2}.* {$p['nama_lengkap']} ({$p['nis_nip']}){$nilaiStr}\n";
        }

        $keyboard   = _buildNomorKeyboard(count($pengumpulanList));
        $keyboard[] = ['🔙 Kembali ke Menu'];

        setState($chatId, array_merge($state, [
            'step'             => 'nilai_pilih_siswa',
            'tugas_id'         => (int) $tugasPilih['tugas_id'],
            'tugas_judul'      => $tugasPilih['judul'],
            'poin_maksimal'    => (int) $tugasPilih['poin_maksimal'],
            'pengumpulan_list' => $pengumpulanList,
            'user_cache'       => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, $keyboard);
        return true;
    }

    // ── NILAI TUGAS: PILIH SISWA ─────────────────────────────────
    if ($step === 'nilai_pilih_siswa') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $pengumpulanList = (array) ($state['pengumpulan_list'] ?? []);
        $no              = (int) $text;

        if ($no < 1 || $no > count($pengumpulanList)) {
            sendMsg($chatId, "❌ Nomor tidak valid. Pilih dari daftar.");
            return true;
        }

        $pengumpulan = $pengumpulanList[$no - 1];
        $detail      = getPengumpulanDetail((int) $pengumpulan['pengumpulan_id']);

        if (!$detail) {
            sendMsg($chatId, "❌ Gagal memuat data pengumpulan.");
            return true;
        }

        $pesan = formatFormNilai($detail);

        setState($chatId, array_merge($state, [
            'step'               => 'nilai_input_nilai',
            'pengumpulan_id'     => (int) $detail['pengumpulan_id'],
            'pengumpulan_detail' => $detail,
            'user_cache'         => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, [['🔙 Kembali ke Menu']]);
        return true;
    }

    // ── NILAI TUGAS: INPUT NILAI ─────────────────────────────────
    if ($step === 'nilai_input_nilai') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $detail  = (array) ($state['pengumpulan_detail'] ?? []);
        $poinMax = (float) ($detail['poin_maksimal'] ?? 100);

        if (!is_numeric($text)) {
            sendMsg($chatId, "❌ Masukkan angka yang valid. Contoh: `85` atau `90.5`");
            return true;
        }

        $nilai = (float) str_replace(',', '.', $text);

        if ($nilai < 0 || $nilai > $poinMax) {
            sendMsg($chatId, "❌ Nilai harus antara *0* dan *{$poinMax}*. Coba lagi:");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'        => 'nilai_input_catatan',
            'nilai_input' => $nilai,
            'user_cache'  => _buildUserCache($user),
        ]));

        sendMsg(
            $chatId,
            "💯 Nilai: *{$nilai}/{$poinMax}*\n\n"
            . "Ketik *catatan* untuk siswa, atau tekan tombol untuk lanjut tanpa catatan:",
            [['✓ Tanpa Catatan'], ['🔙 Kembali ke Menu']]
        );

        return true;
    }

    // ── NILAI TUGAS: INPUT CATATAN ───────────────────────────────
    if ($step === 'nilai_input_catatan') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $catatan = ($text === '✓ Tanpa Catatan') ? null : $text;
        $detail  = (array)  ($state['pengumpulan_detail'] ?? []);
        $nilai   = (float)  ($state['nilai_input'] ?? 0);

        $pesan = formatKonfirmasiNilai($detail, $nilai, $catatan);

        setState($chatId, array_merge($state, [
            'step'         => 'nilai_konfirmasi',
            'catatan_input' => $catatan,
            'user_cache'   => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, [['✅ Simpan', '✏️ Ubah Nilai', '❌ Batal']]);
        return true;
    }

    // ── NILAI TUGAS: KONFIRMASI & SIMPAN ─────────────────────────
    if ($step === 'nilai_konfirmasi') {
        if ($text === '❌ Batal') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '✏️ Ubah Nilai') {
            // Kembali ke input nilai
            $detail = (array) ($state['pengumpulan_detail'] ?? []);
            setState($chatId, array_merge($state, [
                'step'       => 'nilai_input_nilai',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsg($chatId, formatFormNilai($detail), [['🔙 Kembali ke Menu']]);
            return true;
        }

        if ($text === '✅ Simpan') {
            $hasil = savePenilaian(
                (int)    $state['pengumpulan_id'],
                $uid,
                (float)  $state['nilai_input'],
                $state['catatan_input'] ?? null,
                'lulus'
            );

            $detail = (array) ($state['pengumpulan_detail'] ?? []);

            if ($hasil['success']) {
                // Tawarkan untuk menilai siswa berikutnya
                $pengumpulanList = (array) ($state['pengumpulan_list'] ?? []);
                $belumDinilai    = array_values(array_filter(
                    $pengumpulanList,
                    fn($p) => $p['pengumpulan_id'] !== $state['pengumpulan_id']
                        && $p['nilai'] === null
                ));

                _resetKeMenu($chatId, $user);

                $pesanOk = "✅ *Penilaian Berhasil Disimpan!*\n\n"
                         . "👤 {$detail['nama_lengkap']}\n"
                         . "📝 {$detail['judul']}\n"
                         . formatNilaiBintang((float) $state['nilai_input'], (int) $detail['poin_maksimal']);

                if (!empty($state['catatan_input'])) {
                    $pesanOk .= "\n💬 _{$state['catatan_input']}_";
                }

                if (!empty($belumDinilai)) {
                    $pesanOk .= "\n\n📌 Masih ada " . count($belumDinilai) . " siswa yang belum dinilai.";
                }

                sendMsg($chatId, $pesanOk, mainKeyboard($role));
            } else {
                _resetKeMenu($chatId, $user);
                sendMsg($chatId, "❌ Gagal menyimpan: {$hasil['message']}", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Gunakan tombol untuk melanjutkan.", [['✅ Simpan', '✏️ Ubah Nilai', '❌ Batal']]);
        return true;
    }

    // ── PENGUMUMAN: PILIH KELAS (guru) ───────────────────────────
    if ($step === 'pengumuman_pilih_kelas') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        $kelas = sirey_fetch(sirey_query(
            'SELECT g.grup_id, g.nama_grup
             FROM grup_rayhanrp g
             INNER JOIN guru_mengajar_rayhanrp gm ON g.grup_id = gm.grup_id
             WHERE g.nama_grup = ? AND gm.akun_id = ? AND gm.aktif = 1 LIMIT 1',
            'si',
            $text,
            $uid
        ));

        if (!$kelas) {
            sendMsg($chatId, "❌ Kelas tidak valid. Pilih dari tombol yang tersedia.");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'       => 'pengumuman_input_judul',
            'grup_id'    => (int) $kelas['grup_id'],
            'kelas'      => $kelas['nama_grup'],
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsgRemoveKeyboard(
            $chatId,
            "📢 Kelas: *{$kelas['nama_grup']}*\n\nMasukkan *judul pengumuman*:"
        );

        return true;
    }

    // ── PENGUMUMAN: INPUT JUDUL ──────────────────────────────────
    if ($step === 'pengumuman_input_judul') {
        if (mb_strlen($text) < 3) {
            sendMsg($chatId, "❌ Judul terlalu pendek. Coba lagi:");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'       => 'pengumuman_input_isi',
            'judul'      => $text,
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg($chatId, "Masukkan *isi pengumuman*:");
        return true;
    }

    // ── PENGUMUMAN: INPUT ISI ────────────────────────────────────
    if ($step === 'pengumuman_input_isi') {
        if (mb_strlen($text) < 5) {
            sendMsg($chatId, "❌ Isi pengumuman terlalu pendek (minimal 5 karakter). Coba lagi:");
            return true;
        }

        $preview = "✅ *Konfirmasi Pengumuman*\n\n"
                 . "🎓 Kelas  : {$state['kelas']}\n"
                 . "📢 Judul  : {$state['judul']}\n\n"
                 . "📋 *Isi:*\n{$text}\n\n"
                 . "Kirim pengumuman ini?";

        setState($chatId, array_merge($state, [
            'step'       => 'pengumuman_konfirmasi',
            'isi'        => $text,
            'user_cache' => _buildUserCache($user),
        ]));

        sendMsg($chatId, $preview, [['✅ Ya, Kirim', '❌ Batal']]);
        return true;
    }

    // ── PENGUMUMAN: KONFIRMASI ───────────────────────────────────
    if ($step === 'pengumuman_konfirmasi') {
        if ($text === '❌ Batal') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '✅ Ya, Kirim') {
            $ok = simpanPengumumanGuru([
                'judul'      => (string) $state['judul'],
                'isi'        => (string) $state['isi'],
                'grup_id'    => (int)    $state['grup_id'],
                'pembuat_id' => $uid,
            ]);

            _resetKeMenu($chatId, $user);

            if ($ok) {
                sendMsg(
                    $chatId,
                    "✅ *Pengumuman berhasil dikirim!*\n\nSiswa di kelas *{$state['kelas']}* sudah menerima notifikasi. 📬",
                    mainKeyboard($role)
                );
            } else {
                sendMsg($chatId, "❌ Gagal mengirim pengumuman. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Ya, Kirim* atau *❌ Batal*", [['✅ Ya, Kirim', '❌ Batal']]);
        return true;
    }

    // ── PENGATURAN: PILIH MENU ───────────────────────────────────
    if ($step === 'pengaturan_pilih_menu') {
        if ($text === '🔙 Kembali ke Menu') {
            return _kembaliMenu($chatId, $user);
        }

        if ($text === '🔑 Ganti Password') {
            setState($chatId, array_merge($state, [
                'step'       => 'pengaturan_ganti_password',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsgRemoveKeyboard(
                $chatId,
                "🔑 *Ganti Password*\n\nMasukkan *password baru* (minimal 6 karakter):"
            );
            return true;
        }

        if ($text === '🕐 Atur Jam Notifikasi') {
            $jamNow = getJamNotifikasi($uid);

            $instruksi = $role === 'guru'
                ? "Masukkan jam notifikasi jadwal:\nFormat: `HH:MM`\nContoh: `07:00`"
                : "Masukkan dua jam dipisah spasi:\nFormat: `HH:MM HH:MM`\nUrutan: _(Jadwal) (Tugas)_\nContoh: `07:00 12:00`";

            setState($chatId, array_merge($state, [
                'step'       => 'pengaturan_atur_jam',
                'user_cache' => _buildUserCache($user),
            ]));

            sendMsgRemoveKeyboard(
                $chatId,
                "🕐 *Atur Jam Notifikasi*\n\n"
                . "Saat ini:\n"
                . ($role === 'guru' ? "📅 Jadwal : *{$jamNow['jam_jadwal']}*\n\n" : (
                    "📅 Jadwal : *{$jamNow['jam_jadwal']}*\n"
                    . "📝 Tugas  : *{$jamNow['jam_tugas']}*\n\n"
                ))
                . $instruksi
            );

            return true;
        }

        sendMsg($chatId, "Pilih menu dari tombol di bawah:", settingsKeyboard());
        return true;
    }

    // ── PENGATURAN: GANTI PASSWORD ───────────────────────────────
    if ($step === 'pengaturan_ganti_password') {
        if (mb_strlen($text) < 6) {
            sendMsg($chatId, "❌ Password minimal 6 karakter. Coba lagi:");
            return true;
        }

        setState($chatId, array_merge($state, [
            'step'          => 'pengaturan_konfirmasi_password',
            'password_baru' => $text,
            'user_cache'    => _buildUserCache($user),
        ]));

        sendMsg(
            $chatId,
            "Password baru: `{$text}`\n\nYakin ingin menyimpan password ini?",
            [['✅ Ya, Simpan', '❌ Batal']]
        );

        return true;
    }

    // ── PENGATURAN: KONFIRMASI PASSWORD ──────────────────────────
    if ($step === 'pengaturan_konfirmasi_password') {
        if ($text === '❌ Batal') {
            setState($chatId, array_merge($state, [
                'step'       => 'pengaturan_pilih_menu',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsg($chatId, "↩️ Dibatalkan.", settingsKeyboard());
            return true;
        }

        if ($text === '✅ Ya, Simpan') {
            $ok = updatePassword($uid, (string) $state['password_baru']);
            _resetKeMenu($chatId, $user);

            if ($ok) {
                sendMsg(
                    $chatId,
                    "✅ *Password berhasil diubah!*\n\nGunakan password baru Anda saat login berikutnya.",
                    mainKeyboard($role)
                );
            } else {
                sendMsg($chatId, "❌ Gagal mengubah password. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Ya, Simpan* atau *❌ Batal*", [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    // ── PENGATURAN: ATUR JAM NOTIFIKASI ──────────────────────────
    if ($step === 'pengaturan_atur_jam') {
        $parts = preg_split('/\s+/', trim($text));
        $pola  = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';

        if ($role === 'guru') {
            // Guru hanya input 1 jam (jadwal)
            if (count($parts) !== 1 || !preg_match($pola, $parts[0])) {
                sendMsg($chatId, "❌ Format salah. Masukkan satu jam: contoh `07:00`");
                return true;
            }

            $jamJadwal = $parts[0];
            $jamTugas  = '12:00'; // default untuk guru

        } else {
            // Siswa input 2 jam
            if (count($parts) !== 2 || !preg_match($pola, $parts[0]) || !preg_match($pola, $parts[1])) {
                sendMsg($chatId, "❌ Format salah. Masukkan dua jam: contoh `07:00 12:00`");
                return true;
            }

            $jamJadwal = $parts[0];
            $jamTugas  = $parts[1];
        }

        $pesan = "⏰ *Konfirmasi Jam Notifikasi*\n\n"
               . "📅 Jadwal : *{$jamJadwal}*\n"
               . ($role !== 'guru' ? "📝 Tugas  : *{$jamTugas}*\n" : '')
               . "\nSimpan pengaturan ini?";

        setState($chatId, array_merge($state, [
            'step'        => 'pengaturan_konfirmasi_jam',
            'jam_jadwal'  => $jamJadwal,
            'jam_tugas'   => $jamTugas,
            'user_cache'  => _buildUserCache($user),
        ]));

        sendMsg($chatId, $pesan, [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    // ── PENGATURAN: KONFIRMASI JAM ───────────────────────────────
    if ($step === 'pengaturan_konfirmasi_jam') {
        if ($text === '❌ Batal') {
            setState($chatId, array_merge($state, [
                'step'       => 'pengaturan_pilih_menu',
                'user_cache' => _buildUserCache($user),
            ]));
            sendMsg($chatId, "↩️ Dibatalkan.", settingsKeyboard());
            return true;
        }

        if ($text === '✅ Ya, Simpan') {
            $ok = updateJamNotifikasi($uid, (string) $state['jam_jadwal'], (string) $state['jam_tugas']);
            _resetKeMenu($chatId, $user);

            if ($ok) {
                $pesanOk = "✅ *Jam Notifikasi Diperbarui!*\n\n"
                         . "📅 Jadwal : {$state['jam_jadwal']}\n"
                         . ($role !== 'guru' ? "📝 Tugas  : {$state['jam_tugas']}\n" : '');
                sendMsg($chatId, $pesanOk, mainKeyboard($role));
            } else {
                sendMsg($chatId, "❌ Gagal menyimpan. Silakan coba lagi.", mainKeyboard($role));
            }

            return true;
        }

        sendMsg($chatId, "Pilih: *✅ Ya, Simpan* atau *❌ Batal*", [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    // ── STEP MENU: lanjutkan ke handleMenu ───────────────────────
    if ($step === 'menu') {
        return false;
    }

    return false;
}


// ================================================================
// Helper privat
// ================================================================

/**
 * Reset state ke menu utama dan tampilkan keyboard menu.
 * Mengembalikan true agar bisa langsung dipakai sebagai return.
 */
function _kembaliMenu(int $chatId, array $user): bool
{
    _resetKeMenu($chatId, $user);
    sendMsg($chatId, "↩️ Menu utama:", mainKeyboard((string) $user['role']));
    return true;
}

/**
 * Reset state ke step 'menu' tanpa kirim pesan.
 */
function _resetKeMenu(int $chatId, array $user): void
{
    $stateSekarang = getState($chatId) ?? [];

    setState($chatId, [
        'step'          => 'menu',
        'session_token' => $stateSekarang['session_token'] ?? '',
        'user_cache'    => _buildUserCache($user),
    ]);
}

/**
 * Buat array user_cache dari data user.
 * Didefinisikan di sini juga agar state.php bisa berdiri sendiri.
 * (Fungsi sama di menu.php, tapi PHP tidak masalah karena keduanya di-include index.php)
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