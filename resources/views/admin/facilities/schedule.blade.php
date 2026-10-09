@extends('layouts.admin')

@section('title', 'Visitation Schedule | SmartDalaw')

@section('content')
    <div class="max-w-4xl">
        <a href="{{ route('admin.facilities.settings') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Facility settings</a>
        <div class="mt-3">
            <p class="text-xs font-semibold uppercase text-[#0A2472]">Visitation schedule</p>
            <h1 class="mt-1 text-2xl font-bold text-[#102044]">{{ $facility->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Configure which days and hours are open for visitation. Multiple periods per day are supported. Days without any period are treated as unavailable for public bookings.</p>
        </div>

        <form action="{{ route('admin.facilities.schedule.update', $facility) }}" method="POST" class="mt-7 space-y-4">
            @csrf
            @method('PUT')

            @foreach($scheduleByDay as $day => $periods)
                <section class="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="schedule-{{ Str::lower($day) }}-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 id="schedule-{{ Str::lower($day) }}-heading" class="text-sm font-semibold text-slate-900">{{ $day }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">No periods means no visitation on {{ $day }}.</p>
                        </div>
                        <button type="button" data-add-period="{{ $day }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">+ Add period</button>
                    </div>

                    <div class="mt-4 space-y-3" data-day-periods="{{ $day }}">
                        @forelse($periods as $index => $period)
                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]" data-period-row>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">Start time</label>
                                    <input type="time" name="schedules[{{ $day }}][{{ $index }}][start_time]" value="{{ old('schedules.'.$day.'.'.$index.'.start_time', \App\Models\Facility::normalizeTime($period->start_time)) }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-700">End time</label>
                                    <input type="time" name="schedules[{{ $day }}][{{ $index }}][end_time]" value="{{ old('schedules.'.$day.'.'.$index.'.end_time', \App\Models\Facility::normalizeTime($period->end_time)) }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <button type="button" data-remove-period class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Remove</button>
                            </div>
                        @empty
                            <p data-empty-day class="text-sm text-slate-500">Unavailable &mdash; add a period to open visitation.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.facilities.settings') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Save schedule</button>
            </div>
        </form>
    </div>

    <script>
        function periodRowMarkup(day, index) {
            return `
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]" data-period-row>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Start time</label>
                        <input type="time" name="schedules[${day}][${index}][start_time]" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">End time</label>
                        <input type="time" name="schedules[${day}][${index}][end_time]" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <button type="button" data-remove-period class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Remove</button>
                </div>
            `;
        }

        document.querySelectorAll('[data-add-period]').forEach((button) => {
            button.addEventListener('click', () => {
                const day = button.dataset.addPeriod;
                const container = document.querySelector(`[data-day-periods="${day}"]`);

                container.querySelector('[data-empty-day]')?.remove();

                const index = container.querySelectorAll('[data-period-row]').length;
                const wrapper = document.createElement('template');

                wrapper.innerHTML = periodRowMarkup(day, index);
                container.appendChild(wrapper.content.cloneNode(true));
            });
        });

        document.querySelectorAll('[data-day-periods]').forEach((container) => {
            container.addEventListener('click', (event) => {
                if (event.target.closest('[data-remove-period]')) {
                    event.target.closest('[data-period-row]').remove();
                }
            });
        });
    </script>
@endsection