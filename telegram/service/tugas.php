<?php
    function createTugas($db, array $data, int $guruId): bool {

        // VALIDASI
        if (empty($data['judul'])) return false;
        if (empty($data['deadline'])) return false;

        // RULE: deadline tidak boleh lewat
        if (strtotime($data['deadline']) < time()) {
            return false;
        }

        // SIMPAN
        return sirey_execute(
            'INSERT INTO tugas (judul, deskripsi, tenggat, grup_id, pembuat_id)
            VALUES (?,?,?,?,?)',
            'sssii',
            $data['judul'],
            $data['deskripsi'],
            $data['deadline'],
            $data['grup_id'],
            $guruId
        );
    }
?>