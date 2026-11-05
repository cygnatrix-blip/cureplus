<x-layouts.admin title="Medicine catalogue">
    <x-page-header title="Medicine catalogue">
        <form method="GET"><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Search" aria-label="Search"></form>
        <a class="btn btn-secondary" href="{{ route('admin.medicines.requests') }}">Requests</a>
    </x-page-header>
    <div class="card">
        @if ($medicines->isEmpty())
            <x-empty title="No medicines found" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Generic</th><th>Brand</th><th>Strength</th><th>Form</th><th>Manufacturer</th><th>System</th><th>Department</th><th>Active</th></tr></thead>
                <tbody>
                    @foreach ($medicines as $medicine)
                        <tr><td class="font-medium">{{ $medicine->generic_name }}</td><td>{{ $medicine->brand_name }}</td><td>{{ $medicine->strength }}</td><td>{{ $medicine->form }}</td><td>{{ $medicine->manufacturer }}</td><td>{{ $medicine->system }}</td><td>{{ $medicine->specialty }}</td><td>{{ $medicine->is_active ? 'Yes' : 'No' }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="mt-4">{{ $medicines->links() }}</div>
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.medicines.store') }}" class="card card-body space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">Add a medicine</h3>
            <x-field name="generic_name" label="Generic name" required />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="brand_name" label="Brand" />
                <x-field name="strength" label="Strength" />
                <x-field name="form" label="Form" />
                <x-field name="manufacturer" label="Manufacturer" />
                <x-specialty-select label="Department" placeholder="Choose a department" />
            </div>
            <button class="btn btn-primary">Add</button>
        </form>
        <form method="POST" action="{{ route('admin.medicines.import') }}" enctype="multipart/form-data" class="card card-body space-y-3">
            @csrf
            <h3 class="font-semibold text-slate-900">Import from CSV</h3>
            <p class="text-sm text-slate-600">Columns: generic_name, brand_name, strength, form, manufacturer, specialty.</p>
            <input class="input" type="file" name="file" accept=".csv" required aria-label="CSV file">
            @error('file')<p class="field-error">{{ $message }}</p>@enderror
            <button class="btn btn-secondary">Import</button>
        </form>
    </div>
</x-layouts.admin>
