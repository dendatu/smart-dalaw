@extends('layouts.admin')

@section('title', 'Daily Visitation Summary | SmartDalaw')

@section('content')
    <div class="max-w-5xl">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Dashboard</a>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-[#0A2472]">Reports</p>
                <h1 class="mt-1 text-2xl font-bold text-[#102044]">Daily Visitation Summary</h1>
                <p class="mt-2 text-sm text-slate-600">View visitor statistics grouped by sex, age category, and priority status (PWD / pregnant).</p>
            </div>
            <div class="flex flex-col gap-2 sm:items-end">
                <form action="{{ route('admin.daily-summary') }}" method="GET" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="from" class="mb-1 block text-xs font-semibold text-slate-600">From</label>
                        <input type="date" id="from" name="from" value="{{ $summary['from'] }}" required class="min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="to" class="mb-1 block text-xs font-semibold text-slate-600">To</label>
                        <input type="date" id="to" name="to" value="{{ $summary['to'] }}" min="{{ $summary['from'] }}" required class="min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <button type="submit" class="min-h-10 rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Generate Report</button>
                </form>
                <div class="flex flex-wrap gap-3 text-xs font-semibold text-[#0A2472]">
                    <a href="{{ route('admin.daily-summary', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="hover:underline">Today</a>
                    <a href="{{ route('admin.daily-summary', ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()]) }}" class="hover:underline">Last 7 days</a>
                    <a href="{{ route('admin.daily-summary', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->endOfMonth()->toDateString()]) }}" class="hover:underline">This month</a>
                    <a href="{{ route('admin.daily-summary', ['from' => today()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => today()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}" class="hover:underline">Last month</a>
                </div>
            </div>
        </div>

        @php
            $isRange = $summary['from'] !== $summary['to'];
            $rangeLabel = \Carbon\Carbon::parse($summary['from'])->format('F d, Y').($isRange ? ' – '.\Carbon\Carbon::parse($summary['to'])->format('F d, Y') : '');
        @endphp

        @if($errors->any())
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <p class="mt-4 text-sm font-semibold text-slate-700">Report period: {{ $rangeLabel }}</p>

        <section class="mt-4" aria-label="Overall statistics">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Total Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $summary['total_visitors'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">All approved visitors</p>
                </article>
                <article class="rounded-md border border-blue-200 bg-white p-4">
                    <p class="text-xs font-medium text-blue-800">Male Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-blue-900">{{ $summary['by_sex']['Male'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_sex']['Male'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-pink-200 bg-white p-4">
                    <p class="text-xs font-medium text-pink-800">Female Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-pink-900">{{ $summary['by_sex']['Female'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_sex']['Female'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Approved Bookings</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $bookings->count() }}</p>
                    <p class="mt-1 text-xs text-slate-500">Including accompanying visitors</p>
                </article>
            </div>
        </section>

        <section class="mt-6" aria-label="Age distribution">
            <h2 class="text-lg font-bold text-[#102044]">Age Distribution</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <article class="rounded-md border border-emerald-200 bg-white p-4">
                    <p class="text-xs font-medium text-emerald-800">Children (Below 18)</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-900">{{ $summary['by_age']['child'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['child'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Adults (18-59)</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $summary['by_age']['adult'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['adult'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-amber-200 bg-white p-4">
                    <p class="text-xs font-medium text-amber-800">Senior Citizens (60+)</p>
                    <p class="mt-2 text-2xl font-bold text-amber-900">{{ $summary['by_age']['senior'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['senior'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
            </div>
        </section>

        <section class="mt-6" aria-label="Priority visitors">
            <h2 class="text-lg font-bold text-[#102044]">Priority Visitors</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <article class="rounded-md border border-indigo-200 bg-white p-4">
                    <p class="text-xs font-medium text-indigo-800">Persons with Disability (PWD)</p>
                    <p class="mt-2 text-2xl font-bold text-indigo-900">{{ $summary['pwd'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['pwd'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-rose-200 bg-white p-4">
                    <p class="text-xs font-medium text-rose-800">Pregnant Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-rose-900">{{ $summary['pregnant'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['pregnant'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Total Priority Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $summary['priority'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">PWD and/or pregnant</p>
                </article>
            </div>
        </section>

        <section class="mt-6" aria-label="Sex and age breakdown">
            <h2 class="text-lg font-bold text-[#102044]">Breakdown by Sex and Age</h2>
            <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sex</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Children<br><span class="font-normal">&lt;18</span></th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Adults<br><span class="font-normal">18-59</span></th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Seniors<br><span class="font-normal">60+</span></th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">Male</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['child'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['adult'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ $summary['by_sex']['Male'] }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">Female</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['child'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['adult'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ $summary['by_sex']['Female'] }}</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="px-4 py-4 font-semibold text-slate-900">Total</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['child'] }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['adult'] }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-bold text-[#0A2472]">{{ $summary['total_visitors'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if($isRange && count($summary['by_date']) > 0)
            <section class="mt-6" aria-label="Breakdown by date">
                <h2 class="text-lg font-bold text-[#102044]">Breakdown by Date</h2>
                <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Date</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Bookings</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Male</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Female</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">PWD</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Pregnant</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($summary['by_date'] as $visitDate => $day)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ \Carbon\Carbon::parse($visitDate)->format('D, M d, Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-slate-700">{{ $day['bookings'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-slate-700">{{ $day['Male'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-slate-700">{{ $day['Female'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-slate-700">{{ $day['pwd'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-sm text-slate-700">{{ $day['pregnant'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-slate-900">{{ $day['visitors'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50">
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">Total</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-slate-900">{{ $bookings->count() }}</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-slate-900">{{ $summary['by_sex']['Male'] }}</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-slate-900">{{ $summary['by_sex']['Female'] }}</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-slate-900">{{ $summary['pwd'] }}</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-slate-900">{{ $summary['pregnant'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-[#0A2472]">{{ $summary['total_visitors'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($bookings->count() > 0)
            <section class="mt-6" aria-label="Booking details">
                <h2 class="text-lg font-bold text-[#102044]">Approved Bookings for {{ $rangeLabel }}</h2>
                <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Reference</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Visitor</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">PDL</th>
                                @if($isRange)
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Date</th>
                                @endif
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Time Slot</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Accompanying</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">PWD / Pregnant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($bookings as $booking)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-4 font-mono text-xs font-semibold text-[#0A2472]">{{ $booking->reference_code }}</td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <p class="text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $booking->visitor_age }} yrs · {{ $booking->visitor_sex }}</p>
                                        <div class="mt-1 flex gap-1">@include('bookings.partials.priority-badges', ['isPwd' => $booking->visitor_is_pwd, 'isPregnant' => $booking->visitor_is_pregnant])</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->pdl_name }}</td>
                                    @if($isRange)
                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->visit_date->format('M d, Y') }}</td>
                                    @endif
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->time_slot }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center text-sm text-slate-700">{{ $booking->accompanyingVisitors->count() }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center text-sm text-slate-700">
                                        {{ (int) $booking->visitor_is_pwd + $booking->accompanyingVisitors->where('is_pwd', true)->count() }}
                                        /
                                        {{ (int) $booking->visitor_is_pregnant + $booking->accompanyingVisitors->where('is_pregnant', true)->count() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="mt-6 rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-sm text-slate-500">No approved bookings found for {{ $isRange ? 'this date range' : 'this date' }}.</p>
            </div>
        @endif
    </div>
@endsection
