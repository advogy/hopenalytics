{{--
    Shared "add/edit a social account" page — used for a Church's own accounts, a Person's own
    accounts, and an organization-level (Union/Conference/Institution) account. The three
    differ only in: whether a category select is shown (church accounts only — "gereja" vs
    "umum"), the auto-fetch hint wording, and the handle placeholder's example — everything
    else (platform/handle/profile_url fields, the duplicate-account check script, the
    auto-fetch checkbox, and the delete-account footer) is identical, so those differences are
    the only props this takes. $action/$backUrl are resolved by the caller since each owner
    type routes differently (see e.g. admin/organization/social-form.blade.php's owner-type
    match). Built on x-entity-crud-form, same shell every other CRUD form here uses.
--}}
@props([
    'social',
    'owner',
    'action',
    'backUrl',
    'showCategory' => false,
    'hintSuffix' => 'church',
    'handleExample' => 'gmahkbekasi',
    'requireConsent' => false,
])

<x-entity-crud-form
    :entity="$social"
    :action="$action"
    :back-url="$backUrl"
    :back-label="__('entity.back_to', ['name' => $owner->name])"
    :title="$social->exists ? __('entity.title_edit_social') : __('entity.title_add_social')"
    :submit-label="$social->exists ? __('common.save_changes') : __('entity.title_add_social')"
    :destroy-action="$social->exists ? route('socials.destroy', $social) : null"
    :destroy-confirm="__('entity.delete_account_confirm', ['handle' => $social->display_handle])"
    :destroy-label="__('entity.delete_account')"
>
    <div class="mb-5 grid grid-cols-1 gap-4 {{ $showCategory ? 'sm:grid-cols-2' : '' }}">
        <div>
            <x-select-field name="platform" :label="__('entity.platform')" required wrapper-class="">
                @foreach (\App\Models\AppSetting::current()->enabledPlatformLabels() as $value => $label)
                    <option value="{{ $value }}" @selected(old('platform', $social->platform?->value) === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>
        </div>
        @if ($showCategory)
            <div>
                <x-select-field name="category" :label="__('entity.category')" required wrapper-class="">
                    <option value="gereja" @selected(old('category', $social->category?->value) === 'gereja')>{{ __('directory.church_accounts') }}</option>
                    <option value="umum" @selected(old('category', $social->category?->value) === 'umum')>{{ __('directory.general_accounts') }}</option>
                </x-select-field>
            </div>
        @endif
    </div>

    <x-form-field name="handle" :label="__('entity.handle')" required :value="$social->handle" :placeholder="__('entity.handle_placeholder', ['example' => $handleExample])" />

    <x-form-field
        name="profile_url"
        :label="__('entity.profile_url')"
        :hint="__('entity.profile_url_hint')"
        type="url"
        :value="$social->profile_url"
        placeholder="https://www.facebook.com/..."
    />
    <div id="social-similar-results" class="hidden mb-5 -mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950"></div>
    <script>
        window.initSimilarSocialCheck(
            document.getElementById('platform'),
            document.getElementById('handle'),
            document.getElementById('profile_url'),
            document.getElementById('social-similar-results'),
            {
                url: '{{ route('socials.similar') }}',
                excludeId: {{ $social->exists ? $social->id : 'null' }},
            }
        );
    </script>

    @if ($requireConsent)
        {{--
            Personal-only (see PersonSocialController/ChurchSocialController's $personal
            validation branch, and ChurchSocial::scopeConsentGranted()) — required to submit at
            all, both on create and on re-saving an edit, so an existing account with no consent
            on record (e.g. every one already in the database the moment this shipped) can only
            regain fetch eligibility by having this box checked here. Pre-checked once consent_at
            is already set, so re-saving an already-consented account stays one click.
        --}}
        <div class="mb-6 flex items-center gap-2">
            <input
                type="checkbox" id="consent" name="consent" value="1"
                @checked(old('consent', $social->exists && $social->consent_at !== null))
                required
                class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
            >
            <label for="consent" class="text-sm">
                {{ __('entity.social_consent_label') }}
                <span class="block text-xs text-slate-400">{{ __('entity.social_consent_hint') }}</span>
            </label>
        </div>
    @endif

    <div class="mb-6 flex items-center gap-2">
        <input
            type="checkbox" id="is_auto_fetch" name="is_auto_fetch" value="1"
            @checked(old('is_auto_fetch', $social->exists ? $social->is_auto_fetch : true))
            class="h-4 w-4 rounded border-black/20 text-blue-600 focus:ring-blue-500"
        >
        <label for="is_auto_fetch" class="text-sm">
            {{ __('entity.auto_fetch_weekly') }}
            <span class="block text-xs text-slate-400">{{ __('entity.auto_fetch_hint_'.$hintSuffix) }}</span>
        </label>
    </div>
</x-entity-crud-form>
