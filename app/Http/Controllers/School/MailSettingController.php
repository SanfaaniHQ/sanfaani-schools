<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\AuditLogService;
use App\Services\CurrentSchoolService;
use App\Services\MailSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MailSettingController extends Controller
{
    public function edit(MailSettingService $mailSettings)
    {
        $school = $this->currentSchoolOrFail();

        return view('school.mail-settings.edit', [
            'school' => $school,
            'setting' => $mailSettings->forSchool($school),
            'masker' => $mailSettings,
        ]);
    }

    public function update(Request $request, MailSettingService $mailSettings, AuditLogService $auditLog)
    {
        $school = $this->currentSchoolOrFail();
        $setting = $mailSettings->forSchool($school);
        $data = $this->validatedSettings($request);

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);
        $data['metadata'] = ['fallback' => $data['is_enabled'] ? 'school_smtp' : 'platform'];
        $setting->update($data);

        $auditLog->log('school_mail_settings_updated', $setting, $school, metadata: [
            'mailer' => $setting->mailer,
            'is_enabled' => $setting->is_enabled,
        ], request: $request);

        return back()->with('success', 'School mail settings saved successfully.');
    }

    public function test(Request $request, MailSettingService $mailSettings, AuditLogService $auditLog)
    {
        $school = $this->currentSchoolOrFail();
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $setting = $mailSettings->forSchool($school);

        try {
            $mailSettings->sendTest($setting, $data['test_email']);
        } catch (\Throwable $exception) {
            Log::warning('School mail settings test failed.', [
                'school_id' => $school->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'School mail test could not be sent. Check the SMTP settings or use platform mail fallback.');
        }

        $auditLog->log('school_mail_settings_test_sent', $setting, $school, metadata: [
            'mailer' => $setting->mailer,
            'recipient' => $data['test_email'],
        ], request: $request);

        return back()->with('success', 'Test email sent successfully.');
    }

    private function validatedSettings(Request $request): array
    {
        return $request->validate([
            'mailer' => ['required', Rule::in(['log', 'smtp'])],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2000'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function currentSchoolOrFail(): School
    {
        $school = app(CurrentSchoolService::class)->get();

        if (! $school) {
            abort(403, 'Your account is not assigned to a school.');
        }

        return $school;
    }
}
