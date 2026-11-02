<?php

namespace App\Domain\Ekim;

final class EkimSignatureService
{
    private const VOLATILE_FIELDS = [
        'updated_at',
        'dokumen_hash',
        'status_approval',
        'ttd_hsse_user_id',
        'ttd_hsse_nama',
        'ttd_hsse_signature',
        'ttd_hsse_hash',
        'ttd_hsse_gambar',
        'ttd_hsse_timestamp',
        'ttd_manajer_user_id',
        'ttd_manajer_nama',
        'ttd_manajer_signature',
        'ttd_manajer_hash',
        'ttd_manajer_gambar',
        'ttd_manajer_timestamp',
        'qr_token',
        'verification_uuid',
        'verification_hash_sha512',
        'verification_signature',
        'verification_url',
        'verification_qrcode_path',
        'verification_created_at',
    ];

    public function canonicalHash(array $form, array $items, string $algorithm = 'sha256'): string
    {
        foreach (self::VOLATILE_FIELDS as $field) {
            unset($form[$field]);
        }
        $form['checklist_items'] = $items;

        return hash($algorithm, json_encode(
            $form,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    public function sign(string $hash, string $secret): string
    {
        $signature = base64_encode(hash_hmac('sha256', $hash, $secret, true));
        $urlSafeSignature = str_replace(['+', '/', '='], ['-', '_', ''], $signature);

        return 'hmac-sha256:'.$urlSafeSignature;
    }

    public function verify(string $hash, string $signature, string $secret): bool
    {
        return hash_equals($this->sign($hash, $secret), $signature);
    }
}
