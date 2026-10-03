@props(['id', 'type' => 'bar', 'labels' => [], 'datasets' => [], 'options' => [], 'height' => 260])

{{--
    Backlog #8: reports get a chart alongside their existing table, not
    instead of it. A plain (non-Livewire) Blade component wrapping a Chart.js
    canvas — wire:ignore keeps Livewire's own morph from touching it once
    drawn, and keying the wrapper on the data itself makes Livewire replace
    the whole node (a fresh element, so x-init draws a fresh chart) whenever
    a report's filters change the numbers, instead of hand-rolling a
    Chart.update() call.
--}}
@php($key = substr(md5(json_encode([$labels, $datasets, $type])), 0, 12))

<div wire:ignore wire:key="chart-{{ $id }}-{{ $key }}" style="position:relative;height:{{ $height }}px;">
    <canvas
        x-data
        x-init="new Chart($el.getContext('2d'), {
            type: @js($type),
            data: { labels: @js($labels), datasets: @js($datasets) },
            options: Object.assign({ responsive: true, maintainAspectRatio: false }, @js($options)),
        })"
    ></canvas>
</div>
