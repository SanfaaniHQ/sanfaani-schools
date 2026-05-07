<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">School Mail Settings</h2>
                <p class="mt-1 text-sm text-gray-500">Configure {{ $school->name }} outbound mail or fall back to platform mail.</p>
            </div>
            <a href="{{ route('school.dashboard') }}" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">← Back to Dashboard</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div class="lg:col-span-2">
                @if (session('success'))
                    <div class="mb-6 rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('school.mail-settings.update') }}" data-loading-text="Saving..." class="space-y-6 rounded-2xl bg-white p-6 shadow-sm">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mail Driver</label>
                            <select name="mailer" class="mt-1 block w-full rounded-xl border-gray-300">
                                <option value="log" @selected(old('mailer', $setting->mailer) === 'log')>Platform / Log fallback</option>
                                <option value="smtp" @selected(old('mailer', $setting->mailer) === 'smtp')>Custom SMTP</option>
                            </select>
                        </div>
                        <label class="mt-6 flex items-center gap-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-700">
                            <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $setting->is_enabled)) class="rounded border-gray-300">
                            Use this school's custom outbound mail settings
                        </label>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Host</label>
                            <input name="host" value="{{ old('host', $setting->host) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                            @error('host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Port</label>
                            <input name="port" type="number" value="{{ old('port', $setting->port) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                            @error('port') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Username</label>
                            <input name="username" value="{{ old('username', $setting->username) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">SMTP Password</label>
                            <input name="password" type="password" placeholder="{{ $masker->mask($setting->password) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                            <p class="mt-1 text-xs text-gray-500">Encrypted at rest. Leave blank to keep the current password.</p>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Encryption</label>
                            <select name="encryption" class="mt-1 block w-full rounded-xl border-gray-300">
                                <option value="">None</option>
                                <option value="tls" @selected(old('encryption', $setting->encryption) === 'tls')>TLS</option>
                                <option value="ssl" @selected(old('encryption', $setting->encryption) === 'ssl')>SSL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sender Email</label>
                            <input name="from_address" value="{{ old('from_address', $setting->from_address) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sender Name</label>
                            <input name="from_name" value="{{ old('from_name', $setting->from_name) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white">Save School Mail Settings</button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <form method="POST" action="{{ route('school.mail-settings.test') }}" data-loading-text="Sending..." class="rounded-2xl bg-white p-6 shadow-sm">
                    @csrf
                    <h3 class="text-base font-semibold text-gray-900">Connection Check</h3>
                    <p class="mt-1 text-sm text-gray-500">Sends a test email using this school's configuration.</p>
                    <label class="mt-4 block text-sm font-medium text-gray-700">Recipient</label>
                    <input name="test_email" value="{{ old('test_email', auth()->user()->email) }}" class="mt-1 block w-full rounded-xl border-gray-300">
                    <button class="mt-4 w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white">Send Test</button>
                </form>

                <div class="rounded-2xl bg-white p-6 text-sm text-gray-600 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Secure Fallback</h3>
                    <p class="mt-2">When disabled or unavailable, school communication can fall back to platform mail without exposing SMTP passwords in the UI.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
