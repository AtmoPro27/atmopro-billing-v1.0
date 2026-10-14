<?php
/**
 * Upload Validator — helper validasi file upload
 * Dibuat: 2026-10-11
 *
 * Fungsi:
 * - validate_image_upload()   : validasi gambar (logo, favicon, foto, bukti)
 * - validate_document_upload(): validasi file umum (jpg, png, pdf, zip)
 * - safe_save_upload()        : simpan file dengan nama acak
 */

/**
 * Validasi file gambar
 * Return: ['ok' => true, 'ext' => 'jpg'] atau ['ok' => false, 'error' => '...']
 */
function validate_image_upload($file_key, $max_size_mb = 5) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File tidak ditemukan atau gagal upload.'];
    }

    $file = $_FILES[$file_key];

    // 1. Cek ukuran
    if ($file['size'] > $max_size_mb * 1024 * 1024) {
        return ['ok' => false, 'error' => "File terlalu besar (maks {$max_size_mb}MB)."];
    }

    // 2. Cek ekstensi dari nama file
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['ok' => false, 'error' => "Ekstensi tidak diizinkan. Gunakan: " . implode(', ', $allowed)];
    }

    // 3. Cek MIME asli dari isi file
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mimes)) {
        return ['ok' => false, 'error' => 'Tipe file tidak valid (bukan gambar).'];
    }

    // 4. Cek isi benar gambar
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'File bukan gambar yang valid.'];
    }
    if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
        return ['ok' => false, 'error' => 'Format gambar tidak didukung.'];
    }

    // 5. Cek konten tidak mengandung kode PHP/script
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php|<\?=|<script|<%|eval\(|base64_decode/i', $content)) {
        return ['ok' => false, 'error' => 'File mengandung kode yang tidak diizinkan.'];
    }

    return ['ok' => true, 'ext' => $ext];
}

/**
 * Validasi file dokumen (attachment tiket)
 */
function validate_document_upload($file_key, $max_size_mb = 10) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File tidak ditemukan.'];
    }

    $file = $_FILES[$file_key];

    if ($file['size'] > $max_size_mb * 1024 * 1024) {
        return ['ok' => false, 'error' => "File terlalu besar (maks {$max_size_mb}MB)."];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'zip'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['ok' => false, 'error' => "Ekstensi tidak diizinkan."];
    }

    // Cek MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = [
        'image/jpeg', 'image/png',
        'application/pdf',
        'application/zip', 'application/x-zip-compressed'
    ];
    if (!in_array($mime, $allowed_mimes)) {
        return ['ok' => false, 'error' => 'Tipe file tidak valid.'];
    }

    // Cek konten PHP
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php|<\?=/', $content)) {
        return ['ok' => false, 'error' => 'File mengandung kode PHP.'];
    }

    return ['ok' => true, 'ext' => $ext];
}

/**
 * Simpan file dengan nama aman (acak)
 * Return: nama file baru, atau false
 */
function safe_save_upload($file_key, $destination_dir, $prefix = '') {
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(8));
    $filename = ($prefix ? $prefix . '_' : '') . date('Ymd_His') . '_' . $random . '.' . $ext;
    $destination = rtrim($destination_dir, '/') . '/' . $filename;

    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $destination)) {
        return $filename;
    }
    return false;
}
