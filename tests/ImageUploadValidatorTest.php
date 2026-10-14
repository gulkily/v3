<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Images\ImageUploadValidator;

final class ImageUploadValidatorTest
{
    public function testDecodedContentsAndLimits(): void
    {
        assertSame(true, ImageUploadValidator::available(), 'Install PHP GD to run image-upload tests.');
        $path = tempnam(sys_get_temp_dir(), 'image-validator-');
        $image = imagecreatetruecolor(4, 3);
        try {
            foreach (['imagepng', 'imagejpeg'] as $encode) {
                $encode($image, $path);
                ImageUploadValidator::validate($path);
                $valid = file_get_contents($path);
                file_put_contents($path, substr($valid, 0, -5));
                $this->reject($path);
            }
            imagepng($image, $path);
            $png = file_get_contents($path);
            $chunk = 'acTL' . pack('NN', 2, 0);
            file_put_contents($path, substr($png, 0, 33) . pack('N', 8) . $chunk . hash('crc32b', $chunk, true) . substr($png, 33));
            $this->reject($path);
            file_put_contents($path, substr_replace($png, 'x', 45, 1));
            $this->reject($path);
            $large = imagecreatetruecolor(2049, 1);
            imagepng($large, $path); imagedestroy($large);
            $this->reject($path);
            file_put_contents($path, str_repeat('x', 5 * 1024 * 1024 + 1));
            $this->reject($path);
        } finally { imagedestroy($image); unlink($path); }
    }

    public function testMissingDecoderFailsClosed(): void
    {
        $code = 'require ' . var_export(__DIR__ . '/../autoload.php', true) . '; try { \\ForumRewrite\\Images\\ImageUploadValidator::validate("missing"); exit(1); } catch (RuntimeException $e) { echo $e->getMessage(); }';
        exec(escapeshellarg(PHP_BINARY) . ' -n -r ' . escapeshellarg($code), $lines, $status);
        assertSame(0, $status);
        assertStringContains('PHP GD', implode("\n", $lines));
    }

    private function reject(string $path): void
    {
        try { ImageUploadValidator::validate($path); } catch (RuntimeException) { return; }
        throw new RuntimeException('Invalid image accepted.');
    }
}
