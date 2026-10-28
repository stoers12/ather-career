# P2J-08 image calibration

Disposable production-image calibration used PHP 8.3.33, bundled GD 2.1-compatible, and the production `memory_limit` of 128 MiB. `scripts/calibrate-phase2-images.php` generated and decoded high-compression JPEG and PNG inputs through the current profile resize and conservative full project decode paths.

| Input | Pixels | Worst measured PHP peak | Worst measured process high-water RSS |
|---|---:|---:|---:|
| 4000×2000 PNG | 8,000,000 | 59,654,144 bytes | 78,820 KiB |
| 3000×3000 PNG | 9,000,000 | 64,749,568 bytes | 85,860 KiB |
| 4032×3024 PNG | 12,192,768 | 89,010,176 bytes | 107,616 KiB |
| 4000×3500 PNG | 14,000,000 | 100,720,640 bytes | 120,420 KiB |
| 4000×4000 PNG | 16,000,000 | 115,109,888 bytes | 134,368 KiB |

JPEG measurements were lower; PNG is the limiting measured format. A 20-megapixel GD decode exhausted the 128 MiB PHP limit, and 16 megapixels left inadequate worker headroom. The old GD policy therefore enforced 14 MP, but this was an implementation ceiling rather than a product safety policy.

The libvips thumbnail spike used `VIPS_CONCURRENCY=1` inside a 256 MiB disposable container. It normalized the real 25.96 MP JPEG source to 960 px at 47 MiB RSS and 1600 px at 61 MiB RSS. A 64 MP alpha PNG reached 190 MiB RSS; progressive JPEG and WebP loaders had materially different behavior. The source preflight policy is therefore format-specific:

- baseline JPEG: 64 MP
- progressive JPEG: 26 MP
- PNG: 64 MP
- WebP: 32 MP
- every supported format: maximum 12,000 pixels on either edge

These are decoded-image safety limits, not presentation dimensions. Valid sources are normalized through a bounded libvips thumbnail process to a maximum 960-pixel Profile derivative or 1600-pixel Project derivative without upscaling or cropping; private originals remain preserved. Inputs above the relevant ceiling are rejected before libvips execution.
