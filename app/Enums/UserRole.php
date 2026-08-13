<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case AdminGlobal = 'admin_global';
    case AdminNasional = 'admin_nasional';
    case AdminUni = 'admin_uni';
    case AdminDaerah = 'admin_daerah';
    case AdminGereja = 'admin_gereja';
    case AdminInstitusi = 'admin_institusi';
    case PimpinanGlobal = 'pimpinan_global';
    case PimpinanNasional = 'pimpinan_nasional';
    case PimpinanUni = 'pimpinan_uni';
    case PimpinanDaerah = 'pimpinan_daerah';
    case PimpinanGereja = 'pimpinan_gereja';
    case PimpinanInstitusi = 'pimpinan_institusi';

    /**
     * The organizational level this role sees, or null for SuperAdmin (sees everything,
     * not tied to any single level, never delegated — only bootstrapped via
     * `artisan make:superadmin`). "global" (Admin/Pimpinan Global) is also fully
     * unrestricted like SuperAdmin, but IS part of the normal delegation chain (see
     * promotesToLevel()) and needs its own matchable level string for that — see
     * hasGlobalAccess() for the "is this unrestricted" check most callers actually want.
     * "nasional" (Admin/Pimpinan Nasional) is scoped to an assigned SET of Union records
     * (see User::assignedUnions()) rather than a single region column, since one country
     * can have multiple Unions and one Union can span multiple countries — there's no
     * clean single "region" value to store on the user row the way uni/daerah/gereja/
     * institusi levels do. "institusi" sits outside the global→nasional→uni→daerah→gereja
     * chain — institutions aren't nested under a single Union/Conference, so they're
     * assigned directly via the manage-institution-users gate instead.
     */
    public function level(): ?string
    {
        return match ($this) {
            self::SuperAdmin => null,
            self::AdminGlobal, self::PimpinanGlobal => 'global',
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
            self::PimpinanGlobal,
            self::PimpinanNasional,
            self::PimpinanUni,
            self::PimpinanDaerah,
            self::PimpinanGereja,
            self::PimpinanInstitusi,
        ], true);
    }

    /**
     * True, fully unrestricted access — no region filter anywhere. Renamed from
     * hasNasionalAccess() when Admin Nasional was repurposed to be scoped to an
     * assigned set of Unions instead of unrestricted; Admin Global took over the
     * "unrestricted" meaning the old name described.
     */
    public function hasGlobalAccess(): bool
    {
        return in_array($this, [self::SuperAdmin, self::AdminGlobal], true);
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
            self::SuperAdmin => 'global',
            self::AdminGlobal => 'nasional',
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
            self::AdminGlobal => 'Admin Global',
            self::AdminNasional => 'Admin Nasional',
            self::AdminUni => 'Admin Uni',
            self::AdminDaerah => 'Admin Daerah',
            self::AdminGereja => 'Admin Gereja',
            self::AdminInstitusi => 'Admin Institusi',
            self::PimpinanGlobal => 'Pimpinan Global',
            self::PimpinanNasional => 'Pimpinan Nasional',
            self::PimpinanUni => 'Pimpinan Uni',
            self::PimpinanDaerah => 'Pimpinan Daerah',
            self::PimpinanGereja => 'Pimpinan Gereja',
            self::PimpinanInstitusi => 'Pimpinan Institusi',
        };
    }
}
