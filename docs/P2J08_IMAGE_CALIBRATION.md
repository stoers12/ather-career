# P2J-08 image calibration

Disposable production-image calibration used PHP 8.3.33, bundled GD 2.1-compatible, and the production `memory_limit` of 128 MiB. `scripts/calibrate-phase2-images.php` generated and decoded high-compression JPEG and PNG inputs through the current profile resize and conservative full project decode paths.

| Input | Pixels | Worst measured PHP peak | Worst measured process high-water RSS |
|---|---:|---:|---:|
| 4000×2000 PNG | 8,000,000 | 59,654,144 bytes | 78,820 KiB |
| 3000×3000 PNG | 9,000,000 | 64,749,568 bytes | 85,860 KiB |
| 4000×4000 PNG | 16,000,000 | 115,109,888 bytes | 134,496 KiB |

JPEG measurements were lower; PNG is the limiting measured format. Sixteen million pixels approaches the PHP process limit without adequate worker headroom. Phase 2 therefore enforces:

- `PROFILE_PIXEL_CEILING = 8000000`
- `PROJECT_PIXEL_CEILING = 8000000`

Actual multipart tests confirmed that a valid 4000×4000 compressed PNG below the encoded-byte limits is rejected by both upload paths.
