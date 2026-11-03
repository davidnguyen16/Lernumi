<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::query()->forceCreate(['name' => 'Subject administrator', 'email' => 'subjects@example.test', 'password' => 'test-password', 'is_admin' => true])->refresh());
    }

    public function test_creating_a_subject_normalizes_input_and_returns_its_location(): void
    {
        $institution = $this->institution();

        $response = $this->postJson($this->endpoint($institution), [
            'subjectCode' => ' comp1000 ',
            'subjectName' => ' Introduction to Programming ',
            'subjectDescription' => '   ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.institutionId', $institution->id)
            ->assertJsonPath('data.subjectCode', 'COMP1000')
            ->assertJsonPath('data.subjectName', 'Introduction to Programming')
            ->assertJsonPath('data.subjectDescription', null)
            ->assertJsonStructure(['data' => [
                'id', 'institutionId', 'subjectCode', 'subjectName',
                'subjectDescription', 'createdAt', 'updatedAt',
            ]]);

        $id = $response->json('data.id');
        $this->assertTrue(Str::isUuid($id));
        $this->assertStringEndsWith($this->endpoint($institution, $id), $response->headers->get('Location'));
        $this->assertDatabaseHas('subjects', [
            'id' => $id,
            'institution_id' => $institution->id,
            'subject_code' => 'COMP1000',
            'subject_name' => 'Introduction to Programming',
            'subject_description' => null,
        ]);
    }

    public function test_read_patch_replace_and_delete_follow_resource_semantics(): void
    {
        $institution = $this->institution();
        $subject = $this->subject($institution, 'COMP1000', 'Programming', 'Original description');
        $endpoint = $this->endpoint($institution, $subject->id);

        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.id', $subject->id);

        $this->patchJson($endpoint, ['subjectName' => 'Programming Fundamentals'])
            ->assertOk()
            ->assertJsonPath('data.subjectCode', 'COMP1000')
            ->assertJsonPath('data.subjectName', 'Programming Fundamentals')
            ->assertJsonPath('data.subjectDescription', 'Original description');

        $this->patchJson($endpoint, ['subjectDescription' => null])
            ->assertOk()->assertJsonPath('data.subjectDescription', null);

        $this->patchJson($endpoint, ['subjectDescription' => 'Temporary description'])->assertOk();
        $this->putJson($endpoint, [
            'subjectCode' => ' comp1010 ',
            'subjectName' => 'Algorithms',
        ])->assertOk()
            ->assertJsonPath('data.subjectCode', 'COMP1010')
            ->assertJsonPath('data.subjectDescription', null);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'subject_code' => 'COMP1010',
            'subject_name' => 'Algorithms',
            'subject_description' => null,
        ]);

        $this->deleteJson($endpoint)->assertNoContent();
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->getJson($endpoint)->assertNotFound();
    }

    public function test_subject_codes_are_unique_within_each_institution_after_normalization(): void
    {
        $institution = $this->institution();
        $otherInstitution = $this->institution('Another institution');
        $first = $this->subject($institution, 'COMP1000');
        $second = $this->subject($institution, 'COMP1010');

        $this->postJson($this->endpoint($institution), [
            'subjectCode' => ' comp1000 ', 'subjectName' => 'Duplicate',
        ])->assertUnprocessable()->assertJsonValidationErrors('subjectCode');

        $this->postJson($this->endpoint($otherInstitution), [
            'subjectCode' => 'COMP1000', 'subjectName' => 'Allowed in another institution',
        ])->assertCreated();

        $this->patchJson($this->endpoint($institution, $first->id), ['subjectCode' => ' comp1000 '])
            ->assertOk()->assertJsonPath('data.subjectCode', 'COMP1000');

        $this->patchJson($this->endpoint($institution, $second->id), ['subjectCode' => 'comp1000'])
            ->assertUnprocessable()->assertJsonValidationErrors('subjectCode');

        $this->assertDatabaseHas('subjects', ['id' => $second->id, 'subject_code' => 'COMP1010']);
    }

    public function test_postgresql_also_enforces_the_scoped_unique_constraint(): void
    {
        $institution = $this->institution();
        $this->subject($institution, 'COMP1000');

        $this->expectException(UniqueConstraintViolationException::class);

        // The nested transaction rolls back to a savepoint after PostgreSQL rejects the duplicate.
        DB::transaction(fn () => $this->subject($institution, 'COMP1000'));
    }

    #[DataProvider('invalidCreatePayloads')]
    public function test_invalid_subjects_return_field_errors_without_writing_data(array $payload, array $fields): void
    {
        $institution = $this->institution();

        $this->postJson($this->endpoint($institution), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($fields);

        $this->assertDatabaseCount('subjects', 0);
    }

    public static function invalidCreatePayloads(): array
    {
        return [
            'missing required fields' => [[], ['subjectCode', 'subjectName']],
            'blank values' => [['subjectCode' => ' ', 'subjectName' => ' '], ['subjectCode', 'subjectName']],
            'short code' => [['subjectCode' => 'A', 'subjectName' => 'Valid'], ['subjectCode']],
            'long code' => [['subjectCode' => str_repeat('A', 21), 'subjectName' => 'Valid'], ['subjectCode']],
            'invalid code characters' => [['subjectCode' => 'COMP_1000', 'subjectName' => 'Valid'], ['subjectCode']],
            'non-string code' => [['subjectCode' => ['COMP1000'], 'subjectName' => 'Valid'], ['subjectCode']],
            'null name' => [['subjectCode' => 'COMP1000', 'subjectName' => null], ['subjectName']],
            'long name' => [['subjectCode' => 'COMP1000', 'subjectName' => str_repeat('N', 201)], ['subjectName']],
            'long description' => [[
                'subjectCode' => 'COMP1000', 'subjectName' => 'Valid', 'subjectDescription' => str_repeat('D', 1001),
            ], ['subjectDescription']],
        ];
    }

    public function test_invalid_updates_do_not_mutate_the_subject_and_put_requires_both_fields(): void
    {
        $institution = $this->institution();
        $subject = $this->subject($institution, 'COMP1000', 'Original');
        $endpoint = $this->endpoint($institution, $subject->id);

        $this->patchJson($endpoint, ['subjectName' => ' ', 'subjectCode' => 'COMP/1000'])
            ->assertUnprocessable()->assertJsonValidationErrors(['subjectName', 'subjectCode']);
        $this->putJson($endpoint, ['subjectName' => 'Replacement'])
            ->assertUnprocessable()->assertJsonValidationErrors('subjectCode');
        $this->putJson($endpoint, ['subjectCode' => 'COMP1010'])
            ->assertUnprocessable()->assertJsonValidationErrors('subjectName');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id, 'subject_code' => 'COMP1000', 'subject_name' => 'Original',
        ]);
    }

    public function test_subject_routes_cannot_read_or_mutate_a_subject_in_another_institution(): void
    {
        $institution = $this->institution();
        $otherInstitution = $this->institution('Another institution');
        $subject = $this->subject($institution, 'COMP1000', 'Original');
        $wrongEndpoint = $this->endpoint($otherInstitution, $subject->id);

        $this->getJson($wrongEndpoint)->assertNotFound();
        $this->patchJson($wrongEndpoint, ['subjectName' => 'Changed'])->assertNotFound();
        $this->putJson($wrongEndpoint, ['subjectCode' => 'COMP1010', 'subjectName' => 'Changed'])->assertNotFound();
        $this->deleteJson($wrongEndpoint)->assertNotFound();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id, 'institution_id' => $institution->id, 'subject_name' => 'Original',
        ]);
    }

    public function test_client_input_cannot_reassign_a_subject_to_another_institution(): void
    {
        $institution = $this->institution();
        $otherInstitution = $this->institution('Another institution');
        $subject = $this->subject($institution, 'COMP1000');

        $response = $this->patchJson($this->endpoint($institution, $subject->id), [
            'subjectName' => 'Renamed',
            'institutionId' => $otherInstitution->id,
            'institution_id' => $otherInstitution->id,
        ]);

        $response->assertOk()->assertJsonPath('data.institutionId', $institution->id);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'institution_id' => $institution->id]);
    }

    public function test_missing_and_malformed_resource_identifiers_return_json_404s(): void
    {
        $institution = $this->institution();

        $this->getJson($this->endpoint($institution, (string) Str::uuid()))->assertNotFound();
        $this->getJson($this->endpoint($institution, 'not-a-uuid'))->assertNotFound();
        $this->getJson('/api/v1/institutions/not-a-uuid/subjects')->assertNotFound();
        $this->postJson('/api/v1/institutions/'.Str::uuid().'/subjects', [
            'subjectCode' => 'COMP1000', 'subjectName' => 'Missing institution',
        ])->assertNotFound();
    }

    public function test_listing_is_paginated_ordered_and_scoped_to_the_parent_institution(): void
    {
        $institution = $this->institution();
        $otherInstitution = $this->institution('Another institution');
        $this->subject($institution, 'COMP1030');
        $this->subject($institution, 'COMP1010');
        $this->subject($institution, 'COMP1020');
        $this->subject($otherInstitution, 'COMP1000');

        $this->getJson($this->endpoint($institution).'?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subjectCode', 'COMP1010')
            ->assertJsonPath('data.1.subjectCode', 'COMP1020')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->getJson($this->endpoint($institution).'?per_page=2&page=2')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subjectCode', 'COMP1030')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_search_matches_code_or_name_case_insensitively_without_crossing_scope(): void
    {
        $institution = $this->institution();
        $otherInstitution = $this->institution('Another institution');
        $this->subject($institution, 'COMP1000', 'Programming');
        $this->subject($institution, 'MATH1000', 'Computer Mathematics');
        $this->subject($institution, 'HIST1000', 'History');
        $this->subject($otherInstitution, 'COMP1010', 'Computing elsewhere');

        $this->getJson($this->endpoint($institution).'?search=cOmP')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subjectCode', 'COMP1000')
            ->assertJsonPath('data.1.subjectCode', 'MATH1000')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_treats_sql_like_wildcards_as_literal_characters(): void
    {
        $institution = $this->institution();
        $this->subject($institution, 'MATH1000', 'Percent 100%');
        $this->subject($institution, 'COMP1000', 'Under_score');
        $this->subject($institution, 'HIST1000', 'History');

        $this->getJson($this->endpoint($institution).'?'.http_build_query(['search' => '%']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subjectCode', 'MATH1000');
        $this->getJson($this->endpoint($institution).'?'.http_build_query(['search' => '_']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subjectCode', 'COMP1000');
    }

    #[DataProvider('invalidPagination')]
    public function test_invalid_pagination_returns_validation_errors(string $query, string $field): void
    {
        $this->getJson($this->endpoint($this->institution()).'?'.$query)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidPagination(): array
    {
        return [
            ['page=0', 'page'],
            ['page=abc', 'page'],
            ['per_page=0', 'per_page'],
            ['per_page=101', 'per_page'],
            ['per_page=1.5', 'per_page'],
        ];
    }

    private function institution(string $name = 'Test institution'): Institution
    {
        return Institution::query()->create(['name' => $name]);
    }

    private function subject(
        Institution $institution,
        string $code,
        string $name = 'Test subject',
        ?string $description = null,
    ): Subject {
        return $institution->subjects()->create([
            'subject_code' => $code,
            'subject_name' => $name,
            'subject_description' => $description,
        ]);
    }

    private function endpoint(Institution $institution, ?string $subjectId = null): string
    {
        $endpoint = '/api/v1/institutions/'.$institution->id.'/subjects';

        return $subjectId === null ? $endpoint : $endpoint.'/'.$subjectId;
    }
}
