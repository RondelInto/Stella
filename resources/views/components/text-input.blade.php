{{-- Pass :invalid="$errors->has('field')" to show the red Bootstrap validation border. --}}
@props(['disabled' => false, 'invalid' => false])

<input @disabled($disabled) {{ $attributes->class(['form-control', 'is-invalid' => $invalid]) }}>
