# P2J-08 image calibration

Disposable production-image calibration used PHP 8.3.33, bundled GD 2.1-compatible, and the production `memory_limit` of 128 MiB. `scripts/calibrate-phase2-images.php` generated and decoded high-compression JPEG and PNG inputs through the current profile resize and conservative full project decode paths.

| Input | Pixels | Worst measured PHP peak | Worst measured process high-water RSS |
|---|---:|---:|---:|
| 4000×2000 PNG | 8,000,000 | 59,654,144 bytes | 78,820 KiB |
| 3000×3000 PNG | 9,000,000 | 64,749,568 bytes | 85,860 KiB |
| 4032×3024 PNG | 12,192,768 | 89,010,176 bytes | 107,616 KiB |
| 4000×3500 PNG | 14,000,000 | 100,720,640 bytes | 120,420 KiB |
| 4000×4000 PNG | 16,000,000 | 115,109,888 bytes | 134,368 KiB |

JPEG measurements were lower; PNG is the limiting measured format. A 20-megapixel decode exhausted the 128 MiB PHP limit, and 16 megapixels left inadequate worker headroom. Fourteen megapixels supports common 4032×3024 phone photography while retaining approximately 32 MiB of PHP headroom in the worst measured PNG path. The ingestion policy therefore enforces:

- `PROFILE_INGESTION_PIXEL_CEILING = 14000000`
- `PROJECT_INGESTION_PIXEL_CEILING = 14000000`

These are decoded-image safety limits, not presentation dimensions. Valid sources within the ceilings are normalized to a maximum 960-pixel Profile derivative or 1600-pixel Project derivative without upscaling or cropping; private originals remain preserved. Inputs above the decoded-pixel ceiling are rejected before GD decoding.
