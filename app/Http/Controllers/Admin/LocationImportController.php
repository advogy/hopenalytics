<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Institution;
use App\Models\Person;
use App\Support\AuditLogger;
use App\Support\GeocodeDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk city/country data entry via spreadsheet — built after retiring the old name-based
 * geocoding fallback (see GeocodingService::placeQueryFor()) left hundreds of churches/people/
 * institutions with no way to get a map marker back except typing city+country into each one's
 * edit form individually. This only ever touches the city/country columns directly — never
 * latitude/longitude/geocoded_at; GeocodeDispatcher::dispatchFor() queues one GeocodeEntity job
 * per newly-fillable row instead (see that job's doc comment for why this can't just look up
 * coordinates inline here), which the existing cron-triggered `queue:work --stop-when-empty`
 * (routes/console.php) drains automatically within a minute or so — no SSH/artisan command
 * needed on top of the upload itself.
 */
class LocationImportController extends Controller
{
    private const TYPES = ['gereja', 'personal', 'institusi'];

    public function index()
    {
        $counts = [
            'gereja' => Church::query()->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('city')->orWhereNull('country'))->count(),
            'personal' => Person::query()->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('city')->orWhereNull('country'))->count(),
            'institusi' => Institution::query()->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('city')->orWhereNull('country'))->count(),
        ];

        return view('admin.location-import.index', ['counts' => $counts]);
    }

    public function template(string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $rows = $this->modelFor($type)::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('city')->orWhereNull('country'))
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'country']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lokasi');
        $sheet->fromArray(['ID', 'Nama', 'Kota', 'Negara'], null, 'A1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        $rows->values()->each(fn ($row, $i) => $sheet->fromArray(
            [$row->id, $row->name, $row->city, $row->country],
            null,
            'A'.($i + 2)
        ));

        foreach (['A', 'B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Column A (ID) is what matching on upload keys off — protected so a spreadsheet
        // program's own "sort by name" doesn't quietly scramble which ID goes with which row.
        $sheet->getProtection()->setSheet(true);
        $sheet->getStyle('C:D')->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);

        $tempPath = tempnam(sys_get_temp_dir(), 'location-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return Response::download($tempPath, "template-lokasi-{$type}.xlsx")->deleteFileAfterSend(true);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $model = $this->modelFor($data['type']);

        $spreadsheet = IOFactory::load($data['file']->getRealPath());
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($sheetRows); // header row

        $updated = 0;
        $skippedBlank = 0;
        $skippedNotFound = 0;

        foreach ($sheetRows as $row) {
            [$id, , $city, $country] = array_pad($row, 4, null);

            $id = is_numeric($id) ? (int) $id : null;
            $city = is_string($city) ? trim($city) : $city;
            $country = is_string($country) ? trim($country) : $country;

            if ($id === null) {
                continue;
            }

            if (empty($city) || empty($country)) {
                $skippedBlank++;

                continue;
            }

            $affected = $model::query()->whereKey($id)->update(['city' => $city, 'country' => $country]);

            if ($affected === 0) {
                $skippedNotFound++;
            } else {
                $updated++;
            }
        }

        $queued = GeocodeDispatcher::dispatchFor($model::query());

        AuditLogger::log(
            'location.bulk-import',
            null,
            "Import lokasi massal ({$data['type']}): {$updated} diperbarui, {$skippedBlank} dilewati (kota/negara kosong), {$skippedNotFound} dilewati (ID tidak ditemukan). {$queued} lokasi dijadwalkan untuk dicari koordinatnya."
        );

        return redirect()->route('admin.location-import.index')->with('status', __('location_import.result', [
            'updated' => $updated,
            'skippedBlank' => $skippedBlank,
            'skippedNotFound' => $skippedNotFound,
            'queued' => $queued,
        ]));
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
