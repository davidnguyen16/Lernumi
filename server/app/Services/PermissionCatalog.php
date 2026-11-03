<?php

namespace App\Services;

/** Stable permission vocabulary, shared by seeders, role editors and authorization. */
final class PermissionCatalog
{
    public const BUNDLES = [
        'institution.settings.manage' => ['institution.settings.view', 'institution.settings.update'],
        'subject.manage' => ['subject.create', 'subject.view', 'subject.update', 'subject.delete'],
        'term.manage' => ['term.create', 'term.view', 'term.update', 'term.delete'],
        'role.manage' => ['role.create', 'role.view', 'role.update', 'role.delete'],
        'role_request.review' => ['role_request.view', 'role_request.approve', 'role_request.reject'],
        'scope_request.review' => ['scope_request.view', 'scope_request.approve', 'scope_request.reject'],
        'role_assignment.manage' => ['role_assignment.view', 'role_assignment.create', 'role_assignment.revoke'],
        'scope_assignment.manage' => ['scope_assignment.view', 'scope_assignment.create', 'scope_assignment.revoke'],
        'scope_assignment_code.manage' => ['scope_assignment_code.view', 'scope_assignment_code.create', 'scope_assignment_code.revoke'],
        'offering.manage' => ['offering.view', 'offering.update', 'offering.delete'],
        'assessment.manage' => ['assessment.create', 'assessment.view', 'assessment.update', 'assessment.delete'],
    ];

    public const EXTRA = ['institution.view', 'institution.settings.view', 'institution.settings.update', 'offering.create', 'member.view', 'audit.view', 'assessment.view_published', 'assessment.attempt', 'attempt.start', 'attempt.submit', 'attempt.view_own', 'attempt.view_all', 'attempt.mark', 'attempt.replay', 'attempt.result.finalize', 'tool.launch', 'tool_session.replay_own', 'tool_session.replay_all'];

    public static function codes(): array
    {
        return array_values(array_unique(array_merge(self::EXTRA, array_keys(self::BUNDLES), ...array_values(self::BUNDLES))));
    }

    public static function institutionOnly(string $code): bool
    {
        return str_starts_with($code, 'institution.') || str_starts_with($code, 'subject.') || str_starts_with($code, 'term.') || str_starts_with($code, 'role.') || str_starts_with($code, 'role_request.') || in_array($code, ['offering.create', 'audit.view']);
    }

    public static function expand(array $codes): array
    {
        $implications = self::BUNDLES + [
            'assessment.view' => ['assessment.view_published'],
            'assessment.attempt' => ['attempt.start', 'attempt.submit'],
            'tool_session.replay_all' => ['attempt.replay', 'tool_session.replay_own'],
        ];
        $result = array_fill_keys($codes, true);
        foreach ($codes as $code) {
            foreach ($implications[$code] ?? [] as $implied) {
                foreach (self::expand([$implied]) as $expanded) $result[$expanded] = true;
            }
        }
        foreach (self::BUNDLES as $bundle => $parts) {
            if (count(array_intersect($parts, array_keys($result))) === count($parts)) $result[$bundle] = true;
        }
        if (isset($result['attempt.start'], $result['attempt.submit'])) $result['assessment.attempt'] = true;
        return array_keys($result);
    }
}
