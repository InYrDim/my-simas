<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Login accounts for teachers and staff: NIP as the username with a
 * random first password, or a link to the account under the same email.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function teacherIn(Tenant $tenant, array $attributes = []): Teacher
{
    return inSchool($tenant, fn (): Teacher => Teacher::factory()->create([
        'name' => 'Bu Rina',
        'nip' => '198705012010012001',
        'email' => 'rina@guru.test',
        ...$attributes,
    ]));
}

function teacherAccount(Teacher $teacher): ?object
{
    $userId = $teacher->refresh()->user_id;

    return $userId === null ? null : DB::table('users')->where('id', $userId)->first();
}

/**
 * @return list<string>
 */
function roleNamesOf(Tenant $tenant, int $userId): array
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $userId)
        ->where('roles.tenant_id', $tenant->id)
        ->pluck('roles.name')
        ->all();
}

it('gives a teacher an account that signs in by NIP with the chosen role', function () {
    $tenant = schoolAs('guru-a');
    $teacher = teacherIn($tenant);

    $response = post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'staf-tu'])
        ->assertRedirect()
        ->assertSessionHas('status')
        ->assertSessionHas('password');

    $password = session('password');
    $account = teacherAccount($teacher);

    expect($account)
        ->username->toBe('198705012010012001')
        ->email->toBe('rina@guru.test')
        ->name->toBe('Bu Rina')
        ->and((bool) $account->must_change_password)->toBeTrue()
        ->and(strlen($password))->toBe(10)
        ->and(password_verify($password, $account->password))->toBeTrue()
        ->and(roleNamesOf($tenant, $account->id))->toBe(['staf-tu']);

    signOutOfSchool();

    post(school($tenant->slug, '/login'), [
        'school' => $tenant->id,
        'login' => '198705012010012001',
        'password' => $password,
    ])->assertRedirect();

    expect(auth()->id())->toBe($account->id);
});

it('shows the first password once', function () {
    $tenant = schoolAs('guru-b');
    $teacher = teacherIn($tenant);

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru']);

    get(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('flash.password', fn ($password) => is_string($password) && strlen($password) === 10)
        ->where('login.account.username', '198705012010012001'));

    get(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('flash.password', null));
});

it('refuses a role that is not offered and a teacher without a NIP', function () {
    $tenant = schoolAs('guru-c');
    $teacher = teacherIn($tenant);
    $withoutNip = teacherIn($tenant, ['name' => 'Pak Honor', 'nip' => null, 'email' => 'honor@guru.test']);

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'admin-sekolah'])
        ->assertSessionHasErrors('role');

    post(school($tenant->slug, "/master/guru/{$withoutNip->id}/akun"), ['role' => 'guru'])
        ->assertSessionHasErrors('status');

    expect($teacher->refresh()->user_id)->toBeNull()
        ->and($withoutNip->refresh()->user_id)->toBeNull();
});

it('points to linking when the email already belongs to an account', function () {
    $tenant = schoolAs('guru-d');
    $teacher = teacherIn($tenant);
    $existing = inSchool($tenant, fn () => UserFactory::new()->create(['email' => 'rina@guru.test']));

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru'])
        ->assertSessionHasErrors('status');

    expect($teacher->refresh()->user_id)->toBeNull();

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/tautkan"))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($teacher->refresh()->user_id)->toBe($existing->id)
        // Linking changes nothing about the account itself.
        ->and(teacherAccount($teacher))->username->toBeNull();
});

it('refuses to link without an account, without an email, or to an account another teacher holds', function () {
    $tenant = schoolAs('guru-e');
    $noAccount = teacherIn($tenant, ['email' => 'belum@guru.test']);
    $noEmail = teacherIn($tenant, ['name' => 'Tanpa Email', 'nip' => '111', 'email' => null]);

    post(school($tenant->slug, "/master/guru/{$noAccount->id}/akun/tautkan"))->assertSessionHasErrors('status');
    post(school($tenant->slug, "/master/guru/{$noEmail->id}/akun/tautkan"))->assertSessionHasErrors('status');

    $account = inSchool($tenant, fn () => UserFactory::new()->create(['email' => 'sama@guru.test']));
    $first = teacherIn($tenant, ['name' => 'Pertama', 'nip' => '222', 'email' => 'sama@guru.test']);
    $second = teacherIn($tenant, ['name' => 'Kedua', 'nip' => '333', 'email' => 'sama@guru.test']);

    post(school($tenant->slug, "/master/guru/{$first->id}/akun/tautkan"))->assertSessionHasNoErrors();
    post(school($tenant->slug, "/master/guru/{$second->id}/akun/tautkan"))->assertSessionHasErrors('status');

    expect($first->refresh()->user_id)->toBe($account->id)
        ->and($second->refresh()->user_id)->toBeNull()
        ->and($noAccount->refresh()->user_id)->toBeNull();
});

it('resets a teacher password to a new random one', function () {
    $tenant = schoolAs('guru-f');
    $teacher = teacherIn($tenant);
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru']);
    $first = session('password');

    DB::table('users')->where('id', $teacher->refresh()->user_id)->update(['must_change_password' => false]);

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/reset"))->assertSessionHas('password');
    $second = session('password');
    $account = teacherAccount($teacher);

    expect($second)->not->toBe($first)
        ->and(password_verify($second, $account->password))->toBeTrue()
        ->and((bool) $account->must_change_password)->toBeTrue();
});

it('follows a new NIP for an account that signs in by NIP, and leaves a linked email account alone', function () {
    $tenant = schoolAs('guru-g');
    $teacher = teacherIn($tenant);
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru']);

    put(school($tenant->slug, "/master/guru/{$teacher->id}"), [
        'name' => 'Bu Rina, S.Pd.', 'nip' => '199001', 'employment' => 'PNS', 'duty' => 'Guru Mapel', 'email' => 'rina@guru.test',
    ])->assertSessionHasNoErrors();

    expect(teacherAccount($teacher))->username->toBe('199001')->name->toBe('Bu Rina, S.Pd.');

    inSchool($tenant, fn () => UserFactory::new()->create(['email' => 'tari@guru.test', 'name' => 'Tari Akun']));
    $linked = teacherIn($tenant, ['name' => 'Bu Tari', 'nip' => '444', 'email' => 'tari@guru.test']);
    post(school($tenant->slug, "/master/guru/{$linked->id}/akun/tautkan"));

    put(school($tenant->slug, "/master/guru/{$linked->id}"), [
        'name' => 'Bu Tari, M.Pd.', 'nip' => '555', 'employment' => 'PNS', 'duty' => 'Guru Mapel', 'email' => 'tari@guru.test',
    ])->assertSessionHasNoErrors();

    expect(teacherAccount($linked))->username->toBeNull()->name->toBe('Tari Akun');
});

it('deactivates the account of a deleted teacher', function () {
    $tenant = schoolAs('guru-h');
    $teacher = teacherIn($tenant);
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru']);
    $userId = $teacher->refresh()->user_id;

    delete(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertRedirect();

    expect(DB::table('users')->where('id', $userId)->value('deactivated_at'))->not->toBeNull();
});

it('keeps the last admin signed in when their own teacher record is deleted', function () {
    $tenant = schoolAs('guru-i');
    $teacher = teacherIn($tenant, ['email' => 'admin-sekolah@guru-i.test']);
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/tautkan"))->assertSessionHasNoErrors();

    delete(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(0)
        ->and(DB::table('users')->where('id', auth()->id())->value('deactivated_at'))->toBeNull();
});

it('forbids a teacher from making, linking or resetting accounts', function () {
    $tenant = schoolAs('guru-j', 'guru');
    $teacher = teacherIn($tenant);

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru'])->assertForbidden();
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/tautkan"))->assertForbidden();
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/reset"))->assertForbidden();

    expect($teacher->refresh()->user_id)->toBeNull();
});

it('does not reach a teacher of another school', function () {
    $other = schoolAs('guru-k-lain');
    $teacher = teacherIn($other);

    $tenant = schoolAs('guru-k');

    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun"), ['role' => 'guru'])->assertNotFound();
    post(school($tenant->slug, "/master/guru/{$teacher->id}/akun/tautkan"))->assertNotFound();

    expect($teacher->refresh()->user_id)->toBeNull();
});
