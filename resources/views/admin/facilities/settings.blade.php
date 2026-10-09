@extends('layouts.admin')

@section('title', 'Facility Settings | SmartDalaw')

@section('content')
    <div>
        <p class="text-xs font-semibold uppercase text-[#0A2472]">Visitation capacity</p>
        <h1 class="mt-1 text-2xl font-bold text-[#102044]">Facility settings</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Set the maximum number of visitors each facility can accept in one one-hour time slot. The limit applies separately to each visit date and hour.</p>
    </div>

    <div class="mt-7 divide-y divide-slate-200 border-y border-slate-200 bg-white">
        @forelse($facilities as $facility)
            <article class="grid gap-6 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(14rem,0.8fr)_minmax(18rem,1fr)_minmax(18rem,1fr)]">
                <div class="flex min-w-0 items-start gap-3">
                    @if($facility->badge_url)
                        <img src="{{ $facility->badge_url }}" alt="Official badge for {{ $facility->name }}" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1" onerror="this.src='{{ asset('images/bjmp-logo.png') }}'; this.alt='BJMP Official Seal';">
                    @else
                        <img src="{{ asset('images/bjmp-logo.png') }}" alt="BJMP Official Seal" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                    @endif
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-900">{{ $facility->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $facility->regional_office ?: 'Regional office not set' }}</p>
                        <p class="text-xs text-slate-500">{{ $facility->jail_unit_name ?: 'Jail unit not set' }}</p>
                        <a href="{{ route('admin.facilities.schedule.edit', $facility) }}" class="mt-2 inline-block text-sm font-semibold text-[#0A2472] hover:underline">Manage visitation schedule &rarr;</a>
                    </div>
                </div>

                <form action="{{ route('admin.facilities.capacity.update', $facility) }}" method="POST" class="flex items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div class="min-w-0 flex-1">
                        <label for="facility-limit-{{ $facility->id }}" class="mb-1.5 block text-xs font-semibold text-slate-700">Visitors per hourly slot</label>
                        <input type="number" id="facility-limit-{{ $facility->id }}" name="max_visitors_per_hour" value="{{ old('max_visitors_per_hour', $facility->max_visitors_per_hour) }}" min="1" max="10000" required class="input input-bordered w-full bg-base-100">
                        <p class="mt-1 text-xs text-slate-500">Current limit: {{ $facility->max_visitors_per_hour }} per hour</p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </form>

                <form action="{{ route('admin.facilities.branding.update', $facility) }}" method="POST" enctype="multipart/form-data" class="space-y-3 border-t border-base-300 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="regional-office-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Regional office (official name)</label>
                        <input type="text" id="regional-office-{{ $facility->id }}" name="regional_office" value="{{ old('regional_office', $facility->regional_office) }}" maxlength="150" placeholder="e.g. BJMP Regional Office No. III" class="input input-bordered input-sm w-full bg-base-100">
                    </div>
                    <div>
                        <label for="jail-unit-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Jail unit (official name)</label>
                        <input type="text" id="jail-unit-{{ $facility->id }}" name="jail_unit_name" value="{{ old('jail_unit_name', $facility->jail_unit_name) }}" maxlength="150" placeholder="Use the unit's approved designation" class="input input-bordered input-sm w-full bg-base-100">
                    </div>
                    <div>
                        <label for="badge-image-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Official facility badge</label>
                        <input type="file" id="badge-image-{{ $facility->id }}" name="badge_image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered file-input-sm w-full bg-base-100">
                        <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP. Maximum 2 MB. Upload the approved badge containing official insignia only.</p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save facility identity</button>
                </form>
            </article>
        @empty
            <p class="px-4 py-10 text-center text-sm text-slate-500">No facilities have been configured.</p>
        @endforelse
    </div>
@endsection