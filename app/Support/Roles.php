<?php

namespace App\Support;

class Roles
{
    public const SUPERADMIN = 'superadmin';

    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public const RECEPTIONIST = 'receptionist';

    public const PRACTITIONER = 'practitioner';

    public const PATIENT = 'patient';

    /**
     * @return array<int, array{id: string, label: string, description: string, staff: bool}>
     */
    public static function catalog(): array
    {
        return [
            ['id' => self::SUPERADMIN, 'label' => 'Superadmin', 'description' => 'All branches, roles, revenue, and staff.', 'staff' => true],
            ['id' => self::ADMIN, 'label' => 'Admin', 'description' => 'Organisation admin (same access as superadmin).', 'staff' => true],
            ['id' => self::MANAGER, 'label' => 'Branch manager', 'description' => 'Manages one clinic: staff, hours, appointments.', 'staff' => true],
            ['id' => self::RECEPTIONIST, 'label' => 'Receptionist', 'description' => 'Front desk for an assigned clinic.', 'staff' => true],
            ['id' => self::PRACTITIONER, 'label' => 'Practitioner', 'description' => 'Clinician scheduled at a clinic.', 'staff' => true],
            ['id' => self::PATIENT, 'label' => 'Patient', 'description' => 'Customer portal only.', 'staff' => false],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function assignable(): array
    {
        return array_column(self::catalog(), 'id');
    }

    /**
     * @return array<int, string>
     */
    public static function staff(): array
    {
        return [self::SUPERADMIN, self::ADMIN, self::MANAGER, self::RECEPTIONIST, self::PRACTITIONER];
    }

    public static function isSuperAdmin(?string $role): bool
    {
        return in_array($role, [self::SUPERADMIN, self::ADMIN], true);
    }
}
