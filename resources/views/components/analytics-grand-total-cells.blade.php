{{-- The 4 numeric Reach/Views/Likes/Posts <td> cells of a churches/analytics.blade.php Grand Total row. --}}
@props(['reach', 'views', 'likes', 'posts'])

<td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($reach) }}</td>
<td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $views ? number_format($views) : '—' }}</td>
<td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $likes ? number_format($likes) : '—' }}</td>
<td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $posts ? number_format($posts) : '—' }}</td>
