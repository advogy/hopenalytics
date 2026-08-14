{{--
    The "did you mean an existing X?" advisory hint under a name field — wires up
    window.initSimilarNameCheck() (see NameSimilarity) against the given lookup route.
    Always paired with a form-field named "name" (this component targets #name directly).
--}}
@props(['route', 'excludeId' => null])

<div id="name-similar-results" class="hidden mb-5 -mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950"></div>
<script>
    window.initSimilarNameCheck(document.getElementById('name'), document.getElementById('name-similar-results'), {
        url: @json($route),
        excludeId: @json($excludeId),
    });
</script>
