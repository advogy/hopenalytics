{{-- Wires up [data-password-toggle] buttons rendered by x-form-field for type="password" —
     flips the sibling input between password/text and swaps the eye / eye-slash icon. --}}
<script>
    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-password-toggle]');
        if (! button) return;

        var field = button.closest('[data-password-field]');
        var input = field.querySelector('input');
        var showIcon = field.querySelector('[data-password-toggle-icon-show]');
        var hideIcon = field.querySelector('[data-password-toggle-icon-hide]');
        var revealed = input.type === 'text';

        input.type = revealed ? 'password' : 'text';
        showIcon.classList.toggle('hidden', ! revealed);
        hideIcon.classList.toggle('hidden', revealed);
    });
</script>
