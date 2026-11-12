<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsOrCreatesChurch;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Conference;
use App\Support\AuditLogger;
use App\Support\MemberEngagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Division admins upload each church's recorded membership as of a date, from a spreadsheet.
 * A church named in the file that doesn't exist yet is created in the given Daerah. Every
 * uploaded figure waits for the Union admin's confirmation (MembershipConfirmationController)
 * before it counts.
 */
class MembershipImportController extends Controller
{
    use FindsOrCreatesChurch;

    public function index(Request $request)
    {
        $churchIds = Church::query()->visibleTo($request->user())->select('id');

        $uploads = ChurchMembership::query()
            ->whereIn('church_id', $churchIds)
            ->selectRaw('as_of, COUNT(*) as churches, SUM(members_count) as members')
            ->selectRaw("SUM(status = 'pending') as pending, SUM(status = 'confirmed') as confirmed, SUM(status = 'rejected') as rejected")
            ->groupBy('as_of')->orderByDesc('as_of')->limit(12)->get();

        return view('engagement.import', ['uploads' => $uploads]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        $churches = Church::query()->visibleTo($user)->where('is_active', true)
            ->with('conference:id,name')->get(['id', 'name', 'conference_id'])
            ->sortBy(fn (Church $c) => [$c->conference?->name, $c->name]);

        $latest = MemberEngagement::latestConfirmed(churchIds: $churches->pluck('id'));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray(['ID', 'Gereja', 'Daerah', 'Jumlah Anggota', 'Terakhir dikonfirmasi (info)', 'Per tanggal (info)'], null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $churches->values()->each(fn (Church $church, $i) => $sheet->fromArray([
            $church->id,
            $church->name,
            $church->conference?->name,
            null,
            $latest->get($church->id)?->members_count,
            $latest->get($church->id)?->as_of,
        ], null, 'A'.($i + 2)));

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $reference = $spreadsheet->createSheet();
        $reference->setTitle('Daftar Daerah');
        $reference->setCellValue('A1', 'Daerah');
        $reference->getStyle('A1')->getFont()->setBold(true);
        Conference::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->pluck('name')
            ->values()->each(fn ($name, $i) => $reference->setCellValue('A'.($i + 2), $name));
        $reference->getColumnDimension('A')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'membership').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return Response::download($path, 'template-jumlah-anggota.xlsx')->deleteFileAfterSend(true);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'as_of' => ['required', 'date', 'before_or_equal:today'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $user = $request->user();
        $asOf = Carbon::parse($data['as_of'])->toDateString();

        $spreadsheet = IOFactory::load($data['file']->getRealPath());
        $rows = ($spreadsheet->getSheetByName('Data') ?? $spreadsheet->getSheet(0))->toArray(null, true, true, false);
        array_shift($rows);

        $conferences = Conference::query()->visibleTo($user)->where('is_active', true)->get()
            ->keyBy(fn (Conference $c) => Str::lower(trim($c->name)));

        $result = ['saved' => 0, 'unchanged' => 0, 'created' => 0, 'invalid' => 0, 'not_found' => 0, 'locked' => 0, 'inactive' => 0, 'daerah_not_found' => 0, 'ambiguous' => 0];
        $createdNames = [];

        DB::transaction(function () use ($rows, $user, $asOf, $conferences, &$result, &$createdNames) {
            foreach ($rows as $row) {
                [$id, $name, $daerah, $count] = array_pad($row, 4, null);

                $name = trim((string) $name);
                $daerah = trim((string) $daerah);
                $count = $this->parseCount($count);

                if ($count === null) {
                    continue; // no figure for this church in this upload
                }

                if ($count === false) {
                    $result['invalid']++;

                    continue;
                }

                $church = $this->resolveChurch($user, $id, $name, $daerah, $conferences, $result, $createdNames);

                if ($church === null) {
                    continue;
                }

                $existing = ChurchMembership::query()->where('church_id', $church->id)->whereDate('as_of', $asOf)->first();

                if ($existing && $existing->members_count === (int) $count && $existing->status !== ChurchMembership::REJECTED) {
                    $result['unchanged']++;

                    continue;
                }

                // A confirmed figure is never overwritten: that would send it back to pending and
                // drop the church's counted membership until it's reviewed again (or lose it for
                // good if the new figure is rejected). A correction goes in under a new date.
                if ($existing?->status === ChurchMembership::CONFIRMED) {
                    $result['locked']++;

                    continue;
                }

                ChurchMembership::query()->updateOrCreate(
                    ['church_id' => $church->id, 'as_of' => $asOf],
                    ['members_count' => (int) $count, 'status' => ChurchMembership::PENDING, 'uploaded_by' => $user->id, 'reviewed_by' => null, 'reviewed_at' => null],
                );
                $result['saved']++;
            }
        });

        AuditLogger::log('membership.imported', null, "Mengunggah jumlah anggota per {$asOf}: {$result['saved']} disimpan (menunggu konfirmasi), {$result['unchanged']} tidak berubah, {$result['locked']} dilewati karena sudah dikonfirmasi, {$result['created']} gereja baru dibuat"
            .($createdNames ? ' ('.implode(', ', $createdNames).')' : '')
            .", {$result['not_found']} ID tidak ditemukan, {$result['inactive']} gereja nonaktif, {$result['daerah_not_found']} Daerah tidak ditemukan, {$result['ambiguous']} nama gereja ganda tanpa Daerah, {$result['invalid']} angka tidak valid.");

        return redirect()->route('engagement.import.index')->with('status', __('engagement.import_result', $result));
    }

    /**
     * By ID first (only churches in the admin's region), otherwise by name within the given
     * Daerah — creating the church there if it doesn't exist yet. A name without a Daerah only
     * works when exactly one church in the region has that name.
     */
    private function resolveChurch($user, $id, string $name, string $daerah, $conferences, array &$result, array &$createdNames): ?Church
    {
        if (is_numeric($id)) {
            $church = Church::query()->visibleTo($user)->whereKey((int) $id)->first();

            if ($church === null) {
                $result['not_found']++;
            }

            return $this->activeOrSkip($church, $result);
        }

        if ($name === '') {
            $result['invalid']++;

            return null;
        }

        if ($daerah !== '') {
            $conference = $conferences->get(Str::lower($daerah));

            if ($conference === null) {
                $result['daerah_not_found']++;

                return null;
            }

            // An existing church of that name is reused, even a deactivated one — which is then
            // skipped rather than given figures while it's switched off.
            $church = $this->findOrCreateChurch($conference->id, $name);

            if ($church->wasRecentlyCreated) {
                AuditLogger::log('church.created', $church, "Membuat Gereja \"{$church->name}\" lewat unggah jumlah anggota.");
                $result['created']++;
                $createdNames[] = $church->name;
            }

            return $this->activeOrSkip($church, $result);
        }

        $matches = Church::query()->visibleTo($user)->where('is_active', true)->whereRaw('LOWER(name) = ?', [Str::lower($name)])->limit(2)->get();

        if ($matches->count() !== 1) {
            $matches->isEmpty() ? $result['daerah_not_found']++ : $result['ambiguous']++;

            return null;
        }

        return $matches->first();
    }

    private function activeOrSkip(?Church $church, array &$result): ?Church
    {
        if ($church !== null && ! $church->is_active) {
            $result['inactive']++;

            return null;
        }

        return $church;
    }

    /**
     * The Jumlah Anggota cell as a whole number: null when empty, false when it isn't one.
     * Thousands separators are allowed ("1.250", "1,250"); a decimal ("12,5") is not —
     * stripping every separator used to read that as 125.
     */
    private function parseCount(mixed $value): int|false|null
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (is_int($value) || (is_float($value) && floor($value) === $value)) {
            return $value >= 0 ? (int) $value : false;
        }

        $value = str_replace(' ', '', trim((string) $value));

        if (preg_match('/^\d+$/', $value) || preg_match('/^\d{1,3}([.,])\d{3}(\1\d{3})*$/', $value)) {
            return (int) preg_replace('/\D/', '', $value);
        }

        return false;
    }
}
