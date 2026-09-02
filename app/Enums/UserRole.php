<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case AdminGlobal = 'admin_global';
    case AdminNasional = 'admin_nasional';
    case AdminDivisi = 'admin_divisi';
    case AdminUni = 'admin_uni';
    case AdminDaerah = 'admin_daerah';
    case AdminGereja = 'admin_gereja';
    case AdminInstitusi = 'admin_institusi';
    case PimpinanGlobal = 'pimpinan_global';
    case PimpinanNasional = 'pimpinan_nasional';
    case PimpinanDivisi = 'pimpinan_divisi';
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
     * clean single "region" value to store on the user row the way divisi/uni/daerah/gereja/
     * institusi levels do. "divisi" (Admin/Pimpinan Divisi) sits between nasional and uni —
     * unlike nasional's arbitrary assigned set, Divisi IS a real hierarchical parent of Union
     * (see Division model, Union::division_id), independent of Admin Nasional's own Union-set
     * assignment. "institusi" sits outside the global→nasional→divisi→uni→daerah→gereja
     * chain — institutions aren't nested under a single Union/Conference, so they're
     * assigned directly via the manage-institution-users gate instead.
     */
    public function level(): ?string
    {
        return match ($this) {
            self::SuperAdmin => null,
            self::AdminGlobal, self::PimpinanGlobal => 'global',
            self::AdminNasional, self::PimpinanNasional => 'nasional',
            self::AdminDivisi, self::PimpinanDivisi => 'divisi',
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
            self::PimpinanDivisi,
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
            self::AdminNasional => 'divisi',
            self::AdminDivisi => 'uni',
            self::AdminUni => 'daerah',
            self::AdminDaerah => 'gereja',
            default => null,
        };
    }

    /**
     * Human-readable label for display (e.g. in the account menu), since the raw
     * backing value ("admin_uni") is meant for storage/matching, not the UI. Keyed by
     * $this->value in lang/{id,en}/roles.php, one file per locale — this was hardcoded to
     * Indonesian only until it was found unlocalized on every authenticated page's account menu.
     */
    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
