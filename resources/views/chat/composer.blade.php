{{-- The send form. A plain multipart POST, so it works with no JavaScript and on any phone. --}}
@if ($open)
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-2 border-t border-slate-200 p-4" data-chat-form>
        @csrf
        <label class="sr-only" for="chat-body">{{ __('Message') }}</label>
        <textarea id="chat-body" name="body" rows="2" maxlength="{{ \App\Models\AppointmentMessage::MAX_BODY }}" class="input" placeholder="{{ __('Write a message') }}" @error('body') aria-invalid="true" aria-describedby="chat-body-error" @enderror>{{ old('body') }}</textarea>
        @error('body')<p id="chat-body-error" class="field-error">{{ $message }}</p>@enderror
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <label class="label sr-only" for="chat-file">{{ __('Attach a file') }}</label>
                <input id="chat-file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="text-sm" @error('file') aria-invalid="true" aria-describedby="chat-file-error" @enderror>
                <p class="hint">{{ __('PDF, JPG or PNG, up to 10 MB.') }}</p>
                @error('file')<p id="chat-file-error" class="field-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary"><x-icon name="arrow" />{{ __('Send') }}</button>
        </div>
    </form>
@else
    <p class="border-t border-slate-200 bg-slate-50 p-4 text-center text-sm text-slate-600" role="status">{{ __('This chat is closed. You can still read it.') }}</p>
@endif
