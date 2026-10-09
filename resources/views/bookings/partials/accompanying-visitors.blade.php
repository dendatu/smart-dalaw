<section class="mt-5 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="accompanying-visitors-heading">
    <h2 id="accompanying-visitors-heading" class="text-sm font-semibold text-slate-900">Accompanying Visitors</h2>

    @if($booking->accompanyingVisitors->isNotEmpty())
        <ol class="mt-3 space-y-3">
            @foreach($booking->accompanyingVisitors as $index => $accompanyingVisitor)
                <li class="rounded-md border border-slate-200 bg-white px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">{{ $index + 1 }}. {{ $accompanyingVisitor->full_name }}</p>
                    <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500">Sex</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $accompanyingVisitor->sex ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Age</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $accompanyingVisitor->age ?: 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Valid ID</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $accompanyingVisitor->id_type ?: 'N/A' }} {{ $accompanyingVisitor->id_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Relationship to PDL</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $accompanyingVisitor->relationship_to_pdl ?: 'N/A' }}</dd>
                        </div>
                    </dl>
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-2 text-sm text-slate-500">No accompanying visitors.</p>
    @endif

    <div class="mt-4 border-t border-slate-100 pt-4">
        <h3 class="text-xs font-medium uppercase tracking-wide text-slate-500">Visitor Count</h3>
        <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-slate-500">Primary visitor</dt>
                <dd class="mt-0.5 text-slate-900">1</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Accompanying visitors</dt>
                <dd class="mt-0.5 text-slate-900">{{ $booking->accompanyingVisitors->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Total expected visitors</dt>
                <dd class="mt-0.5 text-slate-900">{{ $booking->accompanyingVisitors->count() + 1 }}</dd>
            </div>
        </dl>
    </div>
</section>