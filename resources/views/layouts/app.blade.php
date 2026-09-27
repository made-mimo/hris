{{-- Passthrough: every page SFC renders its own full document via
     <x-layouts.app>/<x-layouts.guest> (see resources/views/components/layouts/).
     This view only exists to satisfy Livewire's component_layout config. --}}
{{ $slot }}
