<?php
declare(strict_types=1);

/**
 * Tangani semua step percakapan (state machine).
 * Return true jika sudah ditangani.
 */
function handleState(string $step, string $text, int $chat, array $state, ?array $user): bool {

    // ── LOGIN FLOW ──────────────────────────────────────────────────────────

    if ($step === 'ask_nis') {
        if ($text === '') {
            sendMsg($chat, "Masukkan *NIS/NIP* Anda:");
            return true;
        }
        setState($chat, ['step' => 'ask_password', 'nis' => $text]);
        sendMsg($chat, "🔑 Masukkan *password* Anda:");
        return true;
    }

    if ($step === 'ask_password') {
        $akun = sirey_fetch(sirey_query(
            'SELECT akun_id, nis_nip, password, role, nama_lengkap
             FROM akun_rayhanRP WHERE nis_nip = ? LIMIT 1',
            's', $state['nis'] ?? ''
        ));

        if (!$akun || !verifyPassword($akun, $text)) {
            setState($chat, ['step' => 'ask_nis']);
            sendMsg($chat, "❌ NIS/NIP atau password salah.\n\nCoba lagi, masukkan *NIS/NIP*:");
            return true;
        }

        $sessionToken = createTelegramLoginSession((int)$akun['akun_id'], $chat);

        setState($chat, [
            'step'       => 'menu',
            'session_token' => $sessionToken,
            'user_cache' => [
                'akun_id'        => $akun['akun_id'],
                'nama_lengkap'   => $akun['nama_lengkap'],
                'role'           => $akun['role'],
                'nis_nip'        => $akun['nis_nip'],
            ],
        ]);
        sendMsg(
            $chat,
            "✅ Login berhasil!\n\nHalo, *{$akun['nama_lengkap']}* 👋\n\nPilih menu di bawah:",
            mainKeyboard($akun['role'])
        );
        return true;
    }

    // ── FORM BUAT TUGAS (khusus guru) ───────────────────────────────────────

    if ($step === 'tugas_pilih_mapel') {
        // user memilih mapel dari keyboard
        if ($text === '🔙 Kembali ke Menu') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        $mapel = sirey_fetch(sirey_query(
            'SELECT matpel_id, nama FROM mata_pelajaran_rayhanRP WHERE nama = ? LIMIT 1',
            's', $text
        ));

        if (!$mapel) {
            sendMsg($chat, "❌ Mapel tidak valid. Pilih dari daftar.");
            return true;
        }

        $kelasList = getKelasGuruByMatpel((int)$user['akun_id'], (int)$mapel['matpel_id']);
        if (empty($kelasList)) {
            sendMsg($chat, "❌ Anda tidak mengajar kelas manapun untuk mapel ini.");
            return true;
        }

        $kelasKeyboard = array_chunk(array_map(fn($k) => $k['nama_grup'], $kelasList), 2);
        $kelasKeyboard[] = ['🔙 Kembali ke Menu'];

        setState($chat, [
            'step'       => 'tugas_pilih_kelas',
            'matpel_id'  => (int)$mapel['matpel_id'],
            'matpel'     => $mapel['nama'],
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]);
        sendMsg($chat, "Pilih *kelas* untuk tugas ini:", $kelasKeyboard);
        return true;
    }

    if ($step === 'tugas_pilih_kelas') {
        if ($text === '🔙 Kembali ke Menu') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        $kelas = sirey_fetch(sirey_query(
            'SELECT g.grup_id, g.nama_grup
             FROM grup_rayhanRP g
             INNER JOIN guru_mengajar_rayhanRP gm ON g.grup_id = gm.grup_id
             WHERE g.nama_grup = ? AND gm.akun_id = ? AND gm.matpel_id = ? LIMIT 1',
            'sii', $text, (int)$user['akun_id'], (int)($state['matpel_id'] ?? 0)
        ));

        if (!$kelas) {
            sendMsg($chat, "❌ Kelas tidak valid.");
            return true;
        }

        setState($chat, array_merge($state, [
            'step'       => 'tugas_input_judul',
            'grup_id'    => (int)$kelas['grup_id'],
            'kelas'      => $kelas['nama_grup'],
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]));
        sendMsgRemoveKeyboard($chat,
            "📝 Mapel: *{$state['matpel']}*\n🎓 Kelas: *{$kelas['nama_grup']}*\n\nMasukkan *judul tugas*:"
        );
        return true;
    }

    if ($step === 'tugas_input_judul') {
        if (strlen($text) < 3) {
            sendMsg($chat, "Judul terlalu pendek. Masukkan judul yang jelas:");
            return true;
        }
        setState($chat, array_merge($state, [
            'step'       => 'tugas_input_deskripsi',
            'judul'      => $text,
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]));
        sendMsg($chat, "Masukkan *deskripsi tugas* (atau ketik `-` untuk skip):");
        return true;
    }

    if ($step === 'tugas_input_deskripsi') {
        $deskripsi = $text === '-' ? '' : $text;
        setState($chat, array_merge($state, [
            'step'       => 'tugas_input_deadline',
            'deskripsi'  => $deskripsi,
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]));
        sendMsg($chat, "Masukkan *deadline* tugas:\nFormat: `DD/MM/YYYY HH:MM`\nContoh: `25/12/2025 23:59`");
        return true;
    }

    if ($step === 'tugas_input_deadline') {
        $dt = DateTimeImmutable::createFromFormat('d/m/Y H:i', $text);
        if (!$dt) {
            sendMsg($chat, "❌ Format salah. Gunakan format: `DD/MM/YYYY HH:MM`\nContoh: `25/12/2025 23:59`");
            return true;
        }
        if ($dt->getTimestamp() < time()) {
            sendMsg($chat, "❌ Deadline tidak boleh di masa lalu. Coba lagi:");
            return true;
        }

        // Konfirmasi sebelum simpan
        $konfirmasi = "✅ *Konfirmasi Tugas*\n\n"
            . "📚 Mapel: {$state['matpel']}\n"
            . "🎓 Kelas: {$state['kelas']}\n"
            . "📝 Judul: {$state['judul']}\n"
            . ($state['deskripsi'] ? "📋 Deskripsi: {$state['deskripsi']}\n" : '')
            . "📅 Deadline: " . $dt->format('d/m/Y H:i') . "\n\n"
            . "Kirim tugas ini?";

        setState($chat, array_merge($state, [
            'step'       => 'tugas_konfirmasi',
            'tenggat'    => $dt->format('Y-m-d H:i:s'),
            'user_cache' => [
                'akun_id'        => $user['akun_id'],
                'nama_lengkap'   => $user['nama_lengkap'],
                'role'           => $user['role'],
                'nis_nip'        => $user['nis_nip'],
            ],
        ]));
        sendMsg($chat, $konfirmasi, [['✅ Ya, Kirim', '❌ Batal']]);
        return true;
    }

    if ($step === 'tugas_konfirmasi') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Dibatalkan.", mainKeyboard($user['role']));
            return true;
        }
        if ($text === '✅ Ya, Kirim') {
            $ok = simpanTugasBot([
                'grup_id'    => (int)$state['grup_id'],
                'judul'      => $state['judul'],
                'deskripsi'  => $state['deskripsi'] ?? '',
                'matpel_id'  => (int)$state['matpel_id'],
                'tenggat'    => $state['tenggat'],
                'pembuat_id' => (int)$user['akun_id'],
            ]);

            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);
            if ($ok) {
                sendMsg($chat, "✅ *Tugas berhasil dibuat!*\n\nSiswa sudah mendapat notifikasi.", mainKeyboard($user['role']));
            } else {
                sendMsg($chat, "❌ Gagal menyimpan tugas. Coba lagi.", mainKeyboard($user['role']));
            }
            return true;
        }
        sendMsg($chat, "Pilih: *✅ Ya, Kirim* atau *❌ Batal*");
        return true;
    }

    // ── KUMPULKAN TUGAS - PILIH TUGAS ───────────────────────────────────────

    if ($step === 'kumpul_pilih_tugas') {
        // User input nomor tugas
        $no = (int)$text;
        $daftarTugas = $state['daftar_tugas'] ?? [];
        if ($no < 1 || $no > count($daftarTugas)) {
            sendMsg($chat, "❌ Nomor tidak valid. Pilih dari daftar.");
            return true;
        }

        $tugas = $daftarTugas[$no - 1];
        $tipeKumpul = $tugas['tipe'] ?? 'baru'; // Track apakah ini baru atau revisi
        
        setState($chat, [
            'step'          => 'kumpul_pilih_tipe',
            'tugas_id'      => (int)$tugas['tugas_id'],
            'tugas'         => $tugas,
            'tipe_kumpul'   => $tipeKumpul,
            'user_cache'    => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        $pesan = "📝 *{$tugas['judul']}*\n\n"
               . "📚 {$tugas['matpel']} | 🎓 {$tugas['nama_grup']}\n"
               . "📅 Deadline: " . date('d/m/Y', strtotime($tugas['tenggat'])) . "\n\n";
        
        if ($tipeKumpul === 'revisi') {
            $pesan .= "⚠️ *INI ADALAH PENGUMPULAN REVISI*\n"
                   . "Silakan perbaiki jawaban sesuai catatan guru.\n\n";
        }
        
        $pesan .= "Pilih cara pengumpulan:";

        $keyboard = [
            ['📄 Teks Jawaban'],
            ['📎 File/Foto'],
            ['🔙 Kembali ke Menu'],
        ];

        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── KUMPULKAN TUGAS - PILIH TIPE JAWABAN ────────────────────────────────

    if ($step === 'kumpul_pilih_tipe') {
        if ($text === '📄 Teks Jawaban') {
            setState($chat, [
                'step'          => 'kumpul_input_teks',
                'tipe_jawaban'  => 'teks',
                'tugas_id'      => (int)($state['tugas_id'] ?? 0),
                'tugas'         => $state['tugas'] ?? [],
                'tipe_kumpul'   => $state['tipe_kumpul'] ?? 'baru',
                'user_cache'    => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);
            sendMsgRemoveKeyboard($chat, "📝 Silakan masukkan *jawaban tugas* Anda:\n\n(Ketik jawaban Anda di bawah)");
            return true;
        }

        if ($text === '📎 File/Foto') {
            setState($chat, [
                'step'          => 'kumpul_input_file',
                'tipe_jawaban'  => 'file',
                'tugas_id'      => (int)($state['tugas_id'] ?? 0),
                'tugas'         => $state['tugas'] ?? [],
                'tipe_kumpul'   => $state['tipe_kumpul'] ?? 'baru',
                'user_cache'    => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "📎 *Silakan kirim file atau foto* jawaban Anda.\n\n✅ Format yang diterima:\n📄 PDF, DOCX, DOC\n📊 XLSX, XLS, CSV\n🖼️ JPG, JPEG, PNG, GIF\n\n💡 Kirim langsung ke chat ini, atau pilih Kembali untuk ke menu utama", [
                ['🔙 Kembali ke Menu'],
            ]);
            return true;
        }

        if ($text === '🔙 Kembali ke Menu') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        sendMsg($chat, "Pilih tipe pengumpulan yang valid.");
        return true;
    }

    if ($step === 'kumpul_input_teks') {
        if (strlen($text) < 5) {
            sendMsg($chat, "❌ Jawaban terlalu pendek. Minimal 5 karakter.");
            return true;
        }

        $tugas = $state['tugas'] ?? [];
        setState($chat, [
            'step'          => 'kumpul_konfirmasi',
            'tugas_id'      => (int)($state['tugas_id'] ?? 0),
            'tugas'         => $tugas,
            'teks_jawaban'  => $text,
            'link_jawaban'  => '',
            'tipe_kumpul'   => $state['tipe_kumpul'] ?? 'baru',
            'user_cache'    => [
                'akun_id'      => $user['akun_id'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role'         => $user['role'],
                'nis_nip'      => $user['nis_nip'],
            ],
        ]);

        $pesan = formatPreviewPengumpulan($tugas, $text, '');
        sendMsg($chat, $pesan, [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    if ($step === 'kumpul_input_file') {
        if ($text === '🔙 Kembali ke Menu') {
            setState($chat, [
                'step'          => 'menu',
                'user_cache'    => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        // Jika ada text selain tombol "Kembali", abaikan (file upload dihandle di index.php)
        // Return false agar tidak block handler lain
        return false;
    }

    if ($step === 'kumpul_konfirmasi_file') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Pengumpulan dibatalkan.", mainKeyboard($user['role']));
            return true;
        }

        if ($text === '✅ Kirim') {
            // Download file baru dilakukan di sini (saat user konfirmasi),
            // bukan saat file dikirim — agar konfirmasi muncul instan tanpa timeout.
            $fileId   = $state['file_id']   ?? null;
            $fileType = $state['file_type'] ?? 'document';
            $filePath = null;

            if ($fileId) {
                sendMsg($chat, "⏳ Sedang mengunggah file...");
                $filePath = downloadTelegramFile($fileId, $fileType, (int)$user['akun_id']);
            }

            if (!$filePath) {
                sendMsg($chat, "❌ Gagal mengunduh file dari Telegram. Coba kirim ulang.", [['✅ Kirim', '❌ Batal']]);
                return true;
            }

            $tipeKumpul = $state['tipe_kumpul'] ?? 'baru';
            
            // Pilih function berdasarkan tipe pengumpulan
            if ($tipeKumpul === 'revisi') {
                $ok = simpanRevisiTugas(
                    (int)$user['akun_id'],
                    (int)($state['tugas_id'] ?? 0),
                    null,
                    $filePath
                );
            } else {
                $ok = simpanPengumpulanTugas(
                    (int)$user['akun_id'],
                    (int)($state['tugas_id'] ?? 0),
                    null,
                    $filePath
                );
            }

            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'      => $user['akun_id'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role'         => $user['role'],
                    'nis_nip'      => $user['nis_nip'],
                ],
            ]);

            if ($ok) {
                $tugas = $state['tugas'] ?? [];
                if ($tipeKumpul === 'revisi') {
                    $pesan = "✅ *Revisi berhasil dikirim!*\n\n"
                           . "📝 {$tugas['judul']}\n"
                           . "📎 File: {$state['file_nama']}\n"
                           . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
                           . "Guru akan meninjau revisi Anda.";
                } else {
                    $pesan = "✅ *File berhasil dikumpulkan!*\n\n"
                           . "📝 {$tugas['judul']}\n"
                           . "📎 File: {$state['file_nama']}\n"
                           . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
                           . "File akan diproses oleh guru.";
                }
                sendMsg($chat, $pesan, mainKeyboard($user['role']));
            } else {
                sendMsg($chat, "❌ Gagal menyimpan pengumpulan. Coba lagi.", mainKeyboard($user['role']));
            }
            return true;
        }

        sendMsg($chat, "Pilih: *✅ Kirim* atau *❌ Batal*", [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    if ($step === 'kumpul_konfirmasi') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);
            sendMsg($chat, "↩️ Pengumpulan dibatalkan.", mainKeyboard($user['role']));
            return true;
        }

        if ($text === '✅ Kirim') {
            $tipeKumpul = $state['tipe_kumpul'] ?? 'baru';
            
            // Pilih function berdasarkan tipe pengumpulan
            if ($tipeKumpul === 'revisi') {
                $ok = simpanRevisiTugas(
                    (int)$user['akun_id'],
                    (int)($state['tugas_id'] ?? 0),
                    $state['teks_jawaban'] ?? null,
                    $state['file_path'] ?? null
                );
            } else {
                $ok = simpanPengumpulanTugas(
                    (int)$user['akun_id'],
                    (int)($state['tugas_id'] ?? 0),
                    $state['teks_jawaban'] ?? null,
                    $state['file_path'] ?? null
                );
            }

            setState($chat, [
                'step'       => 'menu',
                'user_cache' => [
                    'akun_id'        => $user['akun_id'],
                    'nama_lengkap'   => $user['nama_lengkap'],
                    'role'           => $user['role'],
                    'nis_nip'        => $user['nis_nip'],
                ],
            ]);

            if ($ok) {
                if ($tipeKumpul === 'revisi') {
                    $pesan = "✅ *Revisi berhasil dikirim!*\n\n"
                           . "📝 {$state['tugas']['judul']}\n"
                           . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
                           . "Guru akan meninjau revisi Anda.";
                } else {
                    $pesan = "✅ *Tugas berhasil dikumpulkan!*\n\n"
                           . "📝 {$state['tugas']['judul']}\n"
                           . "⏰ Waktu: " . date('d/m/Y H:i') . "\n\n"
                           . "Guru akan menilai tugas Anda.";
                }
                sendMsg($chat, $pesan, mainKeyboard($user['role']));
            } else {
                sendMsg($chat, "❌ Gagal menyimpan pengumpulan. Coba lagi.", mainKeyboard($user['role']));
            }
            return true;
        }

        sendMsg($chat, "Pilih: *✅ Kirim* atau *❌ Batal*", [['✅ Kirim', '❌ Batal']]);
        return true;
    }

    // ── PENGATURAN: PILIH MENU ──────────────────────────────────────────────

    if ($step === 'pengaturan_pilih_menu') {
        if ($text === '🔑 Ganti Password') {
            setState($chat, [
                'step'       => 'pengaturan_ganti_password',
                'user_cache' => $user,
            ]);
            sendMsgRemoveKeyboard($chat, "🔑 *Ganti Password*\n\nMasukkan *password baru*:\n(Minimal 6 karakter)");
            return true;
        }

        if ($text === '🕐 Atur Jam Notifikasi') {
            $jamNow = getJamNotifikasi((int)$user['akun_id']);
            setState($chat, [
                'step'       => 'pengaturan_atur_jam',
                'user_cache' => $user,
            ]);
            
            // Guru hanya bisa setting notifikasi jadwal
            if ($user['role'] === 'guru') {
                $pesan = "🕐 *Atur Jam Notifikasi Jadwal*\n\n"
                       . "Jam notifikasi saat ini:\n"
                       . "• 📅 Jadwal: *{$jamNow['jam_jadwal']}*\n\n"
                       . "Masukkan jam baru dalam format:\n`HH:MM`\n\n"
                       . "Contoh: `07:00`\n"
                       . "(Notifikasi jadwal mengajar harian)";
            } else {
                // Siswa bisa setting jadwal dan tugas
                $pesan = "🕐 *Atur Jam Notifikasi*\n\n"
                       . "Jam notifikasi saat ini:\n"
                       . "• 📅 Jadwal: *{$jamNow['jam_jadwal']}*\n"
                       . "• 📝 Tugas: *{$jamNow['jam_tugas']}*\n\n"
                       . "Masukkan dua jam dalam format:\n`HH:MM HH:MM`\n\n"
                       . "Contoh: `07:00 12:00`\n"
                       . "(Jadwal, Tugas)";
            }
            sendMsgRemoveKeyboard($chat, $pesan);
            return true;
        }

        if ($text === '🔙 Kembali Ke Menu') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        sendMsg($chat, "Pilih dari menu di bawah:", settingsKeyboard());
        return true;
    }

    // ── PENGATURAN: GANTI PASSWORD ──────────────────────────────────────────

    if ($step === 'pengaturan_ganti_password') {
        if (strlen($text) < 6) {
            sendMsg($chat, "❌ Password terlalu pendek. Minimal 6 karakter. Coba lagi:");
            return true;
        }

        setState($chat, [
            'step'           => 'pengaturan_konfirmasi_password',
            'password_baru'  => $text,
            'user_cache'     => $user,
        ]);
        sendMsg($chat, "Konfirmasi password baru: `{$text}`\n\nAkan disimpan?", [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    if ($step === 'pengaturan_konfirmasi_password') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'pengaturan_pilih_menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Dibatalkan.", settingsKeyboard());
            return true;
        }

        if ($text === '✅ Ya, Simpan') {
            $ok = updatePassword((int)$user['akun_id'], (string)$state['password_baru']);
            
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            
            if ($ok) {
                sendMsg($chat, "✅ *Password berhasil diubah!*\n\nPassword baru Anda telah disimpan.", mainKeyboard($user['role']));
            } else {
                sendMsg($chat, "❌ Gagal mengubah password. Coba lagi.", mainKeyboard($user['role']));
            }
            return true;
        }

        sendMsg($chat, "Pilih: *✅ Ya, Simpan* atau *❌ Batal*", [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    // ── PENGATURAN: ATUR JAM NOTIFIKASI ─────────────────────────────────────

    if ($step === 'pengaturan_atur_jam') {
        $parts = preg_split('/\s+/', trim($text));
        
        // Guru hanya setting 1 jam (jadwal)
        if ($user['role'] === 'guru') {
            if (count($parts) !== 1) {
                sendMsg($chat, "❌ Format salah. Harus 1 jam saja.\n\nContoh: `07:00`");
                return true;
            }
            
            // Validasi format HH:MM
            $pattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
            if (!preg_match($pattern, $parts[0])) {
                sendMsg($chat, "❌ Format jam tidak valid: `{$parts[0]}`\n\nGunakan format HH:MM (misal: 07:00)");
                return true;
            }
            
            setState($chat, [
                'step'           => 'pengaturan_konfirmasi_jam',
                'jam_jadwal'     => $parts[0],
                'jam_tugas'      => null, // Guru tidak perlu tugas
                'user_cache'     => $user,
            ]);
            
            $pesan = "⏰ *Konfirmasi Jam Notifikasi*\n\n"
                   . "📅 Jadwal: *{$parts[0]}*\n\n"
                   . "Simpan pengaturan ini?";
            sendMsg($chat, $pesan, [['✅ Ya, Simpan', '❌ Batal']]);
            return true;
        }
        
        // Siswa setting 2 jam (jadwal dan tugas)
        if (count($parts) !== 2) {
            sendMsg($chat, "❌ Format salah. Harus 2 jam dengan spasi.\n\nContoh: `07:00 12:00`");
            return true;
        }

        // Validasi format HH:MM
        $pattern = '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/';
        foreach ($parts as $jam) {
            if (!preg_match($pattern, $jam)) {
                sendMsg($chat, "❌ Format jam tidak valid: `{$jam}`\n\nGunakan format HH:MM (misal: 07:00)");
                return true;
            }
        }

        setState($chat, [
            'step'           => 'pengaturan_konfirmasi_jam',
            'jam_jadwal'     => $parts[0],
            'jam_tugas'      => $parts[1],
            'user_cache'     => $user,
        ]);

        $pesan = "⏰ *Konfirmasi Jam Notifikasi*\n\n"
               . "📅 Jadwal: *{$parts[0]}*\n"
               . "📝 Tugas: *{$parts[1]}*\n\n"
               . "Simpan pengaturan ini?";
        sendMsg($chat, $pesan, [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    if ($step === 'pengaturan_konfirmasi_jam') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'pengaturan_pilih_menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Dibatalkan.", settingsKeyboard());
            return true;
        }

        if ($text === '✅ Ya, Simpan') {
            // Guru hanya setting jadwal
            if ($user['role'] === 'guru') {
                $ok = updateJamNotifikasi(
                    (int)$user['akun_id'],
                    (string)$state['jam_jadwal'],
                    '12:00' // Guru tidak perlu tugas, gunakan default
                );
                
                setState($chat, [
                    'step'       => 'menu',
                    'user_cache' => $user,
                ]);
                
                if ($ok) {
                    $pesan = "✅ *Jam Notifikasi Jadwal Berhasil Diubah!*\n\n"
                           . "📅 Jadwal: {$state['jam_jadwal']}";
                    sendMsg($chat, $pesan, mainKeyboard($user['role']));
                } else {
                    sendMsg($chat, "❌ Gagal mengubah jam notifikasi. Coba lagi.", mainKeyboard($user['role']));
                }
            } else {
                // Siswa setting jadwal dan tugas
                $ok = updateJamNotifikasi(
                    (int)$user['akun_id'],
                    (string)$state['jam_jadwal'],
                    (string)$state['jam_tugas']
                );
                
                setState($chat, [
                    'step'       => 'menu',
                    'user_cache' => $user,
                ]);
                
                if ($ok) {
                    $pesan = "✅ *Jam Notifikasi Berhasil Diubah!*\n\n"
                           . "📅 Jadwal: {$state['jam_jadwal']}\n"
                           . "📝 Tugas: {$state['jam_tugas']}";
                    sendMsg($chat, $pesan, mainKeyboard($user['role']));
                } else {
                    sendMsg($chat, "❌ Gagal mengubah jam notifikasi. Coba lagi.", mainKeyboard($user['role']));
                }
            }
            return true;
        }

        sendMsg($chat, "Pilih: *✅ Ya, Simpan* atau *❌ Batal*", [['✅ Ya, Simpan', '❌ Batal']]);
        return true;
    }

    // ── PENGUMUMAN: PILIH KELAS ────────────────────────────────────────────

    if ($step === 'pengumuman_pilih_kelas') {
        if ($text === '🔙 Kembali') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        $kelas = sirey_fetch(sirey_query(
            'SELECT g.grup_id, g.nama_grup
             FROM grup_rayhanRP g
             INNER JOIN guru_mengajar_rayhanRP gm ON g.grup_id = gm.grup_id
             WHERE g.nama_grup = ? AND gm.akun_id = ? LIMIT 1',
            'si', $text, (int)$user['akun_id']
        ));

        if (!$kelas) {
            sendMsg($chat, "❌ Kelas tidak valid.");
            return true;
        }

        setState($chat, [
            'step'       => 'pengumuman_input_judul',
            'grup_id'    => (int)$kelas['grup_id'],
            'kelas'      => $kelas['nama_grup'],
            'user_cache' => $user,
        ]);
        sendMsgRemoveKeyboard($chat, "📢 Kelas: *{$kelas['nama_grup']}*\n\nMasukkan *judul pengumuman*:");
        return true;
    }

    if ($step === 'pengumuman_input_judul') {
        if (strlen($text) < 3) {
            sendMsg($chat, "Judul terlalu pendek. Masukkan judul yang jelas:");
            return true;
        }

        setState($chat, [
            'step'       => 'pengumuman_input_isi',
            'judul'      => $text,
            'grup_id'    => (int)($state['grup_id'] ?? 0),
            'kelas'      => $state['kelas'] ?? '',
            'user_cache' => $user,
        ]);
        sendMsg($chat, "📋 Masukkan *isi pengumuman*:\n\n(Jelaskan dengan detail)");
        return true;
    }

    if ($step === 'pengumuman_input_isi') {
        if (strlen($text) < 5) {
            sendMsg($chat, "❌ Isi pengumuman terlalu pendek. Minimal 5 karakter.");
            return true;
        }

        setState($chat, [
            'step'       => 'pengumuman_konfirmasi',
            'judul'      => $state['judul'] ?? '',
            'isi'        => $text,
            'grup_id'    => (int)($state['grup_id'] ?? 0),
            'kelas'      => $state['kelas'] ?? '',
            'user_cache' => $user,
        ]);

        $konfirmasi = "✅ *Konfirmasi Pengumuman*\n\n"
                    . "🎓 Kelas: {$state['kelas']}\n"
                    . "📢 Judul: {$state['judul']}\n"
                    . "📋 Isi:\n{$text}\n\n"
                    . "Kirim pengumuman ini?";
        sendMsg($chat, $konfirmasi, [['✅ Ya, Kirim', '❌ Batal']]);
        return true;
    }

    if ($step === 'pengumuman_konfirmasi') {
        if ($text === '❌ Batal') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Dibatalkan.", mainKeyboard($user['role']));
            return true;
        }

        if ($text === '✅ Ya, Kirim') {
            $ok = simpanPengumumanGuru([
                'judul'      => $state['judul'] ?? '',
                'isi'        => $state['isi'] ?? '',
                'grup_id'    => (int)($state['grup_id'] ?? 0),
                'pembuat_id' => (int)$user['akun_id'],
            ]);

            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);

            if ($ok) {
                sendMsg($chat, "✅ *Pengumuman berhasil dikirim!*\n\nSiswa sudah menerima notifikasi pengumuman.", mainKeyboard($user['role']));
            } else {
                sendMsg($chat, "❌ Gagal mengirim pengumuman. Coba lagi.", mainKeyboard($user['role']));
            }
            return true;
        }

        sendMsg($chat, "Pilih: *✅ Ya, Kirim* atau *❌ Batal*", [['✅ Ya, Kirim', '❌ Batal']]);
        return true;
    }

    // ── ANALISIS TUGAS: PILIH TUGAS ─────────────────────────────────────────

    if ($step === 'analisis_tugas_pilih') {
        if ($text === '🔙 Kembali') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        // Cari tugas berdasarkan nomor - gunakan tugasList dari state jika ada, atau fetch baru
        $tugasList = $state['tugasList'] ?? getTugasAnalisisForGuru((int)$user['akun_id']);
        $tugasMatch = null;
        
        // Parse nomor tugas (text berisi "1", "2", "3", dll)
        if (is_numeric($text)) {
            $nomorTugas = (int)$text;
            $idx = $nomorTugas - 1; // Convert ke 0-based index
            
            if (isset($tugasList[$idx])) {
                $tugasMatch = $tugasList[$idx];
            }
        }

        if (!$tugasMatch) {
            sendMsg($chat, "❌ Nomor tugas tidak valid. Pilih nomor dari daftar yang ditampilkan:");
            return true;
        }

        // Ambil detail analisis
        $analisis = getTugasAnalisisDetail((int)$tugasMatch['tugas_id']);
        if (empty($analisis['tugas'])) {
            sendMsg($chat, "❌ Gagal memuat data tugas.");
            return true;
        }

        $t = $analisis['tugas'];
        $allCount = count($analisis['all_siswa']);
        $kumpulCount = count($analisis['sudah_kumpul']);
        $belumCount = count($analisis['belum_kumpul']);

        $pesan = "📋 *Analisis Tugas*\n\n";
        $pesan .= "*{$t['judul']}*\n";
        $pesan .= "📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n";
        
        // Tambahkan wali kelas jika ada
        if ($t['wali_kelas'] && $t['wali_kelas'] !== '-') {
            $pesan .= "👨‍💼 Wali: {$t['wali_kelas']}\n";
        }
        
        $pesan .= "\n";
        $pesan .= "📊 *Statistik Pengumpulan:*\n";
        $pesan .= "   👥 Total siswa: {$allCount}\n";
        $pesan .= "   ✅ Sudah kumpul: {$kumpulCount}\n";
        $pesan .= "   ⏳ Belum kumpul: {$belumCount}\n\n";

        // Daftar yang sudah kumpul
        if (!empty($analisis['sudah_kumpul'])) {
            $pesan .= "✅ *Sudah Mengumpulkan:*\n";
            foreach ($analisis['sudah_kumpul'] as $s) {
                $pesan .= "  • {$s['nama_lengkap']} ({$s['nis_nip']})\n";
            }
            $pesan .= "\n";
        }

        // Daftar yang belum kumpul
        if (!empty($analisis['belum_kumpul'])) {
            $pesan .= "⏳ *Belum Mengumpulkan:*\n";
            foreach ($analisis['belum_kumpul'] as $s) {
                $pesan .= "  • {$s['nama_lengkap']} ({$s['nis_nip']})\n";
            }
        } else {
            $pesan .= "✅ Semua siswa sudah mengumpulkan!\n";
        }

        setState($chat, [
            'step'       => 'menu',
            'user_cache' => $user,
        ]);

        sendMsg($chat, $pesan, mainKeyboard($user['role']));
        return true;
    }

    // ── NILAI TUGAS: PILIH TUGAS ────────────────────────────────────────────
    if ($step === 'nilai_pilih_tugas') {
        if ($text === '🔙 Kembali') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
            return true;
        }

        $tugas_list = $state['tugas_list'] ?? [];
        $pilihan = (int)$text;

        if ($pilihan < 1 || $pilihan > count($tugas_list)) {
            sendMsg($chat, "❌ Pilihan tidak valid. Masukkan nomor tugas dari daftar.");
            return true;
        }

        $tugasSelected = $tugas_list[$pilihan - 1];
        $pengumpulanList = getPengumpulanTugas((int)$tugasSelected['tugas_id']);

        if (empty($pengumpulanList)) {
            sendMsg($chat, "❌ Tidak ada pengumpulan untuk tugas ini.");
            return true;
        }

        $pesan = "📝 *Daftar Pengumpulan*\n\n";
        $pesan .= "*{$tugasSelected['judul']}*\n";
        $pesan .= "💯 Poin Maksimal: {$tugasSelected['poin_maksimal']}\n\n";
        
        foreach ($pengumpulanList as $idx => $p) {
            $no = $idx + 1;
            $statusEmoji = $p['nilai'] !== null ? '✅' : '⏳';
            $pesan .= "*{$no}.* {$statusEmoji} {$p['nama_lengkap']} ({$p['nis_nip']})\n";
            if ($p['nilai'] !== null) {
                $pesan .= "   Nilai: {$p['nilai']} | Catatan: {$p['catatan_guru']}\n";
            }
            $pesan .= "\n";
        }

        $keyboard = [];
        foreach ($pengumpulanList as $idx => $p) {
            $no = $idx + 1;
            if ($idx % 2 === 0) {
                $keyboard[] = [(string)$no];
            } else {
                $keyboard[count($keyboard) - 1][] = (string)$no;
            }
        }
        $keyboard[] = ['🔙 Kembali'];

        setState($chat, [
            'step'               => 'nilai_pilih_siswa',
            'tugas_id'           => (int)$tugasSelected['tugas_id'],
            'tugas_judul'        => $tugasSelected['judul'],
            'poin_maksimal'      => (int)$tugasSelected['poin_maksimal'],
            'pengumpulan_list'   => $pengumpulanList,
            'user_cache'         => $user,
        ]);

        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── NILAI TUGAS: PILIH SISWA ────────────────────────────────────────────
    if ($step === 'nilai_pilih_siswa') {
        if ($text === '🔙 Kembali') {
            // Kembali ke pilih tugas
            $tugasList = getTugasUntukNilai((int)$user['akun_id']);
            if (empty($tugasList)) {
                setState($chat, [
                    'step'       => 'menu',
                    'user_cache' => $user,
                ]);
                sendMsg($chat, "↩️ Kembali ke menu utama", mainKeyboard($user['role']));
                return true;
            }

            $pesan = "⭐ *Nilai Tugas*\n\nPilih tugas untuk menilai pengumpulan:\n\n";
            foreach ($tugasList as $idx => $t) {
                $no = $idx + 1;
                $belumDinilai = (int)$t['jml_pengumpulan'] - (int)$t['jml_sudah_dinilai'];
                $emoji = $belumDinilai > 0 ? '🔴' : '✅';
                $pesan .= "{$emoji} *{$no}.* {$t['judul']}\n   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n   📊 {$t['jml_sudah_dinilai']}/{$t['jml_pengumpulan']} dinilai\n\n";
            }

            $keyboard = [];
            foreach ($tugasList as $idx => $p) {
                $no = $idx + 1;
                if ($idx % 2 === 0) {
                    $keyboard[] = [(string)$no];
                } else {
                    $keyboard[count($keyboard) - 1][] = (string)$no;
                }
            }
            $keyboard[] = ['🔙 Kembali'];

            setState($chat, [
                'step'       => 'nilai_pilih_tugas',
                'tugas_list' => $tugasList,
                'user_cache' => $user,
            ]);

            sendMsg($chat, $pesan, $keyboard);
            return true;
        }

        $pengumpulanList = $state['pengumpulan_list'] ?? [];
        $pilihan = (int)$text;

        if ($pilihan < 1 || $pilihan > count($pengumpulanList)) {
            sendMsg($chat, "❌ Pilihan tidak valid. Masukkan nomor siswa dari daftar.");
            return true;
        }

        $pengumpulanSelected = $pengumpulanList[$pilihan - 1];
        $detail = getPengumpulanDetail((int)$pengumpulanSelected['pengumpulan_id']);

        if (!$detail) {
            sendMsg($chat, "❌ Gagal memuat data pengumpulan.");
            return true;
        }

        $pesan = formatPreviewPengumpulanGuru($detail);

        setState($chat, [
            'step'                  => 'nilai_lihat_jawaban',
            'pengumpulan_id'        => (int)$detail['pengumpulan_id'],
            'pengumpulan_detail'    => $detail,
            'user_cache'            => $user,
        ]);

        $keyboard = [['✏️ Nilai'], ['⬅️ Kembali']];
        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── NILAI TUGAS: LIHAT JAWABAN & INPUT NILAI ────────────────────────────
    if ($step === 'nilai_lihat_jawaban') {
        if ($text === '⬅️ Kembali' || $text === '🔙 Kembali') {
            // Kembali ke daftar siswa
            $pengumpulanList = $state['pengumpulan_list'] ?? [];
            $pesan = "📝 *Daftar Pengumpulan*\n\n";
            $pesan .= "*{$state['tugas_judul']}*\n";
            $pesan .= "💯 Poin Maksimal: {$state['poin_maksimal']}\n\n";
            
            foreach ($pengumpulanList as $idx => $p) {
                $no = $idx + 1;
                $statusEmoji = $p['nilai'] !== null ? '✅' : '⏳';
                $pesan .= "*{$no}.* {$statusEmoji} {$p['nama_lengkap']} ({$p['nis_nip']})\n";
                if ($p['nilai'] !== null) {
                    $pesan .= "   Nilai: {$p['nilai']} | Catatan: {$p['catatan_guru']}\n";
                }
                $pesan .= "\n";
            }

            $keyboard = [];
            foreach ($pengumpulanList as $idx => $p) {
                $no = $idx + 1;
                if ($idx % 2 === 0) {
                    $keyboard[] = [(string)$no];
                } else {
                    $keyboard[count($keyboard) - 1][] = (string)$no;
                }
            }
            $keyboard[] = ['🔙 Kembali'];

            setState($chat, [
                'step'             => 'nilai_pilih_siswa',
                'tugas_id'         => $state['tugas_id'],
                'tugas_judul'      => $state['tugas_judul'],
                'poin_maksimal'    => $state['poin_maksimal'],
                'pengumpulan_list' => $pengumpulanList,
                'user_cache'       => $user,
            ]);

            sendMsg($chat, $pesan, $keyboard);
            return true;
        }

        if ($text === '✏️ Nilai') {
            $detail = $state['pengumpulan_detail'] ?? [];
            $pesan = formatFormNilai($detail);

            setState($chat, [
                'step'               => 'nilai_input_nilai',
                'pengumpulan_id'     => $state['pengumpulan_id'],
                'pengumpulan_detail' => $detail,
                'user_cache'         => $user,
            ]);

            sendMsg($chat, $pesan);
            return true;
        }

        sendMsg($chat, "Gunakan tombol di bawah untuk melanjutkan.");
        return true;
    }

    // ── NILAI TUGAS: INPUT NILAI ────────────────────────────────────────────
    if ($step === 'nilai_input_nilai') {
        // Validasi input nilai (numeric)
        $nilai = (float)str_replace(',', '.', $text);
        $poinMax = (float)($state['pengumpulan_detail']['poin_maksimal'] ?? 100);

        if (!is_numeric($text) || $nilai < 0 || $nilai > $poinMax) {
            $pesan = "❌ Nilai tidak valid!\n\n";
            $pesan .= "Masukkan nilai antara *0* dan *{$poinMax}*\n";
            $pesan .= "(Contoh: 85 atau 90.5)";
            sendMsg($chat, $pesan);
            return true;
        }

        setState($chat, [
            'step'               => 'nilai_input_catatan',
            'pengumpulan_id'     => $state['pengumpulan_id'],
            'pengumpulan_detail' => $state['pengumpulan_detail'],
            'nilai_input'        => $nilai,
            'user_cache'         => $user,
        ]);

        $pesan = "✏️ *Input Catatan (Opsional)*\n\n";
        $pesan .= "Nilai: *{$nilai}*\n\n";
        $pesan .= "Ketik catatan untuk siswa (atau tekan ✓ untuk lanjut tanpa catatan):";

        $keyboard = [['✓ Lanjut Tanpa Catatan']];
        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── NILAI TUGAS: INPUT CATATAN ──────────────────────────────────────────
    if ($step === 'nilai_input_catatan') {
        $catatan = '';

        if ($text === '✓ Lanjut Tanpa Catatan') {
            $catatan = '';
        } else if (!empty($text)) {
            $catatan = $text;
        }

        $detail = $state['pengumpulan_detail'] ?? [];
        $nilaiInput = $state['nilai_input'] ?? 0;
        $pesan = formatKonfirmasiNilai($detail, $nilaiInput, $catatan ?: null);

        setState($chat, [
            'step'               => 'nilai_konfirmasi',
            'pengumpulan_id'     => $state['pengumpulan_id'],
            'pengumpulan_detail' => $detail,
            'nilai_input'        => $nilaiInput,
            'catatan_input'      => $catatan,
            'user_cache'         => $user,
        ]);

        $keyboard = [['✅ Simpan'], ['✏️ Edit'], ['❌ Batalkan']];
        sendMsg($chat, $pesan, $keyboard);
        return true;
    }

    // ── NILAI TUGAS: KONFIRMASI & SIMPAN ────────────────────────────────────
    if ($step === 'nilai_konfirmasi') {
        if ($text === '❌ Batalkan') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "❌ Penilaian dibatalkan. Kembali ke menu.", mainKeyboard($user['role']));
            return true;
        }

        if ($text === '✏️ Edit') {
            $detail = $state['pengumpulan_detail'] ?? [];
            $pesan = formatFormNilai($detail);

            setState($chat, [
                'step'               => 'nilai_input_nilai',
                'pengumpulan_id'     => $state['pengumpulan_id'],
                'pengumpulan_detail' => $detail,
                'user_cache'         => $user,
            ]);

            sendMsg($chat, $pesan);
            return true;
        }

        if ($text === '✅ Simpan') {
            $hasil = savePenilaian(
                (int)$state['pengumpulan_id'],
                (int)$user['akun_id'],
                (float)$state['nilai_input'],
                $state['catatan_input'] ?: null,
                'lulus'
            );

            if ($hasil['success']) {
                $detail = $state['pengumpulan_detail'] ?? [];
                $pesan = "✅ *Penilaian Berhasil Disimpan*\n\n";
                $pesan .= "👤 Siswa: {$detail['nama_lengkap']}\n";
                $pesan .= "📝 Tugas: {$detail['judul']}\n";
                $pesan .= "💯 Nilai: {$state['nilai_input']}/{$detail['poin_maksimal']}\n";
                
                if ($state['catatan_input']) {
                    $pesan .= "📋 Catatan: {$state['catatan_input']}\n";
                }

                $pesan .= "\n🎯 Apa selanjutnya?";

                $keyboard = [
                    ['⭐ Nilai Tugas Lain'],
                    ['🏠 Kembali ke Menu'],
                ];

                setState($chat, [
                    'step'       => 'nilai_selesai',
                    'user_cache' => $user,
                ]);

                sendMsg($chat, $pesan, $keyboard);
            } else {
                sendMsg($chat, "❌ Gagal menyimpan penilaian: " . $hasil['message']);
            }

            return true;
        }

        sendMsg($chat, "Gunakan tombol untuk melanjutkan.");
        return true;
    }

    // ── NILAI TUGAS: SELESAI ────────────────────────────────────────────────
    if ($step === 'nilai_selesai') {
        if ($text === '⭐ Nilai Tugas Lain') {
            $tugasList = getTugasUntukNilai((int)$user['akun_id']);
            
            if (empty($tugasList)) {
                sendMsg($chat, "❌ Tidak ada tugas yang perlu dinilai.", mainKeyboard($user['role']));
                setState($chat, [
                    'step'       => 'menu',
                    'user_cache' => $user,
                ]);
                return true;
            }

            $pesan = "⭐ *Nilai Tugas*\n\nPilih tugas untuk menilai pengumpulan:\n\n";
            foreach ($tugasList as $idx => $t) {
                $no = $idx + 1;
                $belumDinilai = (int)$t['jml_pengumpulan'] - (int)$t['jml_sudah_dinilai'];
                $emoji = $belumDinilai > 0 ? '🔴' : '✅';
                $pesan .= "{$emoji} *{$no}.* {$t['judul']}\n   📚 {$t['matpel']} | 🎓 {$t['nama_grup']}\n   📊 {$t['jml_sudah_dinilai']}/{$t['jml_pengumpulan']} dinilai\n\n";
            }

            $keyboard = [];
            foreach ($tugasList as $idx => $p) {
                $no = $idx + 1;
                if ($idx % 2 === 0) {
                    $keyboard[] = [(string)$no];
                } else {
                    $keyboard[count($keyboard) - 1][] = (string)$no;
                }
            }
            $keyboard[] = ['🔙 Kembali'];

            setState($chat, [
                'step'       => 'nilai_pilih_tugas',
                'tugas_list' => $tugasList,
                'user_cache' => $user,
            ]);

            sendMsg($chat, $pesan, $keyboard);
            return true;
        }

        if ($text === '🏠 Kembali ke Menu') {
            setState($chat, [
                'step'       => 'menu',
                'user_cache' => $user,
            ]);
            sendMsg($chat, "🏠 Menu utama:", mainKeyboard($user['role']));
            return true;
        }

        setState($chat, [
            'step'       => 'menu',
            'user_cache' => $user,
        ]);
        sendMsg($chat, "🏠 Menu utama:", mainKeyboard($user['role']));
        return true;
    }

    // ── MENU (step = 'menu') ────────────────────────────────────────────────
    if ($step === 'menu') {
        return false; // Biar handleMenu yang handle
    }

    return false;
}


// ============================================================
// HELPERS INTERNAL
// ============================================================

function verifyPassword(array $akun, string $input): bool {
    $stored = (string)$akun['password'];
    if (str_starts_with($stored, '$2')) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

function handleBuatTugas(int $chat, int $guruId): bool {
    $mapelList = getMatpelGuru($guruId);
    if (empty($mapelList)) {
        sendMsg($chat, "❌ Anda belum memiliki assignment mapel. Hubungi admin.");
        return true;
    }
    $keyboard = array_chunk(array_map(fn($m) => $m['nama'], $mapelList), 2);
    $keyboard[] = ['🔙 Kembali ke Menu'];

    setState(
        // chat_id diteruskan melalui closure tidak bisa, jadi simpan via parameter global
        // kita pakai trick: state di-set sebelum return
        0, [] // placeholder — akan di-override di bawah
    );

    // Override state dengan benar
    $GLOBALS['_chat_buat_tugas'] = $chat;
    setState($chat, ['step' => 'tugas_pilih_mapel']);
    sendMsg($chat, "✏️ *Buat Tugas Baru*\n\nPilih *mata pelajaran*:", $keyboard);
    return true;
}
