<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $patient_id
 * @property int $puskesmas_id
 * @property \Illuminate\Support\Carbon $examination_date
 * @property string $examination_type
 * @property numeric $result
 * @property int $year
 * @property int $month
 * @property bool $is_archived
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Puskesmas $puskesmas
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereExaminationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereExaminationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination wherePuskesmasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DmExamination whereYear($value)
 */
	class DmExamination extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $patient_id
 * @property int $puskesmas_id
 * @property \Illuminate\Support\Carbon $examination_date
 * @property int $systolic
 * @property int $diastolic
 * @property int $year
 * @property int $month
 * @property bool $is_archived
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Puskesmas $puskesmas
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereDiastolic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereExaminationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereIsArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination wherePuskesmasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereSystolic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HtExamination whereYear($value)
 */
	class HtExamination extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $puskesmas_id
 * @property string|null $nik
 * @property string|null $bpjs_number
 * @property string $name
 * @property string|null $address
 * @property string|null $gender
 * @property \Illuminate\Support\Carbon|null $birth_date
 * @property int|null $age
 * @property bool $has_ht
 * @property bool $has_dm
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DmExamination> $dmExaminations
 * @property-read int|null $dm_examinations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HtExamination> $htExaminations
 * @property-read int|null $ht_examinations_count
 * @property-read \App\Models\Puskesmas $puskesmas
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereAge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereBpjsNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereHasDm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereHasHt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereNik($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient wherePuskesmasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Patient whereUpdatedAt($value)
 */
	class Patient extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DmExamination> $dmExaminations
 * @property-read int|null $dm_examinations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HtExamination> $htExaminations
 * @property-read int|null $ht_examinations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Patient> $patients
 * @property-read int|null $patients_count
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\YearlyTarget> $yearlyTargets
 * @property-read int|null $yearly_targets_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Puskesmas whereUserId($value)
 */
	class Puskesmas extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $name
 * @property string|null $profile_picture
 * @property string $role
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Puskesmas|null $puskesmas
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRefreshToken> $refreshTokens
 * @property-read int|null $refresh_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePicture($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $refresh_token
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRefreshToken whereUserId($value)
 */
	class UserRefreshToken extends \Eloquent {}
}

namespace App\Models{
/**
 * 
 *
 * @property int $id
 * @property int $puskesmas_id
 * @property string $disease_type
 * @property int $year
 * @property int $target_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Puskesmas $puskesmas
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereDiseaseType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget wherePuskesmasId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereTargetCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|YearlyTarget whereYear($value)
 */
	class YearlyTarget extends \Eloquent {}
}

