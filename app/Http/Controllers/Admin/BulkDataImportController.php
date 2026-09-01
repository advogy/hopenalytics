<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchSocial;
use App\Models\Conference;
use App\Models\Institution;
use App\Models\Person;
use App\Support\AuditLogger;
use App\Support\GeocodeDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk create/update Gereja, Personal, and Institusi (plus Church/Institusi social accounts) via
 * spreadsheet, scoped by the same manage-hierarchy + visibleTo() boundary already governing every
 * other org-unit CRUD route — admin_uni only ever reaches what's under their own Uni, admin_daerah
 * only their own Daerah, per the user's explicit call. Deliberately does NOT cover Personal social
 * accounts: those require the Person's own explicit consent checkbox (see
 * ChurchSocial::scopeConsentGranted(), PersonSocialController::validated()) — an admin bulk-
 * importing them on someone else's behalf would defeat the entire point of that requirement.
 *
 * Never geocodes inline on create/update — GeocodeDispatcher::dispatchFor() queues one
 * GeocodeEntity job per row instead, at the end of import(), which the existing cron-triggered
 * `queue:work --stop-when-empty` (routes/console.php) drains automatically within a minute or
 * so. No SSH/artisan command needed on top of the upload — same reasoning as
 * LocationImportController, which this mirrors.
 */
class BulkDataImportController extends Controller
{
    private const TYPES = ['gereja', 'personal', 'institusi'];

    public function index(Request $request)
    {
        $user = $request->user();

        $counts = [
            'gereja' => Church::query()->visibleTo($user)->where('is_active', true)->count(),
            'personal' => Person::query()->visibleTo($user)->where('is_active', true)->count(),
            'institusi' => Institution::query()->visibleTo($user)->where('is_active', true)->count(),
        ];

        return view('admin.bulk-import.index', ['counts' => $counts]);
    }

    public function template(Request $request, string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $user = $request->user();
        $model = $this->modelFor($type);

        $rows = $model::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city', 'country']);

        $spreadsheet = new Spreadsheet;
        $mainSheet = $spreadsheet->getActiveSheet();
        $mainSheet->setTitle('Data');
        $headers = ['ID', 'Nama', 'Kota', 'Negara', 'Daerah'];
        $mainSheet->fromArray($headers, null, 'A1');
        $mainSheet->getStyle('A1:E1')->getFont()->setBold(true);

        $rows->values()->each(fn ($row, $i) => $mainSheet->fromArray(
            [$row->id, $row->name, $row->city, $row->country, null],
            null,
            'A'.($i + 2)
        ));

        // A few blank rows at the bottom to create new entities in — ID left blank signals
        // "create new" on upload (see import() below). Daerah is required here for Gereja
        // (a Church always needs a conference_id), optional for Personal/Institusi.
        $nextRow = $rows->count() + 2;
        for ($i = 0; $i < 10; $i++) {
            $mainSheet->setCellValue('A'.($nextRow + $i), '');
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
            $mainSheet->getColumnDimension($column)->setAutoSize(true);
        }

        if (in_array($type, ['gereja', 'institusi'], true)) {
            $this->addSocialTemplateSheet($spreadsheet, $type, $model, $user);
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'bulk-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return Response::download($tempPath, "template-bulk-{$type}.xlsx")->deleteFileAfterSend(true);
    }

    private function addSocialTemplateSheet(Spreadsheet $spreadsheet, string $type, string $model, $user): void
    {
        $withCategory = $type === 'gereja';

        $owners = $model::query()->visibleTo($user)->where('is_active', true)->with('socials')->orderBy('name')->get();

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Media Sosial');
        $headers = $withCategory
            ? ['ID/Nama '.($type === 'gereja' ? 'Gereja' : 'Institusi'), 'Platform', 'Kategori (gereja/umum)', 'Handle', 'URL Profil', 'Auto Fetch (ya/tidak)']
            : ['ID/Nama '.($type === 'gereja' ? 'Gereja' : 'Institusi'), 'Platform', 'Handle', 'URL Profil', 'Auto Fetch (ya/tidak)'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $rowNum = 2;
        foreach ($owners as $owner) {
            foreach ($owner->socials as $social) {
                $row = $withCategory
                    ? [$owner->id, $social->platform->value, $social->category->value, $social->handle, $social->profile_url, $social->is_auto_fetch ? 'ya' : 'tidak']
                    : [$owner->id, $social->platform->value, $social->handle, $social->profile_url, $social->is_auto_fetch ? 'ya' : 'tidak'];
                $sheet->fromArray($row, null, 'A'.$rowNum);
                $rowNum++;
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $sheet->setCellValue('A'.($rowNum + $i), '');
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $user = $request->user();
        $type = $data['type'];
        $model = $this->modelFor($type);

        $spreadsheet = IOFactory::load($data['file']->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();

        $mainRows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);
        array_shift($mainRows);

        $result = $this->importMainSheet($mainRows, $type, $model, $user);

        $socialResult = ['created' => 0, 'updated' => 0, 'skippedOwnerNotFound' => 0, 'skippedInvalid' => 0, 'skippedDuplicate' => 0];
        if (isset($sheetNames[1]) && in_array($type, ['gereja', 'institusi'], true)) {
            $socialRows = $spreadsheet->getSheet(1)->toArray(null, true, true, false);
            array_shift($socialRows);
            $socialResult = $this->importSocialSheet($socialRows, $type, $model, $user, $result['createdByName']);
        }

        // Scoped by the same visibleTo() as everything else here — an admin_uni's upload only
        // ever queues geocoding for what's actually theirs, never spends the actor's own upload
        // triggering lookups for rows outside their wilayah that happened to also qualify.
        $queued = GeocodeDispatcher::dispatchFor($model::query()->visibleTo($user));

        AuditLogger::log('bulk-import.completed', null, "Bulk import ({$type}): {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skippedInvalid']} dilewati (data wajib kosong), {$result['skippedDaerahNotFound']} dilewati (Daerah tidak ditemukan), {$result['skippedNotFound']} dilewati (ID tidak ditemukan). {$queued} lokasi dijadwalkan untuk dicari koordinatnya. Media sosial: {$socialResult['created']} dibuat, {$socialResult['updated']} diperbarui, {$socialResult['skippedOwnerNotFound']} dilewati (pemilik tidak ditemukan), {$socialResult['skippedDuplicate']} dilewati (duplikat).");

        return redirect()->route('admin.bulk-import.index')->with('status', __('bulk_import.result', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skippedInvalid' => $result['skippedInvalid'],
            'skippedDaerahNotFound' => $result['skippedDaerahNotFound'],
            'skippedNotFound' => $result['skippedNotFound'],
            'queued' => $queued,
            'socialCreated' => $socialResult['created'],
            'socialUpdated' => $socialResult['updated'],
            'socialSkipped' => $socialResult['skippedOwnerNotFound'] + $socialResult['skippedInvalid'] + $socialResult['skippedDuplicate'],
        ]));
    }

    /** @return array{created:int,updated:int,skippedInvalid:int,skippedDaerahNotFound:int,skippedNotFound:int,createdByName:array<string,int>} */
    private function importMainSheet(array $rows, string $type, string $model, $user): array
    {
        $created = 0;
        $updated = 0;
        $skippedInvalid = 0;
        $skippedDaerahNotFound = 0;
        $skippedNotFound = 0;
        $createdByName = [];

        foreach ($rows as $row) {
            [$id, $name, $city, $country, $daerah] = array_pad($row, 5, null);

            $id = is_numeric($id) ? (int) $id : null;
            $name = is_string($name) ? trim($name) : null;
            $city = (is_string($city) && trim($city) !== '') ? trim($city) : null;
            $country = (is_string($country) && trim($country) !== '') ? trim($country) : null;
            $daerah = (is_string($daerah) && trim($daerah) !== '') ? trim($daerah) : null;

            if ($id === null && $name === null) {
                continue; // blank spreadsheet row (one of the padding rows never filled in)
            }

            if ($id !== null) {
                // Update — never touches Daerah assignment (per the user's explicit call to keep
                // that out of bulk-editable fields), scoped by visibleTo() so an ID outside the
                // actor's own wilayah is indistinguishable from a nonexistent one.
                $affected = $model::query()->visibleTo($user)->whereKey($id)->update(array_filter([
                    'city' => $city,
                    'country' => $country,
                ], fn ($v) => $v !== null));

                $affected > 0 ? $updated++ : $skippedNotFound++;

                continue;
            }

            // Create — Name is the one always-required field.
            if ($name === null || $name === '') {
                $skippedInvalid++;

                continue;
            }

            [$unionId, $conferenceId] = $this->resolveOrgIds($user, $daerah);

            if ($type === 'gereja' && $conferenceId === null) {
                // A Church always needs a conference_id — no fallback like Personal/Institusi have.
                $skippedDaerahNotFound++;

                continue;
            }

            $attributes = array_filter([
                'name' => $name,
                'city' => $city,
                'country' => $country,
            ], fn ($v) => $v !== null);

            $entity = match ($type) {
                'gereja' => $this->createChurch($attributes, $conferenceId),
                'personal' => Person::create($attributes + ['union_id' => $unionId, 'conference_id' => $conferenceId, 'is_active' => true]),
                'institusi' => $this->createInstitution($attributes, $unionId, $conferenceId),
            };

            AuditLogger::log("{$type}.created", $entity, "Membuat \"{$entity->name}\" lewat bulk import.");
            $createdByName[Str::lower($name)] = $entity->id;
            $created++;
        }

        return compact('created', 'updated', 'skippedInvalid', 'skippedDaerahNotFound', 'skippedNotFound', 'createdByName');
    }

    /** @return array{created:int,updated:int,skippedOwnerNotFound:int,skippedInvalid:int,skippedDuplicate:int} */
    private function importSocialSheet(array $rows, string $type, string $model, $user, array $createdByName): array
    {
        $ownerColumn = $type === 'gereja' ? 'church_id' : 'institution_id';
        $withCategory = $type === 'gereja';

        $created = 0;
        $updated = 0;
        $skippedOwnerNotFound = 0;
        $skippedInvalid = 0;
        $skippedDuplicate = 0;

        foreach ($rows as $row) {
            if ($withCategory) {
                [$ownerRef, $platform, $category, $handle, $profileUrl, $autoFetch] = array_pad($row, 6, null);
            } else {
                [$ownerRef, $platform, $handle, $profileUrl, $autoFetch] = array_pad($row, 5, null);
                $category = 'organisasi';
            }

            $ownerRef = is_string($ownerRef) ? trim($ownerRef) : $ownerRef;
            $platform = is_string($platform) ? Str::lower(trim($platform)) : null;
            $category = is_string($category) ? Str::lower(trim($category)) : $category;
            $handle = is_string($handle) ? ltrim(trim($handle), '@') : null;
            $profileUrl = (is_string($profileUrl) && trim($profileUrl) !== '') ? trim($profileUrl) : null;
            $isAutoFetch = ! (is_string($autoFetch) && in_array(Str::lower(trim($autoFetch)), ['tidak', 'no', 'false', '0'], true));

            if (($ownerRef === null || $ownerRef === '') && $platform === null && $handle === null) {
                continue; // blank padding row
            }

            $ownerId = $this->resolveOwnerId($ownerRef, $model, $user, $createdByName);

            if ($ownerId === null) {
                $skippedOwnerNotFound++;

                continue;
            }

            if ($platform === null || ! SocialPlatform::tryFrom($platform) || $handle === null || $handle === '') {
                $skippedInvalid++;

                continue;
            }

            if ($withCategory && ! in_array($category, ['gereja', 'umum'], true)) {
                $skippedInvalid++;

                continue;
            }

            $duplicate = ChurchSocial::query()
                ->where('platform', $platform)->where('category', $category)
                ->whereRaw('LOWER(handle) = ?', [Str::lower($handle)])
                ->where('is_active', true)
                ->where($ownerColumn, $ownerId)
                ->exists();

            if ($duplicate) {
                $skippedDuplicate++;

                continue;
            }

            $existingInactive = ChurchSocial::query()
                ->where($ownerColumn, $ownerId)->where('platform', $platform)->where('category', $category)
                ->where('handle', $handle)->where('is_active', false)->first();

            if ($existingInactive) {
                $existingInactive->update([
                    'profile_url' => $profileUrl,
                    'is_auto_fetch' => $isAutoFetch,
                    'is_active' => true,
                ]);
                $updated++;

                continue;
            }

            ChurchSocial::create([
                $ownerColumn => $ownerId,
                'platform' => $platform,
                'category' => $category,
                'handle' => $handle,
                'profile_url' => $profileUrl,
                'is_active' => true,
                'is_auto_fetch' => $isAutoFetch,
            ]);
            $created++;
        }

        return compact('created', 'updated', 'skippedOwnerNotFound', 'skippedInvalid', 'skippedDuplicate');
    }

    /**
     * Numeric → an existing owner's ID, scoped by visibleTo(). Non-numeric → first checked
     * against entities created earlier in THIS SAME upload's main sheet (so a brand-new church's
     * social accounts can be added in the same file, without ever knowing its ID in advance),
     * then against an existing owner's name within scope.
     */
    private function resolveOwnerId(?string $ownerRef, string $model, $user, array $createdByName): ?int
    {
        if ($ownerRef === null || $ownerRef === '') {
            return null;
        }

        if (is_numeric($ownerRef)) {
            return $model::query()->visibleTo($user)->whereKey((int) $ownerRef)->exists() ? (int) $ownerRef : null;
        }

        $normalized = Str::lower($ownerRef);

        if (isset($createdByName[$normalized])) {
            return $createdByName[$normalized];
        }

        $match = $model::query()->visibleTo($user)->whereRaw('LOWER(name) = ?', [$normalized])->first();

        return $match?->id;
    }

    /**
     * Blank $daerahName: 'daerah'-level is auto-pinned to their own; everyone else gets no
     * conference (independent-within-scope for Personal/Institusi; Church requires a match and
     * fails without one — see importMainSheet()). Non-blank: matched by exact name (case-
     * insensitive) against Conference::visibleTo() — already exactly the actor's own reachable
     * set, so no separate per-level branching is needed here.
     */
    private function resolveOrgIds($user, ?string $daerahName): array
    {
        if ($daerahName !== null) {
            $conference = Conference::query()->visibleTo($user)->whereRaw('LOWER(name) = ?', [Str::lower($daerahName)])->first();

            return $conference ? [$conference->union_id, $conference->id] : [null, null];
        }

        return match ($user->role->level()) {
            'daerah' => [$user->conference->union_id, $user->conference_id],
            'uni' => [$user->union_id, null],
            default => [null, null],
        };
    }

    private function createChurch(array $attributes, int $conferenceId): Church
    {
        $original = Str::slug($attributes['name']);
        $slug = $original;
        $i = 1;

        while (Church::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return Church::create($attributes + ['slug' => $slug, 'conference_id' => $conferenceId, 'is_active' => true]);
    }

    private function createInstitution(array $attributes, ?int $unionId, ?int $conferenceId): Institution
    {
        $original = Str::slug($attributes['name']);
        $slug = $original;
        $i = 1;

        while (Institution::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return Institution::create($attributes + ['slug' => $slug, 'union_id' => $unionId, 'conference_id' => $conferenceId, 'is_active' => true]);
    }

    private function modelFor(string $type): string
    {
        return match ($type) {
            'gereja' => Church::class,
            'personal' => Person::class,
            'institusi' => Institution::class,
        };
    }
}
