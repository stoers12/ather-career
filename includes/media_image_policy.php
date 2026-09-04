<?php

declare(strict_types=1);

const PROFILE_INGESTION_PIXEL_CEILING = 14000000;
const PROJECT_INGESTION_PIXEL_CEILING = 14000000;

function imageDimensionsAreWithinPixelCeiling(mixed $dimensions, int $pixelCeiling): bool
{
    if (!is_array($dimensions)
        || !isset($dimensions[0], $dimensions[1])
        || !is_int($dimensions[0])
        || !is_int($dimensions[1])
        || $dimensions[0] < 1
        || $dimensions[1] < 1
        || $pixelCeiling < 1) {
        return false;
    }

    return $dimensions[0] <= intdiv($pixelCeiling, $dimensions[1]);
}

function profileImageDimensionsAreSafe(mixed $dimensions): bool
{
    return imageDimensionsAreWithinPixelCeiling($dimensions, PROFILE_INGESTION_PIXEL_CEILING);
}

function projectImageDimensionsAreSafe(mixed $dimensions): bool
{
    return imageDimensionsAreWithinPixelCeiling($dimensions, PROJECT_INGESTION_PIXEL_CEILING);
}
