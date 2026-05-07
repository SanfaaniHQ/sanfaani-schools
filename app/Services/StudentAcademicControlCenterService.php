<?php

namespace App\Services;

use App\Models\ClassSubjectAssignment;
use App\Models\School;
use App\Models\Student;
use App\Models\TeacherClassAssignment;
use App\Models\TeacherSubjectAssignment;
use Illuminate\Support\Collection;

class StudentAcademicControlCenterService
{
    public const FEATURE_STUDENT_360 = 'students.view_assigned';
    public const FEATURE_RESULTS_ENTRY = 'teacher.results.create';
    public const FEATURE_RESULTS_REVIEW = 'results.review';
    public const FEATURE_RESULTS_PUBLISH = 'results.publish';
    public const FEATURE_RESULTS_UPLOAD = 'results.upload';
    public const FEATURE_RESULTS_MANUAL = 'results.manual_entry';

    public function __construct(
        private readonly CurrentSchoolService $currentSchool,
        private readonly SchoolRoleFeatureService $roleFeatures,
        private readonly ResultGradingService $grading,
    ) {
    }

    public function roleContext(): ?string
    {
        return $this->currentSchool->roleContext(auth()->user());
    }

    public function supportAccessIsActive(): bool
    {
        return $this->currentSchool->inSupportMode(auth()->user());
    }

    public function authorizeStudentAccess(Student $student, School $school): void
    {
        if ((int) $student->school_id !== (int) $school->id) {
            abort(403, 'You cannot access this student.');
        }

        $roleContext = $this->roleContext();

        if ($roleContext === 'teacher') {
            if (! $this->featureEnabled($school, self::FEATURE_STUDENT_360)) {
                abort(403, 'This feature is not enabled for your role.');
            }

            if (! $this->teacherCanViewStudent($student, $school)) {
                abort(403, 'You can only access students in your assigned classes or subjects.');
            }
        }
    }

    public function permissions(Student $student, School $school): array
    {
        $roleContext = $this->roleContext();
        $isSupport = $this->supportAccessIsActive();
        $isSuperAdmin = auth()->user()?->hasRole('super_admin') && $isSupport;
        $isSchoolAdmin = $roleContext === 'school_admin' || $isSuperAdmin;
        $isResultOfficer = $roleContext === 'result_officer';
        $isTeacher = $roleContext === 'teacher';
        $classAssigned = $isTeacher && $this->teacherHasClassAssignment($student, $school);
        $subjectIds = $isTeacher ? $this->teacherAssignedSubjectIdsForStudent($student, $school) : collect();

        return [
            'role_context' => $roleContext,
            'support_access' => $isSupport,
            'full_student_access' => $isSchoolAdmin,
            'academic_access' => $isSchoolAdmin || ($isResultOfficer && $this->featureEnabled($school, self::FEATURE_RESULTS_REVIEW)) || $classAssigned || $subjectIds->isNotEmpty(),
            'can_manage_profile' => $isSchoolAdmin,
            'can_archive' => $isSchoolAdmin,
            'can_restore' => $isSchoolAdmin,
            'can_promote' => $isSchoolAdmin,
            'can_enter_results' => $isSchoolAdmin
                || ($isResultOfficer && $this->featureEnabled($school, self::FEATURE_RESULTS_MANUAL))
                || ($isTeacher && $this->featureEnabled($school, self::FEATURE_RESULTS_ENTRY) && ($classAssigned || $subjectIds->isNotEmpty())),
            'can_upload_results' => $isSchoolAdmin || ($isResultOfficer && $this->featureEnabled($school, self::FEATURE_RESULTS_UPLOAD)),
            'can_review_results' => $isSchoolAdmin || ($isResultOfficer && $this->featureEnabled($school, self::FEATURE_RESULTS_REVIEW)),
            'can_publish_results' => $isSchoolAdmin || ($isResultOfficer && $this->featureEnabled($school, self::FEATURE_RESULTS_PUBLISH)),
            'can_configure_grading' => $isSchoolAdmin,
            'can_send_communication' => $isSchoolAdmin || $isResultOfficer,
            'can_view_report_cards' => $isSchoolAdmin || $isResultOfficer || $classAssigned,
            'can_view_scratch_cards' => $isSchoolAdmin || $isResultOfficer,
            'can_view_guardian' => $isSchoolAdmin || $isResultOfficer || $classAssigned,
            'teacher_class_assigned' => $classAssigned,
            'teacher_subject_ids' => $subjectIds->all(),
            'readonly_portal_ready' => true,
        ];
    }

    public function resultWorkspace(Student $student, School $school, $selectedSession = null, $selectedTerm = null): array
    {
        $student->loadMissing('schoolClass');
        $permissions = $this->permissions($student, $school);
        $existingResults = $student->results()
            ->where('school_id', $school->id)
            ->when($selectedSession, fn ($query) => $query->where('academic_session_id', $selectedSession->id))
            ->when($selectedTerm, fn ($query) => $query->where('term_id', $selectedTerm->id))
            ->with(['subject', 'recordedBy', 'publishedBy', 'teacherResultSubmission.teacher'])
            ->get()
            ->keyBy('subject_id');

        $registeredSubjects = $this->registeredSubjects($student, $school, $selectedSession, $selectedTerm);
        $rows = $registeredSubjects->map(function ($assignment) use ($existingResults, $permissions, $school) {
            $subject = $assignment->subject;
            $result = $subject ? $existingResults->get($subject->id) : null;
            $total = (float) ($result?->total_score ?? 0);
            $calculated = $result ? $this->grading->calculate($school, $total) : null;
            $subjectLocked = $result?->status === 'published';
            $teacherSubjectIds = collect($permissions['teacher_subject_ids'] ?? []);

            return [
                'subject' => $subject,
                'assignment' => $assignment,
                'result' => $result,
                'ca1' => $result ? round(((float) $result->ca_score) / 2, 2) : null,
                'ca2' => $result ? round(((float) $result->ca_score) / 2, 2) : null,
                'ca3' => null,
                'assignment_score' => null,
                'project_score' => null,
                'exam' => $result?->exam_score,
                'total' => $result?->total_score,
                'grade' => $result?->grade ?? ($calculated['grade'] ?? null),
                'remark' => $result?->remark ?? ($calculated['remark'] ?? null),
                'is_pass' => $calculated['is_pass'] ?? null,
                'position' => null,
                'subject_teacher_remark' => $result?->teacher_remark,
                'result_officer_remark' => data_get($result, 'metadata.result_officer_remark'),
                'school_admin_remark' => data_get($result, 'metadata.school_admin_remark'),
                'submission_status' => $result?->teacherResultSubmission?->status ?? $result?->status ?? 'not_started',
                'approval_status' => in_array($result?->status, ['reviewed', 'approved', 'published'], true) ? $result->status : 'pending',
                'publish_status' => $result?->status === 'published' ? 'published' : 'unpublished',
                'last_updated' => $result?->updated_at,
                'updated_by' => $result?->recordedBy?->name ?? $result?->teacherResultSubmission?->teacher?->name,
                'can_edit' => ! $subjectLocked && ($permissions['full_student_access'] || $permissions['can_review_results'] || $permissions['teacher_class_assigned'] || ($subject && $teacherSubjectIds->contains($subject->id))),
                'locked' => $subjectLocked,
            ];
        })->values();

        return [
            'rows' => $rows,
            'registered_subject_count' => $registeredSubjects->count(),
            'existing_result_count' => $existingResults->count(),
            'grading_scale_count' => $school->gradingScales()->where('status', 'active')->count(),
            'workflow_statuses' => ['draft', 'submitted', 'returned', 'reviewed', 'approved', 'published', 'unpublished'],
        ];
    }

    private function registeredSubjects(Student $student, School $school, $selectedSession = null, $selectedTerm = null): Collection
    {
        $assigned = ClassSubjectAssignment::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->where(function ($query) use ($student) {
                $query->where('school_class_id', $student->school_class_id)
                    ->orWhereNull('school_class_id');
            })
            ->when($selectedSession, fn ($query) => $query->where(function ($query) use ($selectedSession) {
                $query->where('academic_session_id', $selectedSession->id)->orWhereNull('academic_session_id');
            }))
            ->when($selectedTerm, fn ($query) => $query->where(function ($query) use ($selectedTerm) {
                $query->where('term_id', $selectedTerm->id)->orWhereNull('term_id');
            }))
            ->with('subject')
            ->get()
            ->filter(fn ($assignment) => $assignment->subject)
            ->unique('subject_id');

        $electiveSubjectIds = $student->electiveSubjects()
            ->when($selectedSession, fn ($query) => $query->where('academic_session_id', $selectedSession->id))
            ->when($selectedTerm, fn ($query) => $query->where('term_id', $selectedTerm->id))
            ->pluck('subject_id')
            ->all();

        if ($electiveSubjectIds !== []) {
            $electives = ClassSubjectAssignment::query()
                ->where('school_id', $school->id)
                ->whereIn('subject_id', $electiveSubjectIds)
                ->with('subject')
                ->get()
                ->filter(fn ($assignment) => $assignment->subject);

            $assigned = $assigned->merge($electives)->unique('subject_id');
        }

        return $assigned->sortBy(fn ($assignment) => $assignment->subject?->name)->values();
    }

    private function featureEnabled(School $school, string $featureKey): bool
    {
        $roleContext = $this->roleContext() ?: 'school_admin';

        return $this->roleFeatures->enabled($school->id, $roleContext, $featureKey);
    }

    private function teacherCanViewStudent(Student $student, School $school): bool
    {
        return $this->teacherHasClassAssignment($student, $school)
            || $this->teacherAssignedSubjectIdsForStudent($student, $school)->isNotEmpty();
    }

    private function teacherHasClassAssignment(Student $student, School $school): bool
    {
        return TeacherClassAssignment::where('school_id', $school->id)
            ->where('teacher_user_id', auth()->id())
            ->where('school_class_id', $student->school_class_id)
            ->where('status', 'active')
            ->exists();
    }

    private function teacherAssignedSubjectIdsForStudent(Student $student, School $school): Collection
    {
        return TeacherSubjectAssignment::where('school_id', $school->id)
            ->where('teacher_user_id', auth()->id())
            ->where(function ($query) use ($student) {
                $query->where('school_class_id', $student->school_class_id)
                    ->orWhereNull('school_class_id');
            })
            ->where('status', 'active')
            ->pluck('subject_id')
            ->unique()
            ->values();
    }
}
