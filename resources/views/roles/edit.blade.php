<x-app-layout>
    <x-slot name="title">{{ __('Edit Role') }}</x-slot>
    <x-slot name="header">{{ __('Edit Role') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $role->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('roles.update', $role) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    <div class="mb-3">
                        <x-input-label for="name" :value="__('Role Name')" />
                        <x-text-input id="name" name="name" :value="old('name', $role->name)"
                                      :invalid="$errors->has('name')" required />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div class="mb-3">
                        <x-input-label for="description" :value="__('Description')" />
                        <x-text-input id="description" name="description" :value="old('description', $role->description)"
                                      :invalid="$errors->has('description')" />
                        <x-input-error :messages="$errors->get('description')" />
                    </div>

                    <div class="mb-0">
                        <x-input-label for="responsibilities" :value="__('Responsibilities')" />
                        <textarea id="responsibilities" name="responsibilities" rows="6"
                                  class="form-control @error('responsibilities') is-invalid @enderror">{{ old('responsibilities', $role->responsibilities) }}</textarea>
                        <div class="form-text">{{ __('Shown to Management. One responsibility per line is easiest to read.') }}</div>
                        <x-input-error :messages="$errors->get('responsibilities')" />
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
