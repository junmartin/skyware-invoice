@csrf
<div class="mb-3"><label class="form-label">Code</label><input class="form-control" name="code" value="{{ old('code', $client->code ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $client->name ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $client->email ?? '') }}" required></div>
<div class="mb-3"><label class="form-label">Currency</label><input class="form-control" name="currency" value="{{ old('currency', $client->currency ?? 'IDR') }}" required></div>
<div class="mb-3"><label class="form-label">Default Due Days</label><input class="form-control" type="number" name="default_due_days" value="{{ old('default_due_days', $client->default_due_days ?? 14) }}" required></div>
<div class="mb-3"><label class="form-label">Plan Name</label><input class="form-control" name="plan_name" value="{{ old('plan_name', $client->plan_name ?? '') }}"></div>
<div class="mb-3"><label class="form-label">Usage XLSX Path</label><input class="form-control" name="usage_xlsx_path" value="{{ old('usage_xlsx_path', $client->usage_xlsx_path ?? '') }}"></div>
<div class="mb-3"><label class="form-label">Billing Address</label><textarea class="form-control" name="billing_address">{{ old('billing_address', $client->billing_address ?? '') }}</textarea></div>
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" @if(old('is_active', $client->is_active ?? true)) checked @endif><label class="form-check-label">Active</label></div>
<button class="btn btn-primary">Save</button>
