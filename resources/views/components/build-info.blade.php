@php
    $commit = config('filmlog.commit');
    $commitDate = config('filmlog.commit_date');
@endphp

@if ($commit)
    <span {{ $attributes->class('text-xs font-normal tabular-nums text-stone-500') }}>
        {{ substr($commit, 0, 7) }}@if ($commitDate) · {{ \Illuminate\Support\Carbon::parse($commitDate)->timezone(config('app.timezone'))->format('d.m.Y') }}@endif
    </span>
@endif
