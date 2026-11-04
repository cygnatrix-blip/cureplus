<x-field name="name" label="Name" :value="$plan->name" required />
<div class="grid grid-cols-2 gap-2">
    <x-field name="price" label="Price (₹)" :value="$plan->price_paise !== null ? \App\Support\Money::decimal((int) $plan->price_paise) : null" required />
    <div>
        <label class="label" for="billing_interval_{{ $suffix }}">Every</label>
        <select class="input" id="billing_interval_{{ $suffix }}" name="billing_interval"><option value="month" @selected($plan->billing_interval === 'month')>Month</option><option value="year" @selected($plan->billing_interval === 'year')>Year</option></select>
    </div>
    <x-field name="trial_days" label="Trial days" type="number" :value="$plan->trial_days" required />
    <x-field name="razorpay_plan_id" label="Razorpay plan ID" :value="$plan->razorpay_plan_id" />
    <x-field name="limit_doctors" label="Doctors" type="number" :value="$plan->limits['doctors'] ?? null" hint="Blank is unlimited" />
    <x-field name="limit_staff" label="Team members" type="number" :value="$plan->limits['staff'] ?? null" />
    <x-field name="limit_locations" label="Locations" type="number" :value="$plan->limits['locations'] ?? null" />
    <x-field name="limit_messages" label="Messages a month" type="number" :value="$plan->limits['messages_per_month'] ?? null" />
</div>
<fieldset>
    <legend class="label">Features</legend>
    <div class="flex flex-wrap gap-2">
        @foreach (config('plans.features') as $key => $feature)
            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $plan->features ?? [], true) || in_array('*', $plan->features ?? [], true))> {{ $feature['label'] }}</label>
        @endforeach
    </div>
</fieldset>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> On sale</label>
