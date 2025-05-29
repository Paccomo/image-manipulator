<?php

namespace Paccomo\ImageManipulator;

interface IImageManipulator
{
    public function getImage();
    public function getType(): string;
    public function setPixelColor(int $x, int $y, int $red, int $green, int $blue, int $alpha = 0): void;
    public function getWidth(): int;
    public function getHeight(): int;
    public function replaceColor(int $targetR, int $targetG, int $targetB, int $newR, int $newG, int $newB, int $tolerance): void;
    public function invertColors(): void;
    public function crop(int $x, int $y, int $width, int $height): void;
    public function downscale(float $factor): void;
    public function grayscale(): void;
    public function adjustBrightness(int $level): void;
    public function adjustContrast(int $level): void;
    public function colorize(int $r, int $g, int $b): void;
    public function edgeDetect(): void;
    public function rotate(float $angle): void;
    public function flip(string $mode = 'horizontal'): void;
    public function pixelate(int $blockSize): void;
    public function blur(int $passes = 1): void;
    public function vignette(float $strength = 0.5): void;
    public function duotone( int $darkR, int $darkG, int $darkB, int $lightR, int $lightG, int $lightB): void;
    public function selectiveDesaturate(int $r, int $g, int $b, int $tolerance = 50): void;
    public function wave(float $amplitude = 5.0, float $frequency = 0.05): void;
    public function posterize(int $levels = 4): void;
    public function halftone(int $dotSize = 6): void;
    public function save(string $filename): void;
    public function toBase64(): string;
    public function destroy(): void;
}