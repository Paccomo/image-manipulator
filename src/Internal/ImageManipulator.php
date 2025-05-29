<?php

namespace Paccomo\ImageManipulator\Internal;

use Paccomo\ImageManipulator\IImageManipulator;
use Exception;

/**
 * @internal This class is not part of the public API. Use the ImageManipulatorFactory instead.
 */
class ImageManipulator implements IImageManipulator
{
    private $image;
    private $type;

    public function __construct(string $filename)
    {
        $this->loadImage($filename);
    }

    private function loadImage(string $filename): void
    {
        if (!file_exists($filename)) {
            throw new Exception("File not found: $filename");
        }

        $info = getimagesize($filename);
        if (!$info) {
            throw new Exception("Could not read image info: $filename");
        }

        $mime = $info['mime'];
        $this->type = $mime;

        switch ($mime) {
            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($filename);
                break;
            case 'image/png':
                $this->image = imagecreatefrompng($filename);
                break;
            case 'image/gif':
                $this->image = imagecreatefromgif($filename);
                break;
            default:
                throw new Exception("Unsupported image type: $mime");
        }

        if (!$this->image) {
            throw new Exception("Failed to load image: $filename");
        }
    }

    public function getImage()
    {
        return $this->image;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setPixelColor(int $x, int $y, int $red, int $green, int $blue, int $alpha = 0): void
    {
        if (!$this->image) {
            throw new Exception("No image loaded.");
        }

        if ($x < 0 || $y < 0 || $x >= imagesx($this->image) || $y >= imagesy($this->image)) {
            throw new Exception("Coordinates out of bounds.");
        }

        if ($this->type === 'image/png') {
            $color = imagecolorallocatealpha($this->image, $red, $green, $blue, $alpha);
        } else {
            $color = imagecolorallocate($this->image, $red, $green, $blue);
        }

        imagesetpixel($this->image, $x, $y, $color);
    }

    public function getWidth(): int
    {
        if (!$this->image) {
            throw new Exception("No image loaded.");
        }
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        if (!$this->image) {
            throw new Exception("No image loaded.");
        }
        return imagesy($this->image);
    }

    public function replaceColor(
        int $targetR,
        int $targetG,
        int $targetB,
        int $newR,
        int $newG,
        int $newB,
        int $tolerance
    ): void {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = imagecolorat($this->image, $x, $y);
                $color = imagecolorsforindex($this->image, $index);

                $dr = $color['red'] - $targetR;
                $dg = $color['green'] - $targetG;
                $db = $color['blue'] - $targetB;

                if (abs($dr) <= $tolerance && abs($dg) <= $tolerance && abs($db) <= $tolerance) {
                    $adjustedR = max(0, min(255, $newR + $dr));
                    $adjustedG = max(0, min(255, $newG + $dg));
                    $adjustedB = max(0, min(255, $newB + $db));
                    $alpha = $color['alpha'] ?? 0;

                    if ($this->type === 'image/png') {
                        $newColor = imagecolorallocatealpha($this->image, $adjustedR, $adjustedG, $adjustedB, $alpha);
                    } else {
                        $newColor = imagecolorallocate($this->image, $adjustedR, $adjustedG, $adjustedB);
                    }

                    imagesetpixel($this->image, $x, $y, $newColor);
                }
            }
        }
    }

    public function invertColors(): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = imagecolorat($this->image, $x, $y);
                $color = imagecolorsforindex($this->image, $index);

                $invertedR = 255 - $color['red'];
                $invertedG = 255 - $color['green'];
                $invertedB = 255 - $color['blue'];
                $alpha = $color['alpha'] ?? 0;

                if ($this->type === 'image/png') {
                    $newColor = imagecolorallocatealpha($this->image, $invertedR, $invertedG, $invertedB, $alpha);
                } else {
                    $newColor = imagecolorallocate($this->image, $invertedR, $invertedG, $invertedB);
                }

                imagesetpixel($this->image, $x, $y, $newColor);
            }
        }
    }

    public function crop(int $x, int $y, int $width, int $height): void
    {
        $imgWidth = imagesx($this->image);
        $imgHeight = imagesy($this->image);

        $x = max(0, $x);
        $y = max(0, $y);

        $width = min($width, $imgWidth - $x);
        $height = min($height, $imgHeight - $y);

        if ($width <= 0 || $height <= 0) {
            return;
        }

        $cropped = imagecreatetruecolor($width, $height);

        if ($this->type === 'image/png') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            imagefill($cropped, 0, 0, $transparent);
        }

        imagecopy(
            $cropped,
            $this->image,
            0,
            0,
            $x,
            $y,
            $width,
            $height
        );

        imagedestroy($this->image);
        $this->image = $cropped;
    }

    public function downscale(float $factor): void
    {
        if ($factor <= 0 || $factor >= 1) {
            throw new Exception("Scale factor must be between 0 and 1.");
        }

        $origWidth = imagesx($this->image);
        $origHeight = imagesy($this->image);

        $newWidth = (int) round($origWidth * $factor);
        $newHeight = (int) round($origHeight * $factor);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($this->type === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled(
            $resized,
            $this->image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $origWidth,
            $origHeight
        );

        imagedestroy($this->image);
        $this->image = $resized;
    }

    public function grayscale(): void
    {
        if (!imagefilter($this->image, IMG_FILTER_GRAYSCALE)) {
            throw new Exception("Failed to apply grayscale filter.");
        }
    }

    public function adjustBrightness(int $level): void
    {
        if ($level < -255 || $level > 255) {
            throw new Exception("Brightness level must be between -255 and 255.");
        }

        if (!imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $level)) {
            throw new Exception("Failed to apply brightness filter.");
        }
    }

    public function adjustContrast(int $level): void
    {
        if ($level < -100 || $level > 100) {
            throw new Exception("Contrast level must be between -100 and 100.");
        }

        if (!imagefilter($this->image, IMG_FILTER_CONTRAST, $level)) {
            throw new Exception("Failed to apply contrast filter.");
        }
    }

    public function colorize(int $r, int $g, int $b): void
    {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));

        if (!imagefilter($this->image, IMG_FILTER_COLORIZE, $r, $g, $b)) {
            throw new Exception("Failed to apply colorize filter.");
        }
    }

    public function edgeDetect(): void
    {
        if (!imagefilter($this->image, IMG_FILTER_EDGEDETECT)) {
            throw new Exception("Failed to apply edge detection filter.");
        }
    }
    public function rotate(float $angle): void
    {
        $angle = -$angle;

        $bgColor = 0;

        if ($this->type === 'image/png') {
            $bgColor = imagecolorallocatealpha($this->image, 0, 0, 0, 127);
            $rotated = imagerotate($this->image, $angle, $bgColor);

            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        } else {
            $bgColor = imagecolorallocate($this->image, 0, 0, 0);
            $rotated = imagerotate($this->image, $angle, $bgColor);
        }

        if (!$rotated) {
            throw new Exception("Failed to rotate image.");
        }

        imagedestroy($this->image);
        $this->image = $rotated;
    }

    public function flip(string $mode = 'horizontal'): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        $flipped = imagecreatetruecolor($width, $height);

        if ($this->type === 'image/png') {
            imagealphablending($flipped, false);
            imagesavealpha($flipped, true);
            $transparent = imagecolorallocatealpha($flipped, 0, 0, 0, 127);
            imagefill($flipped, 0, 0, $transparent);
        }

        switch (strtolower($mode)) {
            case 'horizontal':
                for ($x = 0; $x < $width; $x++) {
                    imagecopy($flipped, $this->image, $width - $x - 1, 0, $x, 0, 1, $height);
                }
                break;

            case 'vertical':
                for ($y = 0; $y < $height; $y++) {
                    imagecopy($flipped, $this->image, 0, $height - $y - 1, 0, $y, $width, 1);
                }
                break;

            case 'both':
                for ($x = 0; $x < $width; $x++) {
                    for ($y = 0; $y < $height; $y++) {
                        $color = imagecolorat($this->image, $x, $y);
                        imagesetpixel($flipped, $width - $x - 1, $height - $y - 1, $color);
                    }
                }
                break;

            default:
                throw new Exception("Invalid flip mode. Use 'horizontal', 'vertical', or 'both'.");
        }

        imagedestroy($this->image);
        $this->image = $flipped;
    }

    public function pixelate(int $blockSize): void
    {
        if ($blockSize < 1) {
            throw new Exception("Block size must be 1 or greater.");
        }

        if (!imagefilter($this->image, IMG_FILTER_PIXELATE, $blockSize, true)) {
            throw new Exception("Failed to apply pixelate filter.");
        }
    }

    public function blur(int $passes = 1): void
    {
        $passes = max(1, $passes);

        for ($i = 0; $i < $passes; $i++) {
            if (!imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR)) {
                throw new Exception("Failed to apply blur filter.");
            }
        }
    }

    public function vignette(float $strength = 0.5): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);
        $cx = $width / 2;
        $cy = $height / 2;
        $maxDistance = sqrt($cx * $cx + $cy * $cy);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));
                $dx = $x - $cx;
                $dy = $y - $cy;
                $distance = sqrt($dx * $dx + $dy * $dy);
                $fade = 1 - ($strength * ($distance / $maxDistance));
                $fade = max(0, min(1, $fade));

                $newR = (int) ($color['red'] * $fade);
                $newG = (int) ($color['green'] * $fade);
                $newB = (int) ($color['blue'] * $fade);
                $alpha = $color['alpha'] ?? 0;

                $newColor = ($this->type === 'image/png')
                    ? imagecolorallocatealpha($this->image, $newR, $newG, $newB, $alpha)
                    : imagecolorallocate($this->image, $newR, $newG, $newB);

                imagesetpixel($this->image, $x, $y, $newColor);
            }
        }
    }

    public function duotone(
        int $darkR,
        int $darkG,
        int $darkB,
        int $lightR,
        int $lightG,
        int $lightB
    ): void {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));
                $gray = (0.3 * $color['red'] + 0.59 * $color['green'] + 0.11 * $color['blue']) / 255;

                $newR = (int) ($darkR + ($lightR - $darkR) * $gray);
                $newG = (int) ($darkG + ($lightG - $darkG) * $gray);
                $newB = (int) ($darkB + ($lightB - $darkB) * $gray);
                $alpha = $color['alpha'] ?? 0;

                $newColor = ($this->type === 'image/png')
                    ? imagecolorallocatealpha($this->image, $newR, $newG, $newB, $alpha)
                    : imagecolorallocate($this->image, $newR, $newG, $newB);

                imagesetpixel($this->image, $x, $y, $newColor);
            }
        }
    }

    public function selectiveDesaturate(int $r, int $g, int $b, int $tolerance = 50): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));
                $dr = abs($color['red'] - $r);
                $dg = abs($color['green'] - $g);
                $db = abs($color['blue'] - $b);

                if ($dr > $tolerance || $dg > $tolerance || $db > $tolerance) {
                    $gray = (int) (0.3 * $color['red'] + 0.59 * $color['green'] + 0.11 * $color['blue']);
                    $alpha = $color['alpha'] ?? 0;

                    $grayColor = ($this->type === 'image/png')
                        ? imagecolorallocatealpha($this->image, $gray, $gray, $gray, $alpha)
                        : imagecolorallocate($this->image, $gray, $gray, $gray);

                    imagesetpixel($this->image, $x, $y, $grayColor);
                }
            }
        }
    }

    public function wave(float $amplitude = 5.0, float $frequency = 0.05): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);
        $output = imagecreatetruecolor($width, $height);

        if ($this->type === 'image/png') {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $transparent);
        }

        for ($y = 0; $y < $height; $y++) {
            $offset = (int) (sin($y * $frequency) * $amplitude);

            for ($x = 0; $x < $width; $x++) {
                $srcX = $x - $offset;

                if ($srcX >= 0 && $srcX < $width) {
                    $color = imagecolorat($this->image, $srcX, $y);
                    imagesetpixel($output, $x, $y, $color);
                }
            }
        }

        imagedestroy($this->image);
        $this->image = $output;
    }

    public function posterize(int $levels = 4): void
    {
        $levels = max(2, min(256, $levels));
        $step = (int) (256 / $levels);

        $width = imagesx($this->image);
        $height = imagesy($this->image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorsforindex($this->image, imagecolorat($this->image, $x, $y));

                $newR = max(0, min(255, (int) (round($color['red'] / $step) * $step)));
                $newG = max(0, min(255, (int) (round($color['green'] / $step) * $step)));
                $newB = max(0, min(255, (int) (round($color['blue'] / $step) * $step)));

                $alpha = $color['alpha'] ?? 0;

                $newColor = $this->type === 'image/png'
                    ? imagecolorallocatealpha($this->image, $newR, $newG, $newB, $alpha)
                    : imagecolorallocate($this->image, $newR, $newG, $newB);

                imagesetpixel($this->image, $x, $y, $newColor);
            }
        }
    }

    public function halftone(int $dotSize = 6): void
    {
        $width = imagesx($this->image);
        $height = imagesy($this->image);

        $output = imagecreatetruecolor($width, $height);

        if ($this->type === 'image/png') {
            imagealphablending($output, false);
            imagesavealpha($output, true);
            $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
            imagefill($output, 0, 0, $transparent);
        } else {
            imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        }

        for ($y = 0; $y < $height; $y += $dotSize) {
            for ($x = 0; $x < $width; $x += $dotSize) {
                $gray = 0;
                $samples = 0;

                for ($dy = 0; $dy < $dotSize && $y + $dy < $height; $dy++) {
                    for ($dx = 0; $dx < $dotSize && $x + $dx < $width; $dx++) {
                        $color = imagecolorsforindex($this->image, imagecolorat($this->image, $x + $dx, $y + $dy));
                        $gray += 0.3 * $color['red'] + 0.59 * $color['green'] + 0.11 * $color['blue'];
                        $samples++;
                    }
                }

                $gray /= $samples;
                $radius = $dotSize * (1 - $gray / 255) / 2;

                $fill = imagecolorallocate($output, 0, 0, 0);
                imagefilledellipse($output, $x + $dotSize / 2, $y + $dotSize / 2, $radius * 2, $radius * 2, $fill);
            }
        }

        imagedestroy($this->image);
        $this->image = $output;
    }

public function save(string $filename): void
{
    switch ($this->type) {
        case 'image/jpeg':
            imagejpeg($this->image, $filename, 100);
            break;

        case 'image/png':
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
            imagepng($this->image, $filename, 0);
            break;

        case 'image/gif':
            imagegif($this->image, $filename);
            break;

        default:
            throw new Exception("Unsupported image format for saving.");
    }
}


    public function toBase64(): string
    {
        ob_start();

        switch ($this->type) {
            case 'image/jpeg':
                imagejpeg($this->image, null, 100);
                $mime = 'image/jpeg';
                break;

            case 'image/png':
                imagepng($this->image, null, 0);
                $mime = 'image/png';
                break;

            case 'image/gif':
                imagegif($this->image);
                $mime = 'image/gif';
                break;

            default:
                ob_end_clean();
                throw new Exception("Unsupported image format for base64 encoding.");
        }

        $imageData = ob_get_clean();
        return 'data:' . $mime . ';base64,' . base64_encode($imageData);
    }

    public function destroy(): void
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }
}
