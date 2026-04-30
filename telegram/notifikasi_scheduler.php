<?php


// Fungsi revisi —  untuk dipanggil dari penilaian.php

function notifySiswaRevisiApproved(int $pengumpulanId, int $nomorVersi, int $guruId, string $catatan = ''): bool
{
    $data = sirey_fetch(sirey_query(
        'SELECT t.judul, at.telegram_chat_id, a.nama_lengkap, guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         JOIN akun_telegram_rayhanrp at ON at.akun_id = a.akun_id
         LEFT JOIN akun_rayhanRP guru ON guru.akun_id = ?
         WHERE p.pengumpulan_id = ? LIMIT 1',
        'ii', $guruId, $pengumpulanId
    ));

    if (!$data || !$data['telegram_chat_id']) return false;

    $pesan = "✅ *Revisi Disetujui!*\n\n"
           . "Tugas: *{$data['judul']}* (v{$nomorVersi})\n"
           . "Oleh: {$data['guru_nama']}"
           . ($catatan ? "\n\n💬 _{$catatan}_" : '');

    return sendMsg((int) $data['telegram_chat_id'], $pesan);
}

function notifySiswaRevisiRejected(int $pengumpulanId, int $nomorVersi, int $guruId, string $catatan): bool
{
    $data = sirey_fetch(sirey_query(
        'SELECT t.judul, at.telegram_chat_id, guru.nama_lengkap AS guru_nama
         FROM pengumpulan_rayhanRP p
         JOIN tugas_rayhanRP t ON p.tugas_id = t.tugas_id
         JOIN akun_rayhanRP a ON p.akun_id = a.akun_id
         JOIN akun_telegram_rayhanrp at ON at.akun_id = a.akun_id
         LEFT JOIN akun_rayhanRP guru ON guru.akun_id = ?
         WHERE p.pengumpulan_id = ? LIMIT 1',
        'ii', $guruId, $pengumpulanId
    ));

    if (!$data || !$data['telegram_chat_id']) return false;

    $pesan = "❌ *Revisi Ditolak*\n\n"
           . "Tugas: *{$data['judul']}* (v{$nomorVersi})\n"
           . "Oleh: {$data['guru_nama']}\n\n"
           . "💬 Alasan:\n_{$catatan}_\n\n"
           . "Silakan perbaiki dan kirim ulang.";

    return sendMsg((int) $data['telegram_chat_id'], $pesan);
}