<?php

namespace Paccomo\ImageManipulator;

require_once __DIR__ . '/Internal/ImageManipulator.php';
use Paccomo\ImageManipulator\Internal\ImageManipulator;
use Paccomo\ImageManipulator\IImageManipulator;

class ImageManipulatorFactory
{
    public static function create(string $filename): IImageManipulator
    {
        return new ImageManipulator($filename);
    }
}