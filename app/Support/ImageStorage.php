<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan gambar upload admin.
 *
 * Gambar tidak pernah disimpan apa adanya: file di-decode, diputar sesuai orientasi EXIF,
 * dikecilkan bila perlu, lalu di-encode ulang (WebP, atau JPEG jika WebP tidak didukung).
 * Metadata dan data tersisip di dalam file asli otomatis hilang. Nama file acak dan tidak
 * pernah berasal dari pengguna.
 */
class ImageStorage
{
    public const DISK = 'public';

    /**
     * @throws ImageProcessingException jika gambar tidak dapat diproses atau disimpan
     */
    public static function store(UploadedFile $file, string $directory, int $maxSide = 1200, int $quality = 82): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            throw new ImageProcessingException('Server belum mendukung pemrosesan gambar (ekstensi GD belum aktif).');
        }

        $source = $file->getRealPath();
        $data = $source ? @file_get_contents($source) : false;
        $image = $data === false ? false : @imagecreatefromstring($data);

        if ($image === false) {
            throw new ImageProcessingException('Berkas bukan gambar yang valid atau rusak.');
        }

        $image = self::applyOrientation($image, $source);
        $image = self::scaleDown($image, $maxSide);

        [$binary, $extension] = self::encode($image, $quality);

        if ($binary === '' || $binary === false) {
            throw new ImageProcessingException('Gambar gagal diproses.');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        if (! Storage::disk(self::DISK)->put($path, $binary)) {
            throw new ImageProcessingException('Gambar gagal disimpan.');
        }

        return $path;
    }

    /**
     * Hapus file gambar milik aplikasi. Path dari database tidak dipercaya begitu saja:
     * hanya path berpola "{direktori}/{40 karakter acak}.{webp|jpg}" yang boleh dihapus.
     */
    public static function delete(?string $path, string $directory): bool
    {
        if (! $path || ! self::isManagedPath($path, $directory)) {
            return false;
        }

        return Storage::disk(self::DISK)->delete($path);
    }

    public static function isManagedPath(string $path, string $directory): bool
    {
        return (bool) preg_match('#^'.preg_quote(trim($directory, '/'), '#').'/[A-Za-z0-9]{40}\.(webp|jpg)$#', $path);
    }

    /**
     * URL publik mengikuti host/port/subfolder request saat ini (asset()), bukan APP_URL,
     * sehingga gambar tetap muncul di XAMPP subfolder, artisan serve, maupun produksi.
     * Membutuhkan symlink public/storage (php artisan storage:link).
     */
    public static function url(?string $path): ?string
    {
        return $path ? asset('storage/'.ltrim($path, '/')) : null;
    }

    private static function applyOrientation(\GdImage $image, ?string $file): \GdImage
    {
        if (! $file || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $flip = fn (\GdImage $img, int $mode): \GdImage => tap($img, fn ($i) => imageflip($i, $mode));
        $rotate = fn (\GdImage $img, int $angle): \GdImage => imagerotate($img, $angle, 0) ?: $img;

        return match ($orientation) {
            2 => $flip($image, IMG_FLIP_HORIZONTAL),
            3 => $rotate($image, 180),
            4 => $flip($image, IMG_FLIP_VERTICAL),
            5 => $rotate($flip($image, IMG_FLIP_HORIZONTAL), 90),
            6 => $rotate($image, -90),
            7 => $rotate($flip($image, IMG_FLIP_HORIZONTAL), -90),
            8 => $rotate($image, 90),
            default => $image,
        };
    }

    private static function scaleDown(\GdImage $image, int $maxSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxSide) {
            return $image;
        }

        $ratio = $maxSide / $longest;
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC);

        return $scaled ?: $image;
    }

    /** @return array{0:string|false, 1:string} */
    private static function encode(\GdImage $image, int $quality): array
    {
        imagepalettetotruecolor($image);

        if (function_exists('imagewebp')) {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            imagewebp($image, null, $quality);

            return [ob_get_clean(), 'webp'];
        }

        // JPEG tidak mendukung transparansi: ratakan di atas latar putih.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, $quality);

        return [ob_get_clean(), 'jpg'];
    }
}
