<?php

namespace App\Domain\Ekim;

final class EkimNotificationMessage
{
    public function build(string $plate, string $validUntil, int $daysRemaining): array
    {
        $condition = $daysRemaining < 0
            ? 'telah kedaluwarsa'
            : "akan kedaluwarsa dalam $daysRemaining hari";

        return [
            'subject' => "Notifikasi Masa Berlaku EKIM $plate",
            'body' => "EKIM kendaraan $plate $condition pada $validUntil. Segera hubungi pengelola PRIMA untuk tindak lanjut pemeriksaan dan pembaruan EKIM.",
        ];
    }
}
