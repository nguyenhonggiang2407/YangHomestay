<?php
declare(strict_types=1);

/**
 * Central RBAC policy for YangHomestay internal portals.
 * Backend is the source of truth; frontend menus are presentation only.
 */
final class InternalAccess
{
    public const ROLES = ['super_admin', 'manager', 'staff'];

    private const PORTAL_ROLES = [
        'admin'   => ['super_admin'],
        'manager' => ['super_admin', 'manager'],
        'staff'   => ['super_admin', 'manager', 'staff'],
    ];

    private const PERMISSIONS = [
        'dashboard.view'          => ['super_admin', 'manager', 'staff'],
        'calendar.view'           => ['super_admin', 'manager', 'staff'],
        'bookings.view'           => ['super_admin', 'manager', 'staff'],
        'bookings.status'         => ['super_admin', 'manager', 'staff'],
        'bookings.payment'        => ['super_admin', 'manager'],
        'customers.view'          => ['super_admin', 'manager', 'staff'],
        'reviews.view'            => ['super_admin', 'manager', 'staff'],
        'reviews.moderate'        => ['super_admin', 'manager'],
        'contacts.view'           => ['super_admin', 'manager'],
        'contacts.update'         => ['super_admin', 'manager'],
        'properties.view'         => ['super_admin', 'manager', 'staff'],
        'properties.write'        => ['super_admin', 'manager'],
        'units.view'              => ['super_admin', 'manager', 'staff'],
        'units.write'             => ['super_admin', 'manager'],
        'pricing.manage'          => ['super_admin', 'manager'],
        'promotions.manage'       => ['super_admin', 'manager'],
        'revenue.view'            => ['super_admin', 'manager'],
        'internal_accounts.manage'=> ['super_admin'],
        'audit_logs.view'         => ['super_admin', 'manager'],
        'system_settings.view'    => ['super_admin'],
    ];

    private const ACTION_PERMISSION = [
        'dashboard'               => 'dashboard.view',
        'operations-today'        => 'dashboard.view',
        'calendar'                => 'calendar.view',
        'revenue'                 => 'revenue.view',
        'admin-bookings'          => 'bookings.view',
        'admin-booking-detail'    => 'bookings.view',
        'admin-booking-status'    => 'bookings.status',
        'admin-booking-payment'   => 'bookings.payment',
        'admin-customers'         => 'customers.view',
        'admin-reviews'           => 'reviews.view',
        'admin-review-moderate'   => 'reviews.moderate',
        'admin-contacts'          => 'contacts.view',
        'admin-contact-status'    => 'contacts.update',
        'admin-properties'        => 'properties.view',
        'admin-property-get'      => 'properties.view',
        'admin-property-save'     => 'properties.write',
        'admin-property-status'   => 'properties.write',
        'admin-units'             => 'units.view',
        'admin-unit-get'          => 'units.view',
        'admin-unit-save'         => 'units.write',
        'admin-unit-status'       => 'units.write',
        'admin-rate-rules'        => 'pricing.manage',
        'admin-rate-rule-save'    => 'pricing.manage',
        'admin-rate-rule-delete'  => 'pricing.manage',
        'admin-promotions'        => 'promotions.manage',
        'admin-promotion-save'    => 'promotions.manage',
        'internal-accounts'       => 'internal_accounts.manage',
        'internal-account-save'   => 'internal_accounts.manage',
        'internal-account-status' => 'internal_accounts.manage',
        'audit-logs'              => 'audit_logs.view',
        'system-settings'         => 'system_settings.view',
    ];

    public static function rolesForPortal(string $portal): array
    {
        return self::PORTAL_ROLES[$portal] ?? [];
    }

    public static function roleCanUsePortal(string $role, string $portal): bool
    {
        return in_array($role, self::rolesForPortal($portal), true);
    }

    public static function primaryPortalForRole(string $role): string
    {
        return match ($role) {
            'super_admin' => 'admin',
            'manager'     => 'manager',
            'staff'       => 'staff',
            default       => 'staff',
        };
    }

    public static function can(string $role, string $permission): bool
    {
        return in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function requirePermission(string $permission): void
    {
        AdminAuthService::requireAdmin();
        $role = AdminAuthService::currentRole();
        if (!self::can($role, $permission)) {
            ApiResponse::fail('Bạn không có quyền thực hiện thao tác này', 403);
        }
    }

    public static function guardApiAction(string $action): void
    {
        $permission = self::ACTION_PERMISSION[$action] ?? null;
        if ($permission !== null) {
            self::requirePermission($permission);
        }
    }

    public static function permissionMatrix(): array
    {
        return self::PERMISSIONS;
    }
}
