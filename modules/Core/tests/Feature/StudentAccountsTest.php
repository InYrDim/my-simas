<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Actions\PlaceStudents;
use Modules\Core\App\Domain\Enums\PlacementAction;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Login accounts for students: NIS as the username, the birth date as the
 * first password, made per class or per student from master data.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function studentIn(Tenant $tenant, ClassGroup $class, array $attributes = []): Student
{
    return inSchool($tenant, fn (): Student => Student::factory()->create([
        'class_id' => $class->id,
        'birth_date' => '2010-03-04',
        ...$attributes,
    ]));
}

/**
 * The account row behind a student, read without the tenant scope.
 */
function accountOf(Student $student): ?object
{
    $userId = $student->refresh()->user_id;

    return $userId === null ? null : DB::table('users')->where('id', $userId)->first();
}

it('gives every student of a class an account and tells who was skipped', function () {
    $tenant = schoolAs('akun-a');
    $class = classIn($tenant, ['name' => 'X 1']);
    $andi = studentIn($tenant, $class, ['name' => 'Andi', 'nis' => '71001']);
    $budi = studentIn($tenant, $class, ['name' => 'Budi', 'nis' => '71002', 'birth_date' => null]);
    $citra = studentIn($tenant, $class, ['name' => 'Citra', 'nis' => '71003']);

    post(school($tenant->slug, "/master/kelas/{$class->id}/akun-siswa"))
        ->assertRedirect()
        ->assertSessionHas('status', '2 akun dibuat. Dilewati: Budi (tanpa tanggal lahir).');

    expect(accountOf($andi))
        ->username->toBe('71001')
        ->email->toBeNull()
        ->name->toBe('Andi')
        ->tenant_id->toBe($tenant->id)
        ->and((bool) accountOf($andi)->must_change_password)->toBeTrue()
        ->and(accountOf($citra))->not->toBeNull()
        ->and(accountOf($budi))->toBeNull();

    $roles = DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $andi->user_id)
        ->where('roles.tenant_id', $tenant->id)
        ->pluck('roles.name')
        ->all();

    expect($roles)->toBe(['siswa']);
});

it('lets the student sign in with the NIS and the birth date, then asks for a new password', function () {
    $tenant = schoolAs('akun-b');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001', 'birth_date' => '2010-03-04']);

    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"))->assertSessionHas('status');

    signOutOfSchool();

    post(school($tenant->slug, '/login'), [
        'school' => $tenant->id,
        'login' => '71001',
        'password' => '04032010',
    ])->assertRedirect();

    expect(auth()->id())->toBe($student->refresh()->user_id);

    get(school($tenant->slug, '/beranda'))->assertRedirect(route('password.change'));
});

it('does not create a second account when it runs again', function () {
    $tenant = schoolAs('akun-c');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001']);

    post(school($tenant->slug, "/master/kelas/{$class->id}/akun-siswa"));
    $first = $student->refresh()->user_id;

    post(school($tenant->slug, "/master/kelas/{$class->id}/akun-siswa"))
        ->assertSessionHas('status', '0 akun dibuat. Dilewati: 1 sudah punya akun.');

    expect($student->refresh()->user_id)->toBe($first)
        ->and(DB::table('users')->where('tenant_id', $tenant->id)->where('username', '71001')->count())->toBe(1);
});

it('refuses one student who cannot get an account and says why', function (array $attributes, string $reason) {
    $tenant = schoolAs('akun-d');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['name' => 'Dewi', 'nis' => '71001', ...$attributes]);

    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"))
        ->assertSessionHasErrors(['status' => "Akun Dewi tidak dibuat: {$reason}."]);

    expect($student->refresh()->user_id)->toBeNull();
})->with([
    'no birth date' => [['birth_date' => null], 'tanpa tanggal lahir'],
    'graduated' => [['status' => 'graduated', 'class_id' => null], 'bukan siswa aktif'],
]);

it('skips a student whose NIS is the username of another account', function () {
    $tenant = schoolAs('akun-e');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['name' => 'Eka', 'nis' => '71001']);

    inSchool($tenant, fn () => UserFactory::new()->withUsername('71001')->create());

    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"))
        ->assertSessionHasErrors(['status' => 'Akun Eka tidak dibuat: NIS sudah dipakai akun lain.']);

    expect($student->refresh()->user_id)->toBeNull();
});

it('deactivates the account when the student graduates and reactivates it on return', function () {
    $tenant = schoolAs('akun-f');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001']);
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"));

    inSchool($tenant, fn () => app(PlaceStudents::class)->handle(PlacementAction::Graduate, $class, null, [$student->id]));

    expect(accountOf($student)->deactivated_at)->not->toBeNull();

    put(school($tenant->slug, "/master/siswa/{$student->id}"), [
        'name' => $student->name, 'nis' => '71001', 'gender' => $student->gender,
        'status' => 'active', 'class_id' => $class->id,
    ])->assertSessionHasNoErrors();

    expect(accountOf($student)->deactivated_at)->toBeNull();
});

it('keeps the username and the name in step with the student record', function () {
    $tenant = schoolAs('akun-g');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['name' => 'Gilang', 'nis' => '71001']);
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"));

    put(school($tenant->slug, "/master/siswa/{$student->id}"), [
        'name' => 'Gilang Ramadhan', 'nis' => '71999', 'gender' => $student->gender, 'class_id' => $class->id,
    ])->assertSessionHasNoErrors();

    expect(accountOf($student))->username->toBe('71999')->name->toBe('Gilang Ramadhan');
});

it('deactivates the account of a deleted student instead of deleting it', function () {
    $tenant = schoolAs('akun-h');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001']);
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"));
    $userId = $student->refresh()->user_id;

    delete(school($tenant->slug, "/master/siswa/{$student->id}"))->assertRedirect();

    $account = DB::table('users')->where('id', $userId)->first();

    expect($account)->not->toBeNull()
        ->and($account->deactivated_at)->not->toBeNull();
});

it('resets the password back to the birth date', function () {
    $tenant = schoolAs('akun-i');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001', 'birth_date' => '2010-03-04']);
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"));

    DB::table('users')->where('id', $student->refresh()->user_id)->update([
        'password' => bcrypt('SandiSendiri1!'),
        'must_change_password' => false,
    ]);

    post(school($tenant->slug, "/master/siswa/{$student->id}/akun/reset"))->assertSessionHas('status');

    expect((bool) accountOf($student)->must_change_password)->toBeTrue()
        ->and(password_verify('04032010', accountOf($student)->password))->toBeTrue();
});

it('shows the account on the student page and on the class page', function () {
    $tenant = schoolAs('akun-j');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class, ['nis' => '71001']);

    get(school($tenant->slug, "/master/siswa/{$student->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('login.account', null)
        ->where('login.can.create', true)
        ->where('login.can.reset', true));

    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"));

    get(school($tenant->slug, "/master/siswa/{$student->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('login.account.username', '71001')
        ->where('login.account.active', true)
        ->where('login.account.mustChangePassword', true));

    get(school($tenant->slug, "/master/kelas/{$class->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('canCreateAccounts', true)
        ->where('students.0.hasAccount', true));
});

it('forbids a teacher from making or resetting accounts', function () {
    $tenant = schoolAs('akun-k', 'guru');
    $class = classIn($tenant);
    $student = studentIn($tenant, $class);

    post(school($tenant->slug, "/master/kelas/{$class->id}/akun-siswa"))->assertForbidden();
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"))->assertForbidden();
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun/reset"))->assertForbidden();

    expect($student->refresh()->user_id)->toBeNull();

    get(school($tenant->slug, "/master/siswa/{$student->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('login.can.create', false)
        ->where('login.can.reset', false));
});

it('does not reach a class or a student of another school', function () {
    $other = schoolAs('akun-l-lain');
    $class = classIn($other);
    $student = studentIn($other, $class);

    $tenant = schoolAs('akun-l');

    post(school($tenant->slug, "/master/kelas/{$class->id}/akun-siswa"))->assertNotFound();
    post(school($tenant->slug, "/master/siswa/{$student->id}/akun"))->assertNotFound();

    expect($student->refresh()->user_id)->toBeNull();
});
