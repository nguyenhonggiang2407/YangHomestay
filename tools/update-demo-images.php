<?php
declare(strict_types=1);

/**
 * DEPRECATED / FAIL-SAFE — 2026-08-17 LogicFix/GalleryFix.
 *
 * Helper cũ này từng tạo lại gallery theo scene demo và có thể đưa Unit 14
 * về cover workspace + gallery không chứng minh đúng sức chứa. Nó cũng có thể
 * rewrite database/supabase/002_seed.sql bằng mapping cũ.
 *
 * Vì gallery hiện đã được curate bằng ảnh local thật, script này KHÔNG còn
 * được phép mutate database/seed.
 *
 * Nguồn dữ liệu authoritative:
 *   - database/yang_homestay_seed.sql                         (fresh MySQL)
 *   - database/supabase/002_seed.sql                         (fresh Supabase dev reset)
 *   - database/supabase/011_gallery_capacity_logic_fix.sql   (Supabase existing DB)
 *   - database/011_gallery_capacity_logic_fix_mysql.sql      (MySQL existing DB)
 *   - assets/images/SOURCES.csv + docs/image-sources.md
 */

fwrite(STDOUT, "update-demo-images.php is deprecated and will not modify data.\n");
fwrite(STDOUT, "Existing Supabase: run database/supabase/011_gallery_capacity_logic_fix.sql (after 010 if needed).\n");
fwrite(STDOUT, "Existing MySQL: run database/011_gallery_capacity_logic_fix_mysql.sql.\n");
exit(0);
