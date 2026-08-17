<?php
declare(strict_types=1);

/**
 * Supabase backend configuration template.
 *
 * Copy this file to `backend/config.supabase.local.php` on the server and fill
 * the two placeholders below, OR configure SUPABASE_URL / SUPABASE_SECRET_KEY
 * as server-side environment variables. The local file is gitignored.
 *
 * SECURITY: `secret_key` is a backend secret. Never expose it from customer JS,
 * commit it to Git, or place it in a downloadable public asset.
 */
return [
    'url' => 'https://YOUR-PROJECT-REF.supabase.co',
    'secret_key' => 'YOUR_BACKEND_SUPABASE_SECRET_KEY',
    'environment' => 'production',
];
