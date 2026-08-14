<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    protected $fillable = ['domain', 'name', 'logo_path'];

    /**
     * The domain a request's Host header is matched against — normalized the same way here
     * and in ResolveBrand so a stored "www.example.org" and an incoming "example.org" (or vice
     * versa) still match. preg_replace (not Stringable::ltrim, which trims by character set,
     * not by prefix) so the "www." strip only ever removes a genuine prefix. Also tolerant of
     * a full URL pasted into the admin form (the field hint asks for just the host, but
     * "https://example.org/" is an easy mistake) — Request::getHost() from a real request
     * never has a scheme/path to begin with, so this branch is a no-op for it.
     */
    public static function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);

        if (str_contains($domain, '://')) {
            $domain = parse_url($domain, PHP_URL_HOST) ?? $domain;
        }

        $domain = explode('/', $domain, 2)[0];

        return preg_replace('/^www\./', '', strtolower($domain));
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
