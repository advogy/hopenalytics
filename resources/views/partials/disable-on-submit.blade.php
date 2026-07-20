{{-- Disables a form's submit button(s) once it's submitted, to block double-submits
     while the request is in flight (e.g. forms that trigger an email send). --}}
<script>
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (! (form.hasAttribute && form.hasAttribute('data-disable-on-submit'))) return;
        if (form.hasAttribute('data-confirm') && ! form.dataset.confirmed) return;

        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = true;
        });
    });
</script>
