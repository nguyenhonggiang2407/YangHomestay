<?php
declare(strict_types=1);

/**
 * DEPRECATED / FAIL-SAFE — image mapping is consolidated.
 *
 * Tool cũ không còn mutate database/seed để tránh ghi đè gallery curated.
 * Dùng migration 011 cho bản LogicFix/GalleryFix hiện tại.
 */

fwrite(STDOUT, "Image mapping is curated and this helper is disabled.\n");
fwrite(STDOUT, "Existing Supabase: run database/supabase/011_gallery_capacity_logic_fix.sql (after 010 if needed).\n");
fwrite(STDOUT, "Existing MySQL: run database/011_gallery_capacity_logic_fix_mysql.sql.\n");
exit(0);
