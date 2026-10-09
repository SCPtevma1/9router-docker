<?php
declare(strict_types=1);

final class ImageUploadException extends RuntimeException
{
}

function save_uploaded_images(array $files, string $targetDirectory, string $relativeDirectory, string $filePrefix): array
{
    $names = $files['name'] ?? [];
    $temporaryPaths = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];

    if (!is_array($names) || !is_array($temporaryPaths) || !is_array($errors) || !is_array($sizes)) {
        throw new ImageUploadException('รูปแบบไฟล์อัปโหลดไม่ถูกต้อง');
    }

    if (count($names) > 20) {
        throw new ImageUploadException('อัปโหลดรูปได้ไม่เกิน 20 รูปต่อครั้ง');
    }

    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $savedFiles = [];

    try {
        foreach ($names as $index => $_name) {
            $uploadCode = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($uploadCode !== UPLOAD_ERR_OK) {
                throw new ImageUploadException('อัปโหลดรูปไม่สำเร็จ กรุณาตรวจสอบขนาดไฟล์แล้วลองใหม่');
            }

            $temporaryPath = (string) ($temporaryPaths[$index] ?? '');
            if (!is_uploaded_file($temporaryPath)) {
                throw new ImageUploadException('ไฟล์รูปภาพไม่ถูกต้อง');
            }
            if ((int) ($sizes[$index] ?? 0) > 5 * 1024 * 1024) {
                throw new ImageUploadException('รูปภาพแต่ละไฟล์ต้องมีขนาดไม่เกิน 5 MB');
            }

            $imageInfo = @getimagesize($temporaryPath);
            $extension = $imageInfo === false ? null : ($allowedTypes[$imageInfo[2]] ?? null);
            if ($extension === null) {
                throw new ImageUploadException('รองรับเฉพาะรูป JPG, PNG, GIF หรือ WebP');
            }
            if ($imageInfo[0] > 8000 || $imageInfo[1] > 8000) {
                throw new ImageUploadException('ขนาดรูปต้องไม่เกิน 8000 x 8000 พิกเซล');
            }

            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
                throw new ImageUploadException('ไม่สามารถเตรียมพื้นที่จัดเก็บรูปภาพได้');
            }

            $fileName = $filePrefix . bin2hex(random_bytes(16)) . '.' . $extension;
            $absolutePath = $targetDirectory . DIRECTORY_SEPARATOR . $fileName;
            if (!move_uploaded_file($temporaryPath, $absolutePath)) {
                throw new ImageUploadException('ไม่สามารถจัดเก็บรูปภาพได้');
            }
            $savedFiles[] = [
                'path' => trim($relativeDirectory, '/') . '/' . $fileName,
                'absolute_path' => $absolutePath,
            ];
        }
    } catch (Throwable $exception) {
        foreach ($savedFiles as $savedFile) {
            if (is_file($savedFile['absolute_path']) && !@unlink($savedFile['absolute_path'])) {
                error_log('Unable to remove incomplete image upload: ' . $savedFile['absolute_path']);
            }
        }
        throw $exception;
    }

    return $savedFiles;
}

function remove_managed_image_file(string $relativePath, string $relativeDirectory, string $filePrefix): void
{
    $fileName = basename($relativePath);
    $expectedDirectory = trim($relativeDirectory, '/');
    $actualDirectory = str_replace('\\', '/', dirname($relativePath));
    $pattern = '/\A' . preg_quote($filePrefix, '/') . '[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z/i';
    if ($actualDirectory !== $expectedDirectory || !preg_match($pattern, $fileName)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $expectedDirectory) . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($absolutePath) && !@unlink($absolutePath)) {
        error_log('Unable to remove managed image: ' . $absolutePath);
    }
}
