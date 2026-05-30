<?php

namespace App\Jobs;

use App\Services\MediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class GenerateNewsMediaPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const DEFAULT_MAX_PREVIEW_WIDTH = 1600;

    protected const DEFAULT_WEBP_QUALITY = 80;

    protected const DEFAULT_AVIF_QUALITY = 55;

    protected const PREVIEW_WATERMARK_RATIO = 0.10;

    protected const PREVIEW_WATERMARK_OPACITY = 15;

    protected const THUMB_MAX_WIDTH = 480;

    protected const THUMB_QUALITY = 60;

    protected const THUMB_WATERMARK_OPACITY = 18;

    public function __construct(
        protected string $disk,
        protected string $originalPath,
        protected string $previewPath,
        protected string $watermarkPath,
        protected int $maxPreviewWidth = self::DEFAULT_MAX_PREVIEW_WIDTH,
        protected int $webpQuality = self::DEFAULT_WEBP_QUALITY,
        protected int $avifQuality = self::DEFAULT_AVIF_QUALITY,
        protected bool $generateAvif = true,
        protected bool $generateThumb = true,
    ) {}

    public function handle(): void
    {
        $mediaStorage = app(MediaStorage::class);
        $disk = Storage::disk($this->disk);
        $resolved = $mediaStorage->resolveReadableLocalPath($this->originalPath);
        $fullPath = $resolved['path'] ?? null;

        if (! is_string($fullPath) || ! is_readable($fullPath) || ! is_file($fullPath)) {
            Log::warning('GenerateNewsMediaPreview: original not readable', ['path' => $this->originalPath]);

            return;
        }
        if (! is_readable($this->watermarkPath) || ! is_file($this->watermarkPath)) {
            Log::warning('GenerateNewsMediaPreview: watermark not readable', ['path' => $this->watermarkPath]);

            return;
        }

        $previewDir = dirname($this->previewPath);
        if ($previewDir !== '') {
            $disk->makeDirectory($previewDir);
        }

        try {
            $manager = ImageManager::gd();
            $image = $manager->read($fullPath);

            // Preview: max 1600px Breite, WebP Quality 80, Wasserzeichen ~10 % Breite, Opacity 15
            $previewImage = $this->prepareForPreview($image);
            $this->applyWatermark($previewImage, $manager, self::PREVIEW_WATERMARK_RATIO, self::PREVIEW_WATERMARK_OPACITY);
            $previewBytes = (string) $previewImage->toWebp($this->webpQuality);
            $disk->put($this->previewPath, $previewBytes, [
                'visibility' => 'public',
                'ContentType' => 'image/webp',
            ]);

            // Optional: AVIF-Variante der Preview (gleiche Abmessungen/Wasserzeichen)
            if ($this->generateAvif && method_exists($previewImage, 'toAvif')) {
                $avifPath = $this->deriveAvifPath($this->previewPath);
                if ($avifPath !== '') {
                    $avifBytes = (string) $previewImage->toAvif($this->avifQuality);
                    $disk->put($avifPath, $avifBytes, [
                        'visibility' => 'public',
                        'ContentType' => 'image/avif',
                    ]);
                }
            }

            // Optional: Thumbnail (thumb/{name}.webp, max 480px, quality 60, watermark opacity 18)
            if ($this->generateThumb) {
                $thumbPath = (string) preg_replace('/-preview-/', '-thumb-', $this->previewPath, 1);
                if ($thumbPath === '' || $thumbPath === $this->previewPath) {
                    $thumbPath = dirname($this->originalPath).'/thumb/'.pathinfo($this->originalPath, PATHINFO_FILENAME).'.webp';
                }
                $thumbDir = dirname($thumbPath);
                if ($thumbDir !== '') {
                    $disk->makeDirectory($thumbDir);
                }
                $thumbImage = $image->scaleDown(width: self::THUMB_MAX_WIDTH);
                $this->applyWatermark($thumbImage, $manager, self::PREVIEW_WATERMARK_RATIO, self::THUMB_WATERMARK_OPACITY);
                $thumbBytes = (string) $thumbImage->toWebp(self::THUMB_QUALITY);
                $disk->put($thumbPath, $thumbBytes, [
                    'visibility' => 'public',
                    'ContentType' => 'image/webp',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('GenerateNewsMediaPreview failed', [
                'originalPath' => $this->originalPath,
                'previewPath' => $this->previewPath,
                'message' => $e->getMessage(),
            ]);
            report($e);
        } finally {
            $mediaStorage->cleanupResolvedPath($resolved);
        }
    }

    protected function prepareForPreview(ImageInterface $image): ImageInterface
    {
        return $image->scaleDown(width: $this->maxPreviewWidth);
    }

    protected function applyWatermark(ImageInterface $image, ImageManager $manager, float $widthRatio, int $opacity): void
    {
        $watermark = $manager->read($this->watermarkPath);
        $watermarkWidth = (int) max(1, $image->width() * $widthRatio);
        $watermark->scale(width: $watermarkWidth);
        $image->place($watermark, 'center', 0, 0, $opacity);
    }

    protected function deriveAvifPath(string $previewPath): string
    {
        $trimmedPath = trim($previewPath);
        if ($trimmedPath === '') {
            return '';
        }

        return preg_replace('/\.[a-zA-Z0-9]+$/', '.avif', $trimmedPath) ?? '';
    }
}
