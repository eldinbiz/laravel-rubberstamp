<?php

declare(strict_types=1);

use Eldinbiz\RubberStamp\Support\AuditMetadataResolver;

test('it formats browser test class name strictly into hyphen separated subdirectory', function (): void {
    expect(AuditMetadataResolver::formatSnapshotSubdirectory('Tests\Browser\RoleManagementTest'))
        ->toBe('Tests-Browser-RoleManagementTest')
        ->and(AuditMetadataResolver::formatSnapshotSubdirectory('Tests\Browser\LoginTest'))
        ->toBe('Tests-Browser-LoginTest')
        ->and(AuditMetadataResolver::formatSnapshotSubdirectory('Tests\Browser\UserManagementTest'))
        ->toBe('Tests-Browser-UserManagementTest');
});

test('it strips dynamic Pest prefix P\\ from class name', function (): void {
    expect(AuditMetadataResolver::formatSnapshotSubdirectory('P\Tests\Browser\RoleManagementTest'))
        ->toBe('Tests-Browser-RoleManagementTest')
        ->and(AuditMetadataResolver::formatSnapshotSubdirectory('P\Tests\Browser\LoginTest'))
        ->toBe('Tests-Browser-LoginTest');
});

test('it formats nested test namespaces and module namespaces correctly', function (): void {
    expect(AuditMetadataResolver::formatSnapshotSubdirectory('Tests\Browser\Settings\SecurityTest'))
        ->toBe('Tests-Browser-Settings-SecurityTest')
        ->and(AuditMetadataResolver::formatSnapshotSubdirectory('Modules\User\Tests\Browser\UserTest'))
        ->toBe('Modules-User-Tests-Browser-UserTest');
});

test('it sanitizes non-alphanumeric characters while preserving underscores and hyphens', function (): void {
    expect(AuditMetadataResolver::formatSnapshotSubdirectory('Tests\Browser\Special@Feature#Test'))
        ->toBe('Tests-Browser-Special-Feature-Test');
});
