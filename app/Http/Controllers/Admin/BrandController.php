<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Lets a superadmin/admin_global register the mirror domains this app is white-labeled under —
 * see ResolveBrand middleware for how a request's Host header picks one of these at runtime.
 * Deliberately small (index + a shared create/edit form): a handful of domains, known upfront,
 * not the hundreds-of-rows scale the rest of Kelola Akun is built for.
 */
class BrandController extends Controller
{
    public function index()
    {
        return view('admin.brands.index', ['brands' => Brand::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('admin.brands.form', ['brand' => new Brand]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['logo_path'] = $this->storeLogo($request);

        $brand = Brand::create($data);

        AuditLogger::log('brand.created', $brand, "Menambahkan brand \"{$brand->name}\" untuk domain \"{$brand->domain}\".");

        return redirect()->route('admin.brands.index')->with('status', __('brands.created', ['name' => $brand->name]));
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.form', ['brand' => $brand]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request, $brand);

        if ($newLogoPath = $this->storeLogo($request)) {
            $this->deleteLogo($brand);
            $data['logo_path'] = $newLogoPath;
        }

        $brand->update($data);

        AuditLogger::log('brand.updated', $brand, "Memperbarui brand \"{$brand->name}\" ({$brand->domain}).");

        return redirect()->route('admin.brands.index')->with('status', __('brands.updated', ['name' => $brand->name]));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $name = $brand->name;
        $this->deleteLogo($brand);
        $brand->delete();

        AuditLogger::log('brand.deleted', $brand, "Menghapus brand \"{$name}\".");

        return redirect()->route('admin.brands.index')->with('status', __('brands.deleted', ['name' => $name]));
    }

    /**
     * Normalized the same way ResolveBrand reads it back (strip a "www." prefix, lowercase),
     * so a domain entered either way still resolves — and so two rows can't collide on what's
     * effectively the same host.
     */
    private function validated(Request $request, ?Brand $brand = null): array
    {
        $request->merge(['domain' => Brand::normalizeDomain((string) $request->input('domain'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required', 'string', 'max:255',
                Rule::unique('brands', 'domain')->ignore($brand),
            ],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ], [], ['domain' => 'domain']);
    }

    private function storeLogo(Request $request): ?string
    {
        return $request->hasFile('logo') ? $request->file('logo')->store('brands', 'public') : null;
    }

    private function deleteLogo(Brand $brand): void
    {
        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }
    }
}
