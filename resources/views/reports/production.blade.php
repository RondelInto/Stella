<x-app-layout>
    <x-slot name="title">{{ __('Production Report') }}</x-slot>
    <x-slot name="header">{{ __('Production Report') }}</x-slot>

    @include('reports._header', ['title' => __('Production Report'), 'route' => 'reports.production'])

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <h2 class="h6 text-uppercase text-muted small">{{ __('By Product') }}</h2>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th class="text-center">{{ __('Batches') }}</th>
                                <th class="text-end">{{ __('Quantity Made') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $p)
                                <tr>
                                    <td class="fw-semibold">{{ $p->name }}</td>
                                    <td class="text-center">{{ $p->batches }}</td>
                                    <td class="text-end"><x-qty :value="$p->total" :unit="$p->unit" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">{{ __('No production in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <h2 class="h6 text-uppercase text-muted small">{{ __('Ingredients Used') }}</h2>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Ingredient') }}</th>
                                <th class="text-end">{{ __('Total Used') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ingredients as $i)
                                <tr>
                                    <td>{{ $i->name }} <span class="small text-muted"><code>{{ $i->item_code }}</code></span></td>
                                    <td class="text-end"><x-qty :value="$i->total" :unit="$i->unit" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">{{ __('No ingredients recorded in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12">
            <h2 class="h6 text-uppercase text-muted small">{{ __('Daily Output') }}</h2>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Product') }}</th>
                                <th class="text-center">{{ __('Batches') }}</th>
                                <th class="text-end">{{ __('Quantity Made') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($daily as $d)
                                <tr>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($d->date)->format('D, M d, Y') }}</td>
                                    <td>{{ $d->name }}</td>
                                    <td class="text-center">{{ $d->batches }}</td>
                                    <td class="text-end"><x-qty :value="$d->total" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No production in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
