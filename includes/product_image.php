<?php

function storeProductImage(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto gagal diunggah. Silakan coba lagi.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 5 MB.');
    }

    $image = @getimagesize($file['tmp_name']);
    $mime = $image['mime'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WebP.');
    }

    $directory = dirname(__DIR__) . '/uploads/products';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];

    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Foto gagal disimpan.');
    }

    return 'uploads/products/' . $filename;
}

function removeProductImage(?string $path): void
{
    if (
        $path === null ||
        !preg_match('/\Auploads\/products\/[a-f0-9]{32}\.(jpg|png|webp)\z/', $path)
    ) {
        return;
    }

    $absolutePath = dirname(__DIR__) . '/' . $path;

    if (is_file($absolutePath) && !unlink($absolutePath)) {
        error_log('Unable to remove product image: ' . $absolutePath);
    }
}
