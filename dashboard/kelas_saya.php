<?php
declare(strict_types=1);

$judul_halaman_rayhanrp = 'Kelas Saya';
$menu_aktif_rayhanrp    = 'kelas_saya';
require_once __DIR__ . '/_layout.php';

if ($data_admin_rayhanrp['role'] !== 'guru') {
    header('Location: dashboard.php?err=akses');
    exit;
}

$id_guru = (int)$data_admin_rayhanrp['id'];

// ── Ambil semua kelas yang diwalikan (guru wali TIDAK harus mengajar) ──
$kelasSaya = sirey_fetchAll(sirey_query(
    "SELECT
        g.grup_id, g.nama_grup, g.tingkat, g.jurusan, g.deskripsi,

        (SELECT COUNT(*) FROM grup_anggota_rayhanRP ga
         INNER JOIN akun_rayhanRP a ON ga.akun_id = a.akun_id
         WHERE ga.grup_id = g.grup_id AND ga.aktif = 1 AND a.role = 'siswa'
        ) AS jumlah_siswa,

        (SELECT COUNT(DISTINCT gm.akun_id) FROM guru_mengajar_rayhanRP gm
         WHERE gm.grup_id = g.grup_id AND gm.aktif = 1
        ) AS jumlah_guru,

        (SELECT COUNT(DISTINCT gm.matpel_id) FROM guru_mengajar_rayhanRP gm
         WHERE gm.grup_id = g.grup_id AND gm.aktif = 1
        ) AS jumlah_mapel,

        (SELECT COUNT(*) FROM tugas_rayhanRP t
         WHERE t.grup_id = g.grup_id AND t.status = 'active'
        ) AS jumlah_tugas_aktif,

        (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         WHERE t.grup_id = g.grup_id
        ) AS jumlah_pengumpulan,

        (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         LEFT JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         WHERE t.grup_id = g.grup_id AND pn.nilai IS NULL
        ) AS belum_dinilai,

        (SELECT ROUND(AVG(pn.nilai), 1)
         FROM penilaian_rayhanRP pn
         INNER JOIN pengumpulan_rayhanRP p ON pn.pengumpulan_id = p.pengumpulan_id
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         WHERE t.grup_id = g.grup_id AND pn.nilai IS NOT NULL
        ) AS rata_nilai,

        (SELECT COUNT(*) FROM tugas_rayhanRP t
         WHERE t.grup_id = g.grup_id AND t.status = 'active'
           AND t.tenggat BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        ) AS tugas_deadline_dekat

     FROM grup_rayhanRP g
     WHERE g.wali_kelas_id = ? AND g.aktif = 1
     ORDER BY g.tingkat ASC, g.nama_grup ASC",
    'i', $id_guru
));

// ── Detail per kelas (untuk embed di tab) ──
$detail = [];
foreach ($kelasSaya as $k) {
    $gid = (int)$k['grup_id'];

    // 1. Daftar siswa
    $detail[$gid]['siswa'] = sirey_fetchAll(sirey_query(
        "SELECT a.akun_id, a.nis_nip, a.nama_lengkap, a.jenis_kelamin,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
                 WHERE p.akun_id = a.akun_id AND t.grup_id = ?) AS jml_kumpul,
                (SELECT ROUND(AVG(pn.nilai),1)
                 FROM penilaian_rayhanRP pn
                 INNER JOIN pengumpulan_rayhanRP p ON pn.pengumpulan_id = p.pengumpulan_id
                 INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
                 WHERE p.akun_id = a.akun_id AND t.grup_id = ? AND pn.nilai IS NOT NULL
                ) AS rata_nilai_siswa
         FROM grup_anggota_rayhanRP ga
         INNER JOIN akun_rayhanRP a ON ga.akun_id = a.akun_id
         WHERE ga.grup_id = ? AND ga.aktif = 1 AND a.role = 'siswa'
         ORDER BY a.nama_lengkap ASC",
        'iii', $gid, $gid, $gid
    ));

    // 2. Semua tugas kelas (bukan hanya guru ini)
    $detail[$gid]['tugas'] = sirey_fetchAll(sirey_query(
        "SELECT t.tugas_id, t.judul, t.tenggat, t.poin_maksimal, t.status,
                mp.nama AS matpel,
                a.nama_lengkap AS pembuat,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p WHERE p.tugas_id = t.tugas_id) AS jml_kumpul,
                (SELECT COUNT(*) FROM pengumpulan_rayhanRP p
                 INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
                 WHERE p.tugas_id = t.tugas_id AND pn.nilai IS NOT NULL) AS jml_dinilai,
                (SELECT COUNT(*)
                 FROM grup_anggota_rayhanRP ga INNER JOIN akun_rayhanRP aa ON ga.akun_id = aa.akun_id
                 WHERE ga.grup_id = t.grup_id AND ga.aktif = 1 AND aa.role = 'siswa') AS total_siswa
         FROM tugas_rayhanRP t
         LEFT JOIN mata_pelajaran_rayhanRP mp ON t.matpel_id = mp.matpel_id
         LEFT JOIN akun_rayhanRP a ON t.pembuat_id = a.akun_id
         WHERE t.grup_id = ?
         ORDER BY t.tenggat DESC",
        'i', $gid
    ));

    // 3. Penilaian — semua pengumpulan kelas ini
    $detail[$gid]['penilaian'] = sirey_fetchAll(sirey_query(
        "SELECT a.nama_lengkap AS siswa, t.judul, pn.nilai, t.poin_maksimal,
                pn.status_lulus, pn.catatan_guru, pn.dinilai_pada,
                g2.nama_lengkap AS guru_penilai
         FROM pengumpulan_rayhanRP p
         INNER JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         INNER JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         INNER JOIN penilaian_rayhanRP pn ON pn.pengumpulan_id = p.pengumpulan_id
         LEFT JOIN akun_rayhanRP g2 ON pn.dinilai_oleh = g2.akun_id
         WHERE t.grup_id = ? AND pn.nilai IS NOT NULL
         ORDER BY pn.dinilai_pada DESC",
        'i', $gid
    ));

    // 4. Jadwal kelas (semua guru yang mengajar)
    $detail[$gid]['jadwal'] = sirey_fetchAll(sirey_query(
        "SELECT gm.hari, gm.jam_mulai, gm.jam_selesai,
                mp.nama AS matpel, mp.kode,
                a.nama_lengkap AS guru
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN akun_rayhanRP a ON gm.akun_id = a.akun_id
         INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
         WHERE gm.grup_id = ? AND gm.aktif = 1
         ORDER BY FIELD(gm.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), gm.jam_mulai ASC",
        'i', $gid
    ));

    // 5. Guru pengajar di kelas ini
    $detail[$gid]['guru'] = sirey_fetchAll(sirey_query(
        "SELECT a.nama_lengkap,
                GROUP_CONCAT(DISTINCT mp.nama ORDER BY mp.nama SEPARATOR ', ') AS mapel_list,
                GROUP_CONCAT(DISTINCT CONCAT(gm.hari,' ',SUBSTRING(gm.jam_mulai,1,5),'-',SUBSTRING(gm.jam_selesai,1,5))
                             ORDER BY FIELD(gm.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu')
                             SEPARATOR ' · ') AS jadwal_list
         FROM guru_mengajar_rayhanRP gm
         INNER JOIN akun_rayhanRP a ON gm.akun_id = a.akun_id
         INNER JOIN mata_pelajaran_rayhanRP mp ON gm.matpel_id = mp.matpel_id
         WHERE gm.grup_id = ? AND gm.aktif = 1
         GROUP BY a.akun_id, a.nama_lengkap
         ORDER BY a.nama_lengkap ASC",
        'i', $gid
    ));
}

function pcolor(int $p): string {
    return $p >= 75 ? '#059669' : ($p >= 50 ? '#d97706' : '#dc2626');
}
$hariOrder = ['Senin'=>1,'Selasa'=>2,'Rabu'=>3,'Kamis'=>4,'Jumat'=>5,'Sabtu'=>6,'Minggu'=>7];
?>

<style>
/* ═══ HEADER ═══ */
.ks-hero {
    background: linear-gradient(135deg,#1a2e4a 0%,#213447 55%,#2a4060 100%);
    border-radius:18px; padding:32px 36px; margin-bottom:32px;
    color:#fff; position:relative; overflow:hidden;
}
.ks-hero::before {
    content:''; position:absolute;
    width:360px; height:360px;
    background:radial-gradient(circle,rgba(230,126,34,.22) 0%,transparent 70%);
    top:-100px; right:-60px; pointer-events:none;
}
.ks-hero::after {
    content:''; position:absolute;
    width:180px; height:180px;
    background:radial-gradient(circle,rgba(22,160,133,.18) 0%,transparent 70%);
    bottom:-40px; left:20px; pointer-events:none;
}
.ks-hero h2 { font-size:24px;font-weight:900;margin:0 0 6px;position:relative;z-index:1; }
.ks-hero p  { color:rgba(255,255,255,.6);font-size:13px;margin:0;position:relative;z-index:1; }
.wali-chip  {
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(230,126,34,.18); border:1px solid rgba(230,126,34,.4);
    color:#f59e0b; padding:5px 14px; border-radius:20px;
    font-size:12px;font-weight:700;margin-top:12px;position:relative;z-index:1;
}

/* ═══ SUMMARY STATS BAR ═══ */
.summary-bar {
    display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px;
}
.sbar-item {
    background:#fff; border:1px solid #e2e8f0; border-radius:14px;
    padding:18px 20px; display:flex; align-items:center; gap:14px;
    box-shadow:0 1px 3px rgba(0,0,0,.05);
}
.sbar-icon { width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0; }
.sbar-val  { font-size:26px;font-weight:900;line-height:1;font-family:'Plus Jakarta Sans',sans-serif; }
.sbar-lbl  { font-size:11px;color:#64748b;margin-top:2px;font-weight:500; }

/* ═══ KELAS CARD — full width ═══ */
.kelas-card {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 2px 6px rgba(0,0,0,.05),0 6px 24px rgba(15,23,42,.06);
    margin-bottom:28px;
    transition:box-shadow .2s;
}
.kelas-card:hover { box-shadow:0 6px 28px rgba(15,23,42,.11); }

/* Top accent stripe */
.kc-stripe { height:5px; background:linear-gradient(90deg,#E67E22,#f59e0b,#16A085); }

/* Card header row */
.kc-head {
    display:flex; align-items:flex-start; justify-content:space-between;
    padding:22px 28px 18px; gap:20px; flex-wrap:wrap;
    border-bottom:1px solid #f1f5f9;
}
.kc-title { font-size:20px;font-weight:900;color:#0f172a;margin:0 0 8px;font-family:'Plus Jakarta Sans',sans-serif; }
.kc-badges { display:flex;flex-wrap:wrap;gap:6px; }

/* Stat mini tiles in header */
.kc-stats {
    display:flex; gap:10px; flex-wrap:wrap; flex-shrink:0;
}
.kc-stat {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;
    padding:10px 14px; text-align:center; min-width:72px;
}
.kc-stat .n { font-size:20px;font-weight:800;line-height:1;font-family:'Plus Jakarta Sans',sans-serif; }
.kc-stat .l { font-size:10px;color:#64748b;margin-top:2px;font-weight:500; }
.kc-stat.c-blue   { background:#eff6ff;border-color:#bfdbfe; }  .kc-stat.c-blue   .n { color:#2563eb; }
.kc-stat.c-green  { background:#f0fdf4;border-color:#bbf7d0; }  .kc-stat.c-green  .n { color:#059669; }
.kc-stat.c-amber  { background:#fffbeb;border-color:#fde68a; }  .kc-stat.c-amber  .n { color:#d97706; }
.kc-stat.c-red    { background:#fff1f2;border-color:#fecdd3; }  .kc-stat.c-red    .n { color:#e11d48; }
.kc-stat.c-purple { background:#f5f3ff;border-color:#ddd6fe; }  .kc-stat.c-purple .n { color:#7c3aed; }
.kc-stat.c-slate  { background:#f8fafc;border-color:#e2e8f0; }  .kc-stat.c-slate  .n { color:#475569; }

/* Rata-rata progress */
.kc-avg {
    padding:14px 28px;
    border-bottom:1px solid #f1f5f9;
    display:flex; align-items:center; gap:16px;
}
.kc-avg-label { font-size:12px;font-weight:700;color:#475569;flex-shrink:0; }
.kc-avg-bar   { flex:1;height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden; }
.kc-avg-fill  { height:100%;border-radius:4px;transition:width .6s ease; }
.kc-avg-val   { font-size:13px;font-weight:800;flex-shrink:0; }

/* ═══ TAB NAV ═══ */
.kc-tab-nav {
    display:flex; border-bottom:2px solid #f1f5f9;
    background:#fafbfc; overflow-x:auto;
}
.kc-tab-btn {
    padding:13px 20px; font-size:13px; font-weight:600;
    color:#64748b; border:none; background:none; cursor:pointer;
    border-bottom:3px solid transparent; margin-bottom:-2px;
    white-space:nowrap; transition:all .15s; display:flex; align-items:center; gap:6px;
}
.kc-tab-btn:hover  { color:#E67E22; background:rgba(230,126,34,.04); }
.kc-tab-btn.active { color:#E67E22; border-bottom-color:#E67E22; background:#fff; }
.kc-tab-btn .tb-badge {
    background:#dc2626;color:#fff;border-radius:10px;
    padding:1px 7px;font-size:10px;font-weight:700;
}
.kc-tab-btn .tb-badge.green { background:#059669; }

/* ═══ TAB CONTENT ═══ */
.kc-tab-panel { display:none; padding:24px 28px; }
.kc-tab-panel.active { display:block; }

/* ── Siswa table ── */
.ks-table { width:100%;border-collapse:collapse;font-size:13px; }
.ks-table thead th {
    background:#f8fafc; padding:9px 12px; text-align:left;
    font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase;
    color:#64748b; border-bottom:2px solid #e2e8f0; white-space:nowrap;
}
.ks-table tbody td { padding:11px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.ks-table tbody tr:last-child td { border-bottom:none; }
.ks-table tbody tr:hover td { background:#f8fafc; }

/* ── Tugas list ── */
.tugas-row {
    display:flex; align-items:flex-start; gap:14px;
    padding:14px 0; border-bottom:1px solid #f1f5f9;
}
.tugas-row:last-child { border-bottom:none; }
.tugas-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px; }
.tugas-info { flex:1; }
.tugas-info .tj { font-weight:700;color:#0f172a;font-size:13.5px; }
.tugas-info .tm { font-size:12px;color:#64748b;margin-top:3px; }
.tugas-meta { display:flex;gap:10px;align-items:center;margin-top:6px;flex-wrap:wrap; }
.prog-wrap  { display:flex;align-items:center;gap:8px;flex:1;min-width:120px; }
.prog-bg    { flex:1;height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden; }
.prog-fill  { height:100%;border-radius:3px; }

/* ── Jadwal grid ── */
.jadwal-hari { margin-bottom:20px; }
.jadwal-hari-title {
    font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;
    color:#475569; padding:6px 10px; background:#f1f5f9;
    border-radius:6px; margin-bottom:8px; display:inline-block;
}
.jadwal-slot {
    display:flex;align-items:center;gap:12px;
    padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0;
    border-radius:10px; margin-bottom:6px; font-size:13px;
}
.jadwal-time { font-weight:700;color:#0f172a;min-width:100px; }
.jadwal-mp   { flex:1; }
.jadwal-guru { color:#64748b;font-size:12px; }

/* ── Penilaian ── */
.status-lulus       { background:#d1fae5;color:#065f46; }
.status-revisi      { background:#fef3c7;color:#92400e; }
.status-tidak_lulus { background:#fee2e2;color:#991b1b; }

/* ── Alert corner badge ── */
.kc-alert-badge {
    background:#fee2e2; color:#dc2626; border:1px solid #fca5a5;
    border-radius:20px; font-size:10px; font-weight:700;
    padding:3px 10px; display:inline-flex; align-items:center; gap:4px;
}

/* ── Empty ── */
.ks-empty { text-align:center;padding:48px 20px;color:#94a3b8; }
.ks-empty i { font-size:40px;display:block;margin-bottom:10px;opacity:.25; }
.ks-empty p { font-size:13px;color:#64748b; }

/* ── Responsive ── */
@media(max-width:768px){
    .summary-bar { grid-template-columns:1fr 1fr; }
    .kc-head { flex-direction:column; }
    .kc-stats { width:100%; }
    .kc-stat { flex:1; }
}
</style>

<!-- ══ HERO ══ -->
<div class="ks-hero">
    <h2><i class="bi bi-mortarboard-fill me-2"></i>Kelas Saya</h2>
    <p>Pantau seluruh aktivitas kelas yang Anda walikan secara langsung.</p>
    <div class="wali-chip">
        <i class="bi bi-shield-check-fill"></i>
        Wali Kelas — <?php echo htmlspecialchars($data_admin_rayhanrp['name']); ?>
    </div>
</div>

<?php if (empty($kelasSaya)): ?>
<div class="ks-empty">
    <i class="bi bi-mortarboard"></i>
    <p>Anda belum ditugaskan sebagai wali kelas manapun.<br>Hubungi admin atau kurikulum.</p>
</div>
<?php else: ?>

<!-- ══ SUMMARY BAR ══ -->
<div class="summary-bar">
    <div class="sbar-item">
        <div class="sbar-icon" style="background:#eff6ff;"><i class="bi bi-mortarboard-fill" style="color:#2563eb;"></i></div>
        <div>
            <div class="sbar-val" style="color:#2563eb;"><?php echo count($kelasSaya); ?></div>
            <div class="sbar-lbl">Kelas Diwalikan</div>
        </div>
    </div>
    <div class="sbar-item">
        <div class="sbar-icon" style="background:#f0fdf4;"><i class="bi bi-people-fill" style="color:#059669;"></i></div>
        <div>
            <div class="sbar-val" style="color:#059669;"><?php echo array_sum(array_column($kelasSaya,'jumlah_siswa')); ?></div>
            <div class="sbar-lbl">Total Siswa</div>
        </div>
    </div>
    <div class="sbar-item">
        <div class="sbar-icon" style="background:#fffbeb;"><i class="bi bi-journal-text" style="color:#d97706;"></i></div>
        <div>
            <div class="sbar-val" style="color:#d97706;"><?php echo array_sum(array_column($kelasSaya,'jumlah_tugas_aktif')); ?></div>
            <div class="sbar-lbl">Tugas Aktif</div>
        </div>
    </div>
    <div class="sbar-item">
        <div class="sbar-icon" style="background:#fff1f2;"><i class="bi bi-hourglass-split" style="color:#e11d48;"></i></div>
        <div>
            <div class="sbar-val" style="color:#e11d48;"><?php echo array_sum(array_column($kelasSaya,'belum_dinilai')); ?></div>
            <div class="sbar-lbl">Belum Dinilai</div>
        </div>
    </div>
</div>

<!-- ══ PER-KELAS CARDS ══ -->
<?php foreach ($kelasSaya as $k): ?>
<?php
    $gid        = (int)$k['grup_id'];
    $siswa_list = $detail[$gid]['siswa']    ?? [];
    $tugas_list = $detail[$gid]['tugas']    ?? [];
    $peni_list  = $detail[$gid]['penilaian']?? [];
    $jadwal_list= $detail[$gid]['jadwal']   ?? [];
    $guru_list  = $detail[$gid]['guru']     ?? [];
    $rata       = $k['rata_nilai'];
    $pct_rata   = $rata !== null ? (int)round((float)$rata) : 0;
    $ada_alert  = ((int)$k['tugas_deadline_dekat'] > 0 || (int)$k['belum_dinilai'] > 0);

    // Kelompokkan jadwal per hari
    $jadwal_per_hari = [];
    foreach ($jadwal_list as $j) {
        $jadwal_per_hari[$j['hari']][] = $j;
    }
    uksort($jadwal_per_hari, fn($a,$b) => ($hariOrder[$a]??9) <=> ($hariOrder[$b]??9));
?>
<div class="kelas-card">
    <div class="kc-stripe"></div>

    <!-- HEAD -->
    <div class="kc-head">
        <div style="flex:1;">
            <div class="kc-title"><?php echo htmlspecialchars($k['nama_grup']); ?></div>
            <div class="kc-badges">
                <span class="badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;">Kelas <?php echo (int)$k['tingkat']; ?></span>
                <?php if (!empty($k['jurusan'])): ?>
                <span class="badge" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;font-size:11px;"><?php echo htmlspecialchars($k['jurusan']); ?></span>
                <?php endif; ?>
                <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;">
                    <i class="bi bi-shield-check me-1"></i>Wali Kelas Anda
                </span>
                <?php if ($ada_alert): ?>
                <span class="kc-alert-badge"><i class="bi bi-exclamation-circle"></i>Perlu Perhatian</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($k['deskripsi'])): ?>
            <div style="font-size:12px;color:#64748b;margin-top:8px;"><?php echo htmlspecialchars(mb_substr($k['deskripsi'],0,120)); ?></div>
            <?php endif; ?>
        </div>
        <div class="kc-stats">
            <div class="kc-stat c-blue"><div class="n"><?php echo (int)$k['jumlah_siswa']; ?></div><div class="l">Siswa</div></div>
            <div class="kc-stat c-slate"><div class="n"><?php echo (int)$k['jumlah_guru']; ?></div><div class="l">Guru Pengajar</div></div>
            <div class="kc-stat c-purple"><div class="n"><?php echo (int)$k['jumlah_mapel']; ?></div><div class="l">Mata Pelajaran</div></div>
            <div class="kc-stat c-amber"><div class="n"><?php echo (int)$k['jumlah_tugas_aktif']; ?></div><div class="l">Tugas Aktif</div></div>
            <div class="kc-stat c-green"><div class="n"><?php echo (int)$k['jumlah_pengumpulan']; ?></div><div class="l">Dikumpulkan</div></div>
            <div class="kc-stat c-red"><div class="n"><?php echo (int)$k['belum_dinilai']; ?></div><div class="l">Belum Dinilai</div></div>
        </div>
    </div>

    <!-- RATA-RATA NILAI -->
    <?php if ($rata !== null): ?>
    <div class="kc-avg">
        <span class="kc-avg-label"><i class="bi bi-bar-chart-fill me-1"></i>Rata-rata Nilai Kelas</span>
        <div class="kc-avg-bar"><div class="kc-avg-fill" style="width:<?php echo $pct_rata; ?>%;background:<?php echo pcolor($pct_rata); ?>;"></div></div>
        <span class="kc-avg-val" style="color:<?php echo pcolor($pct_rata); ?>;"><?php echo number_format((float)$rata,1); ?> / 100</span>
    </div>
    <?php endif; ?>

    <!-- TAB NAV -->
    <div class="kc-tab-nav">
        <button class="kc-tab-btn active" onclick="ksTab(this,'ks-siswa-<?php echo $gid; ?>')">
            <i class="bi bi-people-fill"></i> Anggota Kelas
            <span class="tb-badge green"><?php echo (int)$k['jumlah_siswa']; ?></span>
        </button>
        <button class="kc-tab-btn" onclick="ksTab(this,'ks-tugas-<?php echo $gid; ?>')">
            <i class="bi bi-journal-text"></i> Tugas
            <?php if ((int)$k['tugas_deadline_dekat'] > 0): ?>
            <span class="tb-badge"><?php echo (int)$k['tugas_deadline_dekat']; ?> deadline</span>
            <?php else: ?>
            <span class="tb-badge green"><?php echo (int)$k['jumlah_tugas_aktif']; ?></span>
            <?php endif; ?>
        </button>
        <button class="kc-tab-btn" onclick="ksTab(this,'ks-peni-<?php echo $gid; ?>')">
            <i class="bi bi-star-fill"></i> Penilaian
            <?php if ((int)$k['belum_dinilai'] > 0): ?>
            <span class="tb-badge"><?php echo (int)$k['belum_dinilai']; ?> pending</span>
            <?php endif; ?>
        </button>
        <button class="kc-tab-btn" onclick="ksTab(this,'ks-jadwal-<?php echo $gid; ?>')">
            <i class="bi bi-calendar3"></i> Jadwal
        </button>
        <button class="kc-tab-btn" onclick="ksTab(this,'ks-guru-<?php echo $gid; ?>')">
            <i class="bi bi-person-badge"></i> Guru Pengajar
            <span class="tb-badge green"><?php echo (int)$k['jumlah_guru']; ?></span>
        </button>
    </div>

    <!-- ═══ TAB: ANGGOTA KELAS ═══ -->
    <div id="ks-siswa-<?php echo $gid; ?>" class="kc-tab-panel active">
        <?php if (empty($siswa_list)): ?>
        <div class="ks-empty"><i class="bi bi-person-x"></i><p>Belum ada siswa terdaftar.</p></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>NIS</th>
                    <th>Nama Lengkap</th>
                    <th>JK</th>
                    <th class="text-center">Tugas Dikumpulkan</th>
                    <th class="text-center">Rata-rata Nilai</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($siswa_list as $i => $s): ?>
                <?php
                    $rn = $s['rata_nilai_siswa'];
                    $rn_pct = $rn !== null ? (int)round((float)$rn) : 0;
                ?>
                <tr>
                    <td class="text-muted" style="font-size:12px;"><?php echo $i+1; ?></td>
                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($s['nis_nip']); ?></code></td>
                    <td><span style="font-weight:600;"><?php echo htmlspecialchars($s['nama_lengkap']); ?></span></td>
                    <td>
                        <span class="badge" style="background:<?php echo $s['jenis_kelamin']==='L'?'#eff6ff':'#fdf2f8'; ?>;color:<?php echo $s['jenis_kelamin']==='L'?'#2563eb':'#be185d'; ?>;">
                            <?php echo $s['jenis_kelamin'] === 'L' ? '♂ L' : '♀ P'; ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span style="font-weight:700;font-size:14px;color:#0f172a;"><?php echo (int)$s['jml_kumpul']; ?></span>
                        <span class="text-muted" style="font-size:11px;"> tugas</span>
                    </td>
                    <td>
                        <?php if ($rn !== null): ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="prog-bg" style="flex:1;height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;">
                                <div class="prog-fill" style="width:<?php echo $rn_pct; ?>%;background:<?php echo pcolor($rn_pct); ?>;height:100%;border-radius:3px;"></div>
                            </div>
                            <span style="font-size:12px;font-weight:700;color:<?php echo pcolor($rn_pct); ?>;min-width:36px;"><?php echo number_format((float)$rn,1); ?></span>
                        </div>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:12px;">Belum ada nilai</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB: TUGAS ═══ -->
    <div id="ks-tugas-<?php echo $gid; ?>" class="kc-tab-panel">
        <?php if (empty($tugas_list)): ?>
        <div class="ks-empty"><i class="bi bi-journal-x"></i><p>Belum ada tugas untuk kelas ini.</p></div>
        <?php else: ?>
        <?php foreach ($tugas_list as $t): ?>
        <?php
            $sisa_hari  = (int)ceil((strtotime($t['tenggat']) - time()) / 86400);
            $dot_color  = $sisa_hari <= 1 ? '#dc2626' : ($sisa_hari <= 3 ? '#d97706' : '#059669');
            $tot        = (int)$t['total_siswa'];
            $kumpul     = (int)$t['jml_kumpul'];
            $dinilai    = (int)$t['jml_dinilai'];
            $pct_k      = $tot > 0 ? (int)round($kumpul/$tot*100) : 0;
            $pct_d      = $kumpul > 0 ? (int)round($dinilai/$kumpul*100) : 0;
            $overdue    = strtotime($t['tenggat']) < time();
        ?>
        <div class="tugas-row">
            <div class="tugas-dot" style="background:<?php echo $t['status']==='active' ? $dot_color : '#94a3b8'; ?>;margin-top:6px;"></div>
            <div class="tugas-info">
                <div class="tj"><?php echo htmlspecialchars($t['judul']); ?></div>
                <div class="tm">
                    <span><i class="bi bi-book me-1"></i><?php echo htmlspecialchars($t['matpel'] ?? '—'); ?></span>
                    &nbsp;·&nbsp;
                    <span><i class="bi bi-person me-1"></i><?php echo htmlspecialchars($t['pembuat'] ?? '—'); ?></span>
                    &nbsp;·&nbsp;
                    <span class="<?php echo $overdue?'text-danger fw-bold':''; ?>">
                        <i class="bi bi-calendar me-1"></i><?php echo date('d/m/Y H:i', strtotime($t['tenggat'])); ?>
                        <?php if (!$overdue): ?>
                        <span style="color:<?php echo $dot_color; ?>;font-weight:600;">(<?php echo $sisa_hari; ?> hari lagi)</span>
                        <?php else: ?><span class="text-danger">(Lewat)</span>
                        <?php endif; ?>
                    </span>
                    &nbsp;·&nbsp;
                    <span class="badge <?php echo $t['status']==='active'?'bg-success':'bg-secondary'; ?>" style="font-size:10px;">
                        <?php echo $t['status']==='active'?'Aktif':'Non-aktif'; ?>
                    </span>
                </div>
                <div class="tugas-meta" style="margin-top:8px;">
                    <!-- Progress Pengumpulan -->
                    <div class="prog-wrap">
                        <span style="font-size:11px;color:#64748b;min-width:80px;">Kumpul</span>
                        <div class="prog-bg"><div class="prog-fill" style="width:<?php echo $pct_k; ?>%;background:<?php echo pcolor($pct_k); ?>;"></div></div>
                        <span style="font-size:11px;font-weight:700;color:<?php echo pcolor($pct_k); ?>;min-width:50px;"><?php echo $kumpul; ?>/<?php echo $tot; ?></span>
                    </div>
                    <!-- Progress Penilaian -->
                    <div class="prog-wrap">
                        <span style="font-size:11px;color:#64748b;min-width:80px;">Dinilai</span>
                        <div class="prog-bg"><div class="prog-fill" style="width:<?php echo $pct_d; ?>%;background:<?php echo pcolor($pct_d); ?>;"></div></div>
                        <span style="font-size:11px;font-weight:700;color:<?php echo pcolor($pct_d); ?>;min-width:50px;"><?php echo $dinilai; ?>/<?php echo $kumpul; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB: PENILAIAN ═══ -->
    <div id="ks-peni-<?php echo $gid; ?>" class="kc-tab-panel">
        <?php if (empty($peni_list)): ?>
        <div class="ks-empty"><i class="bi bi-star"></i><p>Belum ada penilaian di kelas ini.</p></div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>Siswa</th>
                    <th>Judul Tugas</th>
                    <th class="text-center">Nilai</th>
                    <th class="text-center">Status</th>
                    <th>Guru Penilai</th>
                    <th>Catatan</th>
                    <th>Dinilai Pada</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($peni_list as $p): ?>
            <?php
                $nv  = (float)$p['nilai'];
                $max = (int)$p['poin_maksimal'];
                $pct = $max > 0 ? (int)round($nv/$max*100) : 0;
            ?>
            <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($p['siswa']); ?></td>
                <td style="font-size:12px;max-width:200px;"><?php echo htmlspecialchars(mb_substr($p['judul'],0,50)); ?></td>
                <td class="text-center">
                    <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
                        <div class="prog-bg" style="width:60px;height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;">
                            <div class="prog-fill" style="width:<?php echo $pct; ?>%;background:<?php echo pcolor($pct); ?>;height:100%;border-radius:3px;"></div>
                        </div>
                        <span style="font-weight:800;color:<?php echo pcolor($pct); ?>;font-size:13px;"><?php echo number_format($nv,1); ?></span>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge status-<?php echo htmlspecialchars($p['status_lulus']); ?>">
                        <?php echo match($p['status_lulus']) { 'lulus'=>'✅ Lulus', 'revisi'=>'✏️ Revisi', 'tidak_lulus'=>'❌ Tidak Lulus', default=>$p['status_lulus'] }; ?>
                    </span>
                </td>
                <td style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars($p['guru_penilai'] ?? '—'); ?></td>
                <td style="font-size:12px;color:#475569;max-width:180px;">
                    <?php echo !empty($p['catatan_guru']) ? htmlspecialchars(mb_substr($p['catatan_guru'],0,60)) : '—'; ?>
                </td>
                <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($p['dinilai_pada'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB: JADWAL ═══ -->
    <div id="ks-jadwal-<?php echo $gid; ?>" class="kc-tab-panel">
        <?php if (empty($jadwal_per_hari)): ?>
        <div class="ks-empty"><i class="bi bi-calendar-x"></i><p>Belum ada jadwal untuk kelas ini.</p></div>
        <?php else: ?>
        <div class="row g-4">
        <?php foreach ($jadwal_per_hari as $hari => $slots): ?>
        <div class="col-md-6">
            <div class="jadwal-hari">
                <span class="jadwal-hari-title"><?php echo htmlspecialchars($hari); ?></span>
                <?php foreach ($slots as $s): ?>
                <div class="jadwal-slot">
                    <div class="jadwal-time">
                        <i class="bi bi-clock me-1 text-muted"></i>
                        <?php echo substr($s['jam_mulai'],0,5).' – '.substr($s['jam_selesai'],0,5); ?>
                    </div>
                    <div class="jadwal-mp">
                        <span class="badge bg-dark me-1" style="font-size:10px;"><?php echo htmlspecialchars($s['kode']); ?></span>
                        <?php echo htmlspecialchars($s['matpel']); ?>
                    </div>
                    <div class="jadwal-guru">
                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($s['guru']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ TAB: GURU PENGAJAR ═══ -->
    <div id="ks-guru-<?php echo $gid; ?>" class="kc-tab-panel">
        <?php if (empty($guru_list)): ?>
        <div class="ks-empty"><i class="bi bi-person-x"></i><p>Belum ada guru yang mengajar di kelas ini.</p></div>
        <?php else: ?>
        <div class="row g-3">
        <?php foreach ($guru_list as $g): ?>
        <div class="col-md-6 col-lg-4">
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;height:100%;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#E67E22,#f59e0b);
                                color:#fff;font-weight:800;font-size:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <?php echo strtoupper(substr($g['nama_lengkap'],0,1)); ?>
                    </div>
                    <div style="font-weight:700;color:#0f172a;font-size:14px;"><?php echo htmlspecialchars($g['nama_lengkap']); ?></div>
                </div>
                <div style="font-size:12px;color:#475569;margin-bottom:6px;">
                    <i class="bi bi-book me-1 text-primary"></i><?php echo htmlspecialchars($g['mapel_list']); ?>
                </div>
                <?php if (!empty($g['jadwal_list'])): ?>
                <div style="font-size:11px;color:#94a3b8;">
                    <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($g['jadwal_list']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .kelas-card -->
<?php endforeach; ?>

<?php endif; ?>

<script>
function ksTab(btn, targetId) {
    const card = btn.closest('.kelas-card');
    card.querySelectorAll('.kc-tab-btn').forEach(b => b.classList.remove('active'));
    card.querySelectorAll('.kc-tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById(targetId);
    if (panel) panel.classList.add('active');
}
</script>

<?php layoutEnd(); ?>