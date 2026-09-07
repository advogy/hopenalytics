<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic "edit this in a modal instead of a separate page" helpers — originally written for
 * Kelola Akun's 6 entity controllers (see RedirectsToAccountsTab, which now just `use`s this
 * trait alongside its own Kelola-Akun-specific tab-redirect logic) and reused as-is by every
 * other "edit via the shared <x-modal>" controller since (see components/modal.blade.php +
 * admin/accounts/index.blade.php's edit-modal JS for the client side of this same contract:
 * fetch a form as a fragment via ?modal=1, submit it over fetch() with a hidden modal=1 field,
 * follow a JSON {"redirect": "..."} on success, swap a 422 HTML fragment back into the modal
 * body on failure). Nothing in here references anything Kelola-Akun-specific, which is exactly
 * why it's split out on its own rather than living only inside that trait.
 */
trait HandlesModalForms
{
    /**
     * $request->validate($rules), except when this came from an edit MODAL (a hidden modal=1
     * field on the submitted form) and validation fails: there's no page to redirect() back to —
     * the modal was never its own navigation — so instead of Laravel's default
     * redirect-back-with-flashed-errors, this re-renders the SAME edit view server-side (same
     * $view/$viewData the controller's own edit() action already renders) with the failed
     * $errors passed straight in and old() input flashed, and returns that as a 422 HTML
     * fragment for the JS to swap back into the modal body — reusing every field's existing
     * @error()/old() markup unchanged, no separate modal-specific error UI needed.
     *
     * A plain (non-modal) request either rethrows as a normal ValidationException — Laravel's
     * default handler turns that into redirect()->back()->withErrors()->withInput(), exactly
     * what $request->validate($rules) would have done on its own — or, when $failureRedirectTo
     * is given (PersonController::update()'s self-edit branch is the one caller that needs
     * this: it can't rely on back(), see that method's own comment), redirects there instead
     * with the same withErrors()/withInput().
     *
     * Returns the validated data array on success, or the Response to return as-is on failure —
     * callers do `if ($data instanceof Response) return $data;` right after calling this,
     * mirroring how $request->validate() is used everywhere else in these controllers.
     */
    private function validateOrRespondModal(Request $request, array $rules, string $view, array $viewData, ?string $failureRedirectTo = null): array|Response
    {
        $validator = Validator::make($request->all(), $rules);

        if (! $validator->fails()) {
            return $validator->validated();
        }

        if ($request->boolean('modal')) {
            $request->session()->flashInput($request->input());

            // @error()/$errors everywhere in these form views (see components/form-field.blade.php
            // and friends) expects a ViewErrorBag with a 'default' bag inside it — the same shape
            // Illuminate\View\Middleware\ShareErrorsFromSession normally shares in from the
            // session after a redirect — not the plain MessageBag $validator->errors() returns
            // on its own. It also has to be shared globally (View::share(), same call that
            // middleware itself makes), not just passed as this one view's own data — every
            // field's @error() lives inside a Blade component (<x-form-field> and friends),
            // and components don't inherit a parent view's local variables the way an @include
            // does, only globally-shared ones. Passing it merely as local data renders fine for
            // this outer view's own markup but throws ("Call to a member function getBag() on
            // null") the instant any component's own @error() directive runs.
            View::share('errors', (new ViewErrorBag)->put('default', $validator->errors()));

            return response()
                ->view($view, $viewData + ['modal' => true])
                ->setStatusCode(422);
        }

        if ($failureRedirectTo !== null) {
            return redirect($failureRedirectTo)->withErrors($validator)->withInput();
        }

        throw new ValidationException($validator);
    }

    /**
     * The success-path counterpart to validateOrRespondModal(): a modal-friendly controller's
     * update() ends with the same redirect(...)->with($flashKey, $flashMessage) shape a normal
     * full-page form would — for a modal-originated request this instead flashes the same
     * message and returns {"redirect": "..."} as JSON, so the JS can do a real
     * window.location.href navigation (reading the flash normally on arrival, exactly like the
     * full-page flow already does) rather than fetch() silently consuming the redirect's target
     * response. A non-modal request is byte-for-byte the original redirect(...)->with(...) call.
     * $target is a full URL (see respondModalOrRedirect() below for the common named-route case).
     */
    private function respondModalOrRedirectTo(Request $request, string $target, string $flashKey, string $flashMessage): RedirectResponse|JsonResponse
    {
        if ($request->boolean('modal')) {
            $request->session()->flash($flashKey, $flashMessage);

            return response()->json(['redirect' => $target]);
        }

        return redirect($target)->with($flashKey, $flashMessage);
    }

    /** respondModalOrRedirectTo() for the common case of redirecting to a named route. */
    private function respondModalOrRedirect(Request $request, string $routeName, array $routeParams, string $flashKey, string $flashMessage): RedirectResponse|JsonResponse
    {
        return $this->respondModalOrRedirectTo($request, route($routeName, $routeParams), $flashKey, $flashMessage);
    }
}
