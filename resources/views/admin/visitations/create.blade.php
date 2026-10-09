@extends('layouts.admin')

@section('title', 'Record On-Site Visitation | SmartDalaw')

@section('content')
    <div class="max-w-3xl">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Booking dashboard</a>
        <div class="mt-3">
            <p class="text-xs font-semibold uppercase text-[#0A2472]">On-site registration</p>
            <h1 class="mt-1 text-2xl font-bold text-[#102044]">Record on-site visitation</h1>
            <p class="mt-2 text-sm text-slate-600">Record a visitation that was completed at the facility outside of the online booking process.</p>
        </div>

        <form action="{{ route('admin.visitations.store') }}" method="POST" class="mt-7 space-y-5 border-y border-slate-200 bg-white py-6" id="onsiteForm">
            @csrf

            <div>
                <label for="facility_id" class="mb-2 block text-sm font-semibold text-slate-800">Facility</label>
                <select id="facility_id" name="facility_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @foreach($facilities as $availableFacility)
                        <option value="{{ $availableFacility->id }}" @selected(old('facility_id', $facility?->id) == $availableFacility->id)>{{ $availableFacility->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="pdl_name" class="mb-2 block text-sm font-semibold text-slate-800">Person deprived of liberty (PDL) Name</label>
                <input type="text" id="pdl_name" name="pdl_name" value="{{ old('pdl_name') }}" required list="pdl-suggestions" autocomplete="off" placeholder="Enter PDL's full name" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <datalist id="pdl-suggestions">
                    @foreach($pdls as $pdl)
                        <option value="{{ $pdl->first_name }} {{ $pdl->last_name }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visit_date" class="mb-2 block text-sm font-semibold text-slate-800">Visit date</label>
                    <input type="date" id="visit_date" name="visit_date" value="{{ old('visit_date') }}" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="time_slot" class="mb-2 block text-sm font-semibold text-slate-800">Time slot</label>
                    <select id="time_slot" name="time_slot" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select a time slot</option>
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">Suggested slots follow the facility's schedule. A different time can be selected for already-completed visitations.</p>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="visitor_full_name" class="mb-2 block text-sm font-semibold text-slate-800">Primary visitor full name</label>
                    <input type="text" id="visitor_full_name" name="visitor_full_name" value="{{ old('visitor_full_name') }}" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_sex" class="mb-2 block text-sm font-semibold text-slate-800">Sex</label>
                    <select id="visitor_sex" name="visitor_sex" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select sex</option>
                        <option value="Male" @selected(old('visitor_sex') === 'Male')>Male</option>
                        <option value="Female" @selected(old('visitor_sex') === 'Female')>Female</option>
                    </select>
                </div>
                <div>
                    <label for="visitor_age" class="mb-2 block text-sm font-semibold text-slate-800">Age</label>
                    <input type="number" id="visitor_age" name="visitor_age" value="{{ old('visitor_age') }}" required min="1" max="120" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            <div>
                    <label for="visitor_contact" class="mb-2 block text-sm font-semibold text-slate-800">Contact number</label>
                    <input type="tel" id="visitor_contact" name="visitor_contact" value="{{ old('visitor_contact') }}" required maxlength="20" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_email" class="mb-2 block text-sm font-semibold text-slate-800">Email <span class="font-normal text-slate-500">(optional)</span></label>
                    <input type="email" id="visitor_email" name="visitor_email" value="{{ old('visitor_email') }}" maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_id_type" class="mb-2 block text-sm font-semibold text-slate-800">Type of valid ID</label>
                    <select id="visitor_id_type" name="visitor_id_type" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select ID type</option>
                        <option value="PhilSys National ID" @selected(old('visitor_id_type') === 'PhilSys National ID')>PhilSys National ID</option>
                        <option value="Driver's License" @selected(old('visitor_id_type') === "Driver's License")>Driver's License</option>
                        <option value="Passport" @selected(old('visitor_id_type') === 'Passport')>Passport</option>
                        <option value="UMID" @selected(old('visitor_id_type') === 'UMID')>UMID</option>
                        <option value="Postal ID" @selected(old('visitor_id_type') === 'Postal ID')>Postal ID</option>
                        <option value="Voter's ID" @selected(old('visitor_id_type') === "Voter's ID")>Voter's ID</option>
                        <option value="SSS ID" @selected(old('visitor_id_type') === 'SSS ID')>SSS ID</option>
                        <option value="GSIS ID" @selected(old('visitor_id_type') === 'GSIS ID')>GSIS ID</option>
                        <option value="PhilHealth ID" @selected(old('visitor_id_type') === 'PhilHealth ID')>PhilHealth ID</option>
                        <option value="Senior Citizen ID" @selected(old('visitor_id_type') === 'Senior Citizen ID')>Senior Citizen ID</option>
                        <option value="Others" @selected(old('visitor_id_type') === 'Others')>Others</option>
                    </select>
                </div>
                <div>
                    <label for="visitor_id_number" class="mb-2 block text-sm font-semibold text-slate-800">ID number</label>
                    <input type="text" id="visitor_id_number" name="visitor_id_number" value="{{ old('visitor_id_number') }}" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div class="sm:col-span-2">
                    <label for="relationship_to_pdl" class="mb-2 block text-sm font-semibold text-slate-800">Relationship to PDL</label>
                    <input type="text" id="relationship_to_pdl" name="relationship_to_pdl" value="{{ old('relationship_to_pdl') }}" required maxlength="100" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <section aria-labelledby="accompanying-heading">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="accompanying-heading" class="text-sm font-semibold text-slate-900">Accompanying visitors</h2>
                        <p class="mt-1 text-xs text-slate-500">Additional visitors who attended with the primary visitor.</p>
                    </div>
                    <button type="button" id="add-accompanying-visitor" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add visitor</button>
                </div>
                <div id="accompanying-visitors-container" class="mt-4 space-y-4"></div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Record visitation</button>
            </div>
        </form>
    </div>

    <script>
        const onsiteFacilitySelect = document.getElementById('facility_id');
        const onsiteDateInput = document.getElementById('visit_date');
        const onsiteTimeSlotSelect = document.getElementById('time_slot');
        const onsiteVisitorContainer = document.getElementById('accompanying-visitors-container');
        const onsiteAddVisitorBtn = document.getElementById('add-accompanying-visitor');
        let onsiteVisitorCount = 0;

        async function loadOnSiteTimeSlots() {
            if (!onsiteFacilitySelect.value || !onsiteDateInput.value) {
                return;
            }

            try {
                const url = '{{ route('api.facility-slots', ':facility') }}'.replace(':facility', onsiteFacilitySelect.value);
                const response = await fetch(url + '?date=' + encodeURIComponent(onsiteDateInput.value));
                const data = await response.json();
                const currentValue = onsiteTimeSlotSelect.value;

                onsiteTimeSlotSelect.innerHTML = '<option value="">Select a time slot</option>';

                data.slots.forEach((slot) => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot;
                    onsiteTimeSlotSelect.appendChild(option);
                });

                const stillAvailable = Array.from(onsiteTimeSlotSelect.options).some((option) => option.value === currentValue);

                if (stillAvailable) {
                    onsiteTimeSlotSelect.value = currentValue;
                }
            } catch (error) {
                // Keep any existing selection when the availability API is unavailable.
            }
        }

        onsiteFacilitySelect.addEventListener('change', loadOnSiteTimeSlots);
        onsiteDateInput.addEventListener('change', loadOnSiteTimeSlots);

        function createOnSiteVisitorForm(index) {
            const div = document.createElement('div');
            div.className = 'rounded-lg border border-slate-200 bg-slate-50 p-4';
            div.dataset.accompanyingIndex = index;
            div.innerHTML = `
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-800">Visitor ${index + 1}</h4>
                    <button type="button" class="remove-accompanying-visitor text-sm text-red-600 hover:text-red-800" data-index="${index}">Remove</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
<div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Full name <span class="text-red-600">*</span></label>
                        <input type="text" name="accompanying_visitors[${index}][full_name]" required placeholder="Full name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Age <span class="text-red-600">*</span></label>
                        <input type="number" name="accompanying_visitors[${index}][age]" required min="1" max="120" placeholder="25" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Sex <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][sex]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Type of valid ID <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][id_type]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select ID type</option>
                            <option value="PhilSys National ID">PhilSys National ID</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="Passport">Passport</option>
                            <option value="UMID">UMID</option>
                            <option value="Postal ID">Postal ID</option>
                            <option value="Voter's ID">Voter's ID</option>
                            <option value="SSS ID">SSS ID</option>
                            <option value="GSIS ID">GSIS ID</option>
                            <option value="PhilHealth ID">PhilHealth ID</option>
                            <option value="Senior Citizen ID">Senior Citizen ID</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID number <span class="text-red-600">*</span></label>
                        <input type="text" name="accompanying_visitors[${index}][id_number]" required placeholder="XXXXX-XXXXX" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Relationship to PDL <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][relationship_to_pdl]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select relationship</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Parent">Parent</option>
                            <option value="Child">Child</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Relative">Relative</option>
                            <option value="Friend">Friend</option>
                            <option value="Legal Counsel">Legal Counsel</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>
            `;
            return div;
        }

        onsiteAddVisitorBtn.addEventListener('click', () => {
            const form = createOnSiteVisitorForm(onsiteVisitorCount);
            onsiteVisitorContainer.appendChild(form);
            onsiteVisitorCount++;
        });

        onsiteVisitorContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-accompanying-visitor')) {
                const index = e.target.dataset.index;
                const form = onsiteVisitorContainer.querySelector(`[data-accompanying-index="${index}"]`);
                if (form) {
                    form.remove();
                }
            }
        });

        loadOnSiteTimeSlots();
    </script>
@endsection