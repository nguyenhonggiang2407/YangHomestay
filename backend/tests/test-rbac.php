<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

$pass = 0; $fail = 0;
function t(string $name, bool $ok): void { global $pass,$fail; if ($ok) { $pass++; echo "✅ $name\n"; } else { $fail++; echo "❌ $name\n"; } }

t('super_admin vào admin', InternalAccess::roleCanUsePortal('super_admin','admin'));
t('manager bị chặn khỏi admin', !InternalAccess::roleCanUsePortal('manager','admin'));
t('staff bị chặn khỏi admin', !InternalAccess::roleCanUsePortal('staff','admin'));
t('staff bị chặn khỏi manager', !InternalAccess::roleCanUsePortal('staff','manager'));
t('manager vào manager', InternalAccess::roleCanUsePortal('manager','manager'));
t('staff vào staff', InternalAccess::roleCanUsePortal('staff','staff'));
t('staff được đổi booking state', InternalAccess::can('staff','bookings.status'));
t('staff không được payment admin', !InternalAccess::can('staff','bookings.payment'));
t('staff không được pricing', !InternalAccess::can('staff','pricing.manage'));
t('manager được pricing', InternalAccess::can('manager','pricing.manage'));
t('manager không quản lý internal accounts', !InternalAccess::can('manager','internal_accounts.manage'));
t('chỉ super_admin quản lý internal accounts', InternalAccess::can('super_admin','internal_accounts.manage'));
t('manager được xem audit', InternalAccess::can('manager','audit_logs.view'));
t('staff không xem audit', !InternalAccess::can('staff','audit_logs.view'));

echo "\nRBAC: $pass pass / $fail fail\n";
exit($fail === 0 ? 0 : 1);
