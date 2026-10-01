<?php

namespace Modules\Platform\App\Infrastructure\Mock;

use Illuminate\Support\Carbon;

/**
 * Static fixtures for the provider console UI (UI-first stage). Nothing
 * here touches the database: controllers read from this class so every
 * page already has its final prop shape; replace the source, keep the
 * shape, when the real backend lands. Delete this class afterwards.
 *
 * Money is whole rupiah. Dates are ISO strings relative to today so the
 * "due soon" / "overdue" states stay plausible whenever it is opened.
 */
final class ProviderMockData
{
    /**
     * @return list<array{key: string, label: string, available: bool}>
     */
    public static function modules(): array
    {
        return [
            ['key' => 'core', 'label' => 'Inti (Beranda)', 'available' => true],
            ['key' => 'identity', 'label' => 'Identitas & Pengguna', 'available' => true],
            ['key' => 'attendance', 'label' => 'Absensi', 'available' => false],
            ['key' => 'ppdb', 'label' => 'PPDB', 'available' => false],
        ];
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<string>}>
     */
    public static function permissionCatalog(): array
    {
        return [
            [
                'key' => 'identity',
                'label' => 'Identitas & Pengguna',
                'permissions' => [
                    'identity.users.view',
                    'identity.users.create',
                    'identity.users.update',
                    'identity.users.deactivate',
                    'identity.users.sendReset',
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<string>}>
     */
    public static function tenantRoles(): array
    {
        return [
            [
                'key' => 'admin-sekolah',
                'label' => 'Admin Sekolah',
                'permissions' => [
                    'identity.users.view',
                    'identity.users.create',
                    'identity.users.update',
                    'identity.users.deactivate',
                    'identity.users.sendReset',
                ],
            ],
            ['key' => 'guru', 'label' => 'Guru', 'permissions' => []],
            ['key' => 'staf-tu', 'label' => 'Staf/TU', 'permissions' => []],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function plans(): array
    {
        return [
            [
                'key' => 'starter',
                'label' => 'Starter',
                'priceMonthly' => 150_000,
                'priceYearly' => 1_500_000,
                'maxUsers' => 25,
                'modules' => ['core', 'identity'],
                'subscribers' => 3,
            ],
            [
                'key' => 'standard',
                'label' => 'Standard',
                'priceMonthly' => 350_000,
                'priceYearly' => 3_500_000,
                'maxUsers' => 100,
                'modules' => ['core', 'identity', 'attendance'],
                'subscribers' => 3,
            ],
            [
                'key' => 'pro',
                'label' => 'Pro',
                'priceMonthly' => 750_000,
                'priceYearly' => 7_500_000,
                'maxUsers' => null,
                'modules' => ['core', 'identity', 'attendance', 'ppdb'],
                'subscribers' => 2,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tenants(): array
    {
        return [
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V2W', 'SMA Negeri 1 Jakarta', 'sma-1-jakarta', 'active', 'Asia/Jakarta', 'pro', 'yearly', 'active', 'Ibu Ratna Wulandari', 'ratna@sman1jkt.sch.id', 142, ['core', 'identity'], 180, 25),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V2X', 'SMP Al-Hikmah Makassar', 'smp-alhikmah', 'active', 'Asia/Makassar', 'standard', 'monthly', 'active', 'Bapak Andi Mappanyukki', 'andi@alhikmah.sch.id', 87, ['core', 'identity'], 22, 8),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V2Y', 'SD Tunas Bangsa', 'sd-tunasbangsa', 'active', 'Asia/Jakarta', 'starter', 'monthly', 'due', 'Ibu Siti Aminah', 'siti@tunasbangsa.sch.id', 21, ['core', 'identity'], 28, -3),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V2Z', 'SMK Teknologi Surabaya', 'smk-tekno-sby', 'suspended', 'Asia/Jakarta', 'standard', 'yearly', 'overdue', 'Bapak Hendra Gunawan', 'hendra@smktekno.sch.id', 64, ['core', 'identity'], 330, -21),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V30', 'MTs Nurul Huda', 'mts-nurulhuda', 'active', 'Asia/Jakarta', 'starter', 'yearly', 'active', 'Ibu Fatimah Zahra', 'fatimah@nurulhuda.sch.id', 18, ['core', 'identity'], 300, 65),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V31', 'SMA Kristen Immanuel', 'sma-immanuel', 'active', 'Asia/Jayapura', 'pro', 'monthly', 'trial', 'Bapak Yohanes Pigai', 'yohanes@immanuel.sch.id', 36, ['core', 'identity'], 5, 9),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V32', 'SMP Negeri 5 Denpasar', 'smpn5-denpasar', 'active', 'Asia/Makassar', 'standard', 'monthly', 'active', 'Ibu Ni Luh Sari', 'sari@smpn5dps.sch.id', 71, ['core', 'identity'], 16, 14),
            self::tenant('01JB8Z3K4M5N6P7Q8R9S0T1V33', 'SD Islam Terpadu Cahaya', 'sdit-cahaya', 'active', 'Asia/Jakarta', 'starter', 'monthly', 'cancelled', 'Ibu Dewi Lestari', 'dewi@sditcahaya.sch.id', 12, ['core'], 40, -10),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tenantById(string $id): ?array
    {
        foreach (self::tenants() as $tenant) {
            if ($tenant['id'] === $id) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function invoices(): array
    {
        $rows = [
            ['INV-2610-0012', 0, 'pro', 'yearly', 7_500_000, 'paid', 25],
            ['INV-2610-0011', 1, 'standard', 'monthly', 350_000, 'paid', 8],
            ['INV-2610-0010', 2, 'starter', 'monthly', 150_000, 'unpaid', 3],
            ['INV-2609-0009', 3, 'standard', 'yearly', 3_500_000, 'overdue', 21],
            ['INV-2609-0008', 4, 'starter', 'yearly', 1_500_000, 'paid', 65],
            ['INV-2609-0007', 6, 'standard', 'monthly', 350_000, 'paid', 14],
            ['INV-2608-0006', 1, 'standard', 'monthly', 350_000, 'paid', 38],
            ['INV-2608-0005', 2, 'starter', 'monthly', 150_000, 'paid', 33],
            ['INV-2608-0004', 7, 'starter', 'monthly', 150_000, 'void', 45],
            ['INV-2607-0003', 6, 'standard', 'monthly', 350_000, 'paid', 44],
        ];

        $tenants = self::tenants();

        return array_map(fn (array $row): array => [
            'number' => $row[0],
            'tenantId' => $tenants[$row[1]]['id'],
            'tenantName' => $tenants[$row[1]]['name'],
            'plan' => $row[2],
            'cycle' => $row[3],
            'amount' => $row[4],
            'status' => $row[5],
            'issuedAt' => self::day(-$row[6]),
        ], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function subscriptions(): array
    {
        return array_map(fn (array $tenant): array => [
            'tenantId' => $tenant['id'],
            'tenantName' => $tenant['name'],
            'slug' => $tenant['slug'],
            'plan' => $tenant['plan'],
            'cycle' => $tenant['cycle'],
            'status' => $tenant['subscriptionStatus'],
            'startedAt' => $tenant['subscriptionStartedAt'],
            'endsAt' => $tenant['renewsAt'],
            'amount' => $tenant['cycle'] === 'yearly'
                ? self::plan($tenant['plan'])['priceYearly']
                : self::plan($tenant['plan'])['priceMonthly'],
        ], self::tenants());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function providerUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'Provider Admin', 'email' => 'admin@simas.com', 'role' => 'Owner', 'active' => true, 'lastLoginAt' => self::day(0)],
            ['id' => 2, 'name' => 'Rani Kusuma', 'email' => 'rani@simas.com', 'role' => 'Support', 'active' => true, 'lastLoginAt' => self::day(-1)],
            ['id' => 3, 'name' => 'Dimas Pratama', 'email' => 'dimas@simas.com', 'role' => 'Finance', 'active' => true, 'lastLoginAt' => self::day(-4)],
            ['id' => 4, 'name' => 'Lina Marlina', 'email' => 'lina@simas.com', 'role' => 'Support', 'active' => false, 'lastLoginAt' => self::day(-62)],
        ];
    }

    /**
     * @return list<array{month: string, newTenants: int, revenue: int}>
     */
    public static function monthlyTrend(): array
    {
        $values = [[1, 1_850_000], [2, 2_400_000], [0, 2_550_000], [3, 3_950_000], [1, 4_700_000], [2, 5_150_000]];
        $trend = [];

        foreach ($values as $i => [$new, $revenue]) {
            $trend[] = [
                'month' => Carbon::now()->startOfMonth()->subMonths(5 - $i)->locale('id')->translatedFormat('M'),
                'newTenants' => $new,
                'revenue' => $revenue,
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{at: string, text: string}>
     */
    public static function activity(): array
    {
        return [
            ['at' => self::day(0), 'text' => 'SMA Kristen Immanuel memulai masa uji coba paket Pro.'],
            ['at' => self::day(-1), 'text' => 'Pembayaran INV-2610-0011 dari SMP Al-Hikmah Makassar diterima.'],
            ['at' => self::day(-3), 'text' => 'Tagihan INV-2610-0010 untuk SD Tunas Bangsa diterbitkan.'],
            ['at' => self::day(-5), 'text' => 'Rani Kusuma menyetujui pengajuan MTs Nurul Huda.'],
            ['at' => self::day(-21), 'text' => 'SMK Teknologi Surabaya ditangguhkan karena tagihan menunggak.'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function billingSummary(): array
    {
        return [
            'mrr' => 5_150_000,
            'arr' => 61_800_000,
            'activeSubscriptions' => 5,
            'trial' => 1,
            'dueOrOverdue' => 2,
            'cancelled' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function plan(string $key): array
    {
        foreach (self::plans() as $plan) {
            if ($plan['key'] === $key) {
                return $plan;
            }
        }

        return self::plans()[0];
    }

    /**
     * @param  list<string>  $enabledModules
     * @return array<string, mixed>
     */
    private static function tenant(
        string $id,
        string $name,
        string $slug,
        string $status,
        string $timezone,
        string $plan,
        string $cycle,
        string $subscriptionStatus,
        string $adminName,
        string $adminEmail,
        int $userCount,
        array $enabledModules,
        int $startedDaysAgo,
        int $renewsInDays,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'domain' => null,
            'status' => $status,
            'timezone' => $timezone,
            'plan' => $plan,
            'cycle' => $cycle,
            'subscriptionStatus' => $subscriptionStatus,
            'subscriptionStartedAt' => self::day(-$startedDaysAgo),
            'renewsAt' => self::day($renewsInDays),
            'userCount' => $userCount,
            'createdAt' => self::day(-$startedDaysAgo - 2),
            'admin' => ['name' => $adminName, 'email' => $adminEmail],
            'enabledModules' => $enabledModules,
        ];
    }

    private static function day(int $offsetDays): string
    {
        return Carbon::today()->addDays($offsetDays)->toDateString();
    }
}
