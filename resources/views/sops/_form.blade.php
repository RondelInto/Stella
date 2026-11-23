{{-- Shared SOP form. Variables: $roles, $categories, $sop (edit only). --}}
@php
    $sop = $sop ?? null;
    $selectedRoles = array_map('intval', old('roles', $sop?->roles->pluck('id')->all() ?? []));
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <x-input-label for="sop_code" :value="__('SOP Code')" />
        <x-text-input id="sop_code" name="sop_code" :value="old('sop_code', $sop?->sop_code)" :invalid="$errors->has('sop_code')"
                      required placeholder="SOP-INV-001" />
        <x-input-error :messages="$errors->get('sop_code')" />
    </div>
    <div class="col-md-9">
        <x-input-label for="title" :value="__('Title')" />
        <x-text-input id="title" name="title" :value="old('title', $sop?->title)" :invalid="$errors->has('title')" required
                      placeholder="{{ __('e.g. Receiving a stock transfer at the branch') }}" />
        <x-input-error :messages="$errors->get('title')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="category" :value="__('Category')" />
        <x-text-input id="category" name="category" :value="old('category', $sop?->category)" :invalid="$errors->has('category')"
                      required list="category-suggestions" placeholder="{{ __('e.g. Inventory') }}" />
        <datalist id="category-suggestions">
            @foreach ($categories as $category)
                <option value="{{ $category }}">
            @endforeach
        </datalist>
        <div class="form-text">{{ __('Type a new category or pick an existing one.') }}</div>
        <x-input-error :messages="$errors->get('category')" />
    </div>
    <div class="col-md-5">
        <x-input-label for="description" :value="__('Short Description (optional)')" />
        <x-text-input id="description" name="description" :value="old('description', $sop?->description)" :invalid="$errors->has('description')" />
        <x-input-error :messages="$errors->get('description')" />
    </div>
    <div class="col-md-3">
        <x-input-label for="effective_date" :value="__('Effective Date (optional)')" />
        <x-text-input id="effective_date" name="effective_date" type="date"
                      :value="old('effective_date', $sop?->effective_date?->toDateString())" :invalid="$errors->has('effective_date')" />
        <div class="form-text">{{ __('Staff see it from this date.') }}</div>
        <x-input-error :messages="$errors->get('effective_date')" />
    </div>
</div>

{{-- Procedure (Markdown) with preview --}}
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-end mb-1">
        <x-input-label for="content" :value="__('Procedure')" class="mb-0" />
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Write or preview') }}">
            <button type="button" class="btn btn-outline-secondary active" id="btn-write">{{ __('Write') }}</button>
            <button type="button" class="btn btn-outline-secondary" id="btn-preview">{{ __('Preview') }}</button>
        </div>
    </div>
    <textarea id="content" name="content" rows="16" class="form-control font-monospace @error('content') is-invalid @enderror"
              required>{{ old('content', $sop?->content) }}</textarea>
    <div id="preview" class="border rounded p-3 bg-white sop-content" hidden></div>
    <x-input-error :messages="$errors->get('content')" />

    <details class="small mt-2">
        <summary class="text-muted">{{ __('Formatting help') }}</summary>
        <table class="table table-sm mt-2 mb-0" style="max-width: 520px">
            <tr><td><code>## Heading</code></td><td>{{ __('Section heading') }}</td></tr>
            <tr><td><code>1. First step</code></td><td>{{ __('Numbered steps') }}</td></tr>
            <tr><td><code>- Item</code></td><td>{{ __('Bullet list') }}</td></tr>
            <tr><td><code>**important**</code></td><td>{{ __('Bold text') }}</td></tr>
            <tr><td>{{ __('Blank line') }}</td><td>{{ __('New paragraph') }}</td></tr>
        </table>
    </details>
</div>

<div class="row g-3">
    <div class="col-md-7">
        <span class="form-label d-block">{{ __('Who should follow this procedure?') }}</span>
        @foreach ($roles as $role)
            <div class="form-check">
                <input class="form-check-input @error('roles') is-invalid @enderror" type="checkbox" name="roles[]"
                       value="{{ $role->id }}" id="role_{{ $role->id }}" @checked(in_array($role->id, $selectedRoles, true))>
                <label class="form-check-label" for="role_{{ $role->id }}">{{ $role->name }}</label>
            </div>
        @endforeach
        <x-input-error :messages="$errors->get('roles')" />
    </div>

    @unless ($sop)
        <div class="col-md-5">
            <span class="form-label d-block">{{ __('Save as') }}</span>
            @foreach (['draft' => __('Draft — only Management can see it'), 'active' => __('Active — staff with these roles can see it')] as $value => $label)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="status" id="status_{{ $value }}" value="{{ $value }}"
                           @checked(old('status', 'draft') === $value)>
                    <label class="form-check-label" for="status_{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
            <x-input-error :messages="$errors->get('status')" />
        </div>
    @endunless
</div>

<script>
    // Write / Preview toggle. The preview is rendered by the server, exactly like the saved SOP.
    document.addEventListener('DOMContentLoaded', () => {
        const textarea = document.getElementById('content');
        const preview = document.getElementById('preview');
        const writeBtn = document.getElementById('btn-write');
        const previewBtn = document.getElementById('btn-preview');

        writeBtn.addEventListener('click', () => {
            textarea.hidden = false; preview.hidden = true;
            writeBtn.classList.add('active'); previewBtn.classList.remove('active');
        });

        previewBtn.addEventListener('click', async () => {
            preview.textContent = @js(__('Loading preview…'));
            textarea.hidden = true; preview.hidden = false;
            previewBtn.classList.add('active'); writeBtn.classList.remove('active');

            try {
                const response = await window.axios.post(@js(route('sops.preview')), { content: textarea.value });
                // Safe: the server strips raw HTML and unsafe links before returning it.
                preview.innerHTML = response.data.html || `<p class="text-muted">${@js(__('Nothing to preview yet.'))}</p>`;
            } catch {
                preview.textContent = @js(__('The preview could not be loaded.'));
            }
        });
    });
</script>
