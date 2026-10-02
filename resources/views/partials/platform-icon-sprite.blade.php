{{-- One <symbol> per platform glyph, referenced by components/platform-icon's <use>. Kept
     out of the layout (not display:none, which breaks <use> references in some browsers). --}}
<svg aria-hidden="true" width="0" height="0" style="position: absolute; overflow: hidden">
    @foreach (\App\Support\PlatformIcons::ICONS as $platform => $icon)
        <symbol id="{{ \App\Support\PlatformIcons::symbolId($platform) }}" viewBox="0 0 24 24"><path d="{{ $icon['path'] }}" /></symbol>
    @endforeach
</svg>
