{{-- No <html>/<head>/<nav> shell at all — used instead of layouts.app when a page is rendered
     for injection into an already-loaded page's own modal (see components/modal.blade.php and
     admin/accounts/index.blade.php's edit-modal JS), where wrapping the fragment in a second
     full HTML document would be both broken and pointless. --}}
@yield('content')
