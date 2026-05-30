<div class="space-y-2 w-full">
    <label for="author_credit_user_id" class="block text-sm font-medium text-gray-700">
        Credit des Autors <span class="text-xs text-gray-400">(Benutzer / Byline)</span>
    </label>
    @if ($storedCreditUnmatched)
        <p class="text-xs text-amber-700">
            Der gespeicherte Credit „{{ $storedCreditText }}“ entspricht keinem Benutzernamen.
            Der angemeldete Benutzer ist vorausgewählt – bitte bei Bedarf anpassen.
        </p>
    @endif
    <select
        name="author_credit_user_id"
        id="author_credit_user_id"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-[#092E48] focus:ring-[#092E48]"
        required
    >
        @foreach ($creditUsers as $u)
            <option value="{{ $u->id }}" @selected((int) $selectedUserId === (int) $u->id)>
                {{ $u->name }}
            </option>
        @endforeach
    </select>
    @error('author_credit_user_id')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
