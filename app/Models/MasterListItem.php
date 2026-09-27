<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec Section B1's simple flat named lookup lists (Job Categories,
 * Employment Statuses, Education Levels, Skills, Languages, Licenses,
 * Memberships, Nationalities, Countries) share this one table via a `type`
 * discriminator rather than nine near-identical tables/models.
 */
class MasterListItem extends Model
{
    use Auditable;

    public const TYPE_JOB_CATEGORY = 'job_category';

    public const TYPE_EMPLOYMENT_STATUS = 'employment_status';

    public const TYPE_EDUCATION_LEVEL = 'education_level';

    public const TYPE_SKILL = 'skill';

    public const TYPE_LANGUAGE = 'language';

    public const TYPE_LICENSE_TYPE = 'license_type';

    public const TYPE_MEMBERSHIP_BODY = 'membership_body';

    public const TYPE_NATIONALITY = 'nationality';

    public const TYPE_COUNTRY = 'country';

    /** Spec review: every Country/Nationality dropdown in the app defaults to this. */
    public const DEFAULT_COUNTRY_NAME = 'Nigeria';

    public const TYPES = [
        self::TYPE_JOB_CATEGORY => 'Job Categories',
        self::TYPE_EMPLOYMENT_STATUS => 'Employment Statuses',
        self::TYPE_EDUCATION_LEVEL => 'Education Levels',
        self::TYPE_SKILL => 'Skills',
        self::TYPE_LANGUAGE => 'Languages',
        self::TYPE_LICENSE_TYPE => 'License Types',
        self::TYPE_MEMBERSHIP_BODY => 'Membership Bodies',
        self::TYPE_NATIONALITY => 'Nationalities',
        self::TYPE_COUNTRY => 'Countries',
    ];

    protected $fillable = ['type', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public static function defaultCountryId(string $type): ?int
    {
        return static::ofType($type)->where('name', self::DEFAULT_COUNTRY_NAME)->value('id');
    }
}
