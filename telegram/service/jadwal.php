<?php
    function getUserJadwal(mysqli $db, int $userId): array {

        $data = fetchUserSchedule($db, $userId);

        // filter / logic tambahan
        if (!$data) {
            return [];
        }

        // sorting custom berdasarkan hari
        usort($data, function($a, $b) {
            return strcmp($a['hari'], $b['hari']);
        });

        return $data;
    }
?>