<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case AdminNasional = 'admin_nasional';
    case AdminUni = 'admin_uni';
    case AdminDaerah = 'admin_daerah';
    case AdminGereja = 'admin_gereja';
    case AdminInstitusi = 'admin_institusi';
    case PimpinanNasional = 'pimpinan_nasional';
    case PimpinanUni = 'pimpinan_uni';
    case PimpinanDaerah = 'pimpinan_daerah';
    case PimpinanGereja = 'pimpinan_gereja';
    case PimpinanInstitusi = 'pimpinan_institusi';

    /**
     * The organizational level this role sees, or null for SuperAdmin (sees everything,
     * not tied to any single level). "institusi" sits outside the nasional→uni→daerah→gereja
     * chain (decision #5) — institutions aren't nested under a single Union/Conference, so
     * they're managed directly by nasional-level actors instead of delegated down the chain.
     */
    public function level(): ?string
    {
        return match ($this) {
            self::SuperAdmin => null,
            self::AdminNasional, self::PimpinanNasional => 'nasional',
            self::AdminUni, self::PimpinanUni => 'uni',
            self::AdminDaerah, self::PimpinanDaerah => 'daerah',
            self::AdminGereja, self::PimpinanGereja => 'gereja',
            self::AdminInstitusi, self::PimpinanInstitusi => 'institusi',
        };
    }

    public function isReadOnly(): bool
    {
        return in_array($this, [
            self::PimpinanNasional,
            self::PimpinanUni,
            self::PimpinanDaerah,
            self::PimpinanGereja,
            self::PimpinanInstitusi,
        ], true);
    }

    public function hasNasionalAccess(): bool
    {
        return in_array($this, [self::SuperAdmin, self::AdminNasional], true);
    }

    /**
     * The org level THIS role can promote a member into, or null if this role
     * can't promote anyone (gereja-level and every Pimpinan role). Institusi isn't part of
     * this chain — see level() — so it's never returned here; it's assigned directly via
     * the manage-institution-users gate instead.
     */
    public function promotesToLevel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'nasional',
            self::AdminNasional => 'uni',
            self::AdminUni => 'daerah',
            self::AdminDaerah => 'gereja',
            default => null,
        };
    }

    /**
     * Human-readable label for display (e.g. in the account menu), since the raw
     * backing value ("admin_uni") is meant for storage/matching, not the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadmin',
            self::AdminNasional => 'Admin Nasional',
            self::AdminUni => 'Admin Uni',
            self::AdminDaerah => 'Admin Daerah',
            self::AdminGereja => 'Admin Gereja',
            self::AdminInstitusi => 'Admin Institusi',
            self::PimpinanNasional => 'Pimpinan Nasional',
            self::PimpinanUni => 'Pimpinan Uni',
            self::PimpinanDaerah => 'Pimpinan Daerah',
            self::PimpinanGereja => 'Pimpinan Gereja',
            self::PimpinanInstitusi => 'Pimpinan Institusi',
        };
    }
}
