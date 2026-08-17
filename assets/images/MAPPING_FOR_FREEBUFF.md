# YangHomestay real-photo mapping

Sau khi chạy `TAI_ANH_YANGHOMESTAY.bat`, copy toàn bộ các thư mục ảnh vào:

`WEBODER/assets/images/homestays/`

Sau đó yêu cầu Freebuff map theo bảng dưới đây.

| Unit / nhóm | Cover đề xuất | Ảnh phụ |
|---|---|---|
| Cau Giay Business Apartment | `cau-giay-business-apartment/cover.jpg` | `workspace.jpg` |
| Cau Giay Modern Studio | `cau-giay-modern-studio/cover.jpg` | — |
| West Lake Sunset Studio | `west-lake-sunset-studio/cover.jpg` | `lake-view.jpg` |
| West Lake Workcation Loft | `west-lake-workcation-loft/cover.jpg` | `workspace.jpg` |
| West Lake Family Apartment | `west-lake-family-apartment/cover.jpg` | — |
| Truc Bach Lake View Studio | `truc-bach-lake-view-studio/cover.jpg` | `window-view.jpg` |
| Truc Bach Balcony Room | `truc-bach-balcony-room/cover.jpg` | — |
| Ba Dinh Family Apartment | `ba-dinh-family-apartment/cover.jpg` | `bedroom.jpg` |
| Old Quarter Balcony Studio | `old-quarter-balcony-studio/cover.jpg` | — |
| Hanoi Vintage Loft | `hanoi-vintage-loft/cover.jpg` | — |
| Hoan Kiem Couple Studio | `hoan-kiem-couple-studio/cover.jpg` | — |
| Dong Da Cozy Studio | `dong-da-cozy-studio/cover.jpg` | — |
| Dong Da Urban Loft | `dong-da-urban-loft/cover.jpg` | — |
| Unit có bathtub thật | Không dùng làm cover mặc định | `premium-bathroom/bathtub.jpg` hoặc `light-bathroom.jpg` |
| Property exterior / Hanoi section | `hanoi-exterior/hanoi-apartment-exterior.jpg` | — |
| Fallback | `fallback/unit-fallback.jpg` | — |

## Prompt ngắn cho Freebuff

```text
Real photos are now available under:
assets/images/homestays/

Read MAPPING_FOR_FREEBUFF.md and replace the current illustrated placeholders with the provided real local JPG photos.

Requirements:
- Use the mapped cover image for each matching unit.
- Populate property_images/unit_images safely when appropriate.
- Do not fake amenities: bathtub/lake-view/balcony images only for units whose database data supports them.
- Use object-fit: cover, consistent 4:3 card ratio, loading="lazy" for non-critical images, and fallback/unit-fallback.jpg for errors.
- Apply photos to homepage cards, listing cards, favorites, property/unit detail galleries and booking summaries where appropriate.
- Keep Supabase/booking/auth logic unchanged.
- Do not use remote hotlinks after integration; use local asset paths.
```
