<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final readonly class ApiFilter
{
    /** @param array<int,string> $projects @param array<int,string> $mitras @param array<int,string> $riskStatuses @param array<int,string> $projectStatuses */
    public function __construct(
        public CarbonImmutable $reportingAsOf,
        public array $projects = [],
        public array $mitras = [],
        public ?CarbonImmutable $periodFrom = null,
        public ?CarbonImmutable $periodTo = null,
        public array $riskStatuses = [],
        public array $projectStatuses = [],
        public int $pageSize = 50,
        public ?string $cursor = null,
        public array $includes = [],
    ) {}

    public static function fromRequest(Request $request, ?ApiKeyPrincipal $principal = null): self
    {
        $query = $request->query();
        $allowedTopLevel = ['filter', 'reporting_as_of', 'page', 'include'];
        foreach (array_keys($query) as $key) {
            if (! in_array($key, $allowedTopLevel, true)) {
                throw new ApiException('invalid_parameter', 422, 'Parameter API tidak dikenal.', ['parameter' => $key]);
            }
        }

        $rawFilter = $query['filter'] ?? [];
        if (! is_array($rawFilter)) {
            throw new ApiException('invalid_filter', 422, 'Filter API harus berbentuk object.');
        }

        $allowedFilters = ['project', 'mitra', 'period_from', 'period_to', 'risk_status', 'status_project'];
        foreach (array_keys($rawFilter) as $key) {
            if (! in_array($key, $allowedFilters, true)) {
                throw new ApiException('invalid_filter', 422, 'Filter API tidak dikenal.', ['filter' => $key]);
            }
        }

        $projects = self::list($rawFilter['project'] ?? null, 'filter[project]');
        $mitras = self::list($rawFilter['mitra'] ?? null, 'filter[mitra]');
        $riskStatuses = self::list($rawFilter['risk_status'] ?? null, 'filter[risk_status]');
        $projectStatuses = self::list($rawFilter['status_project'] ?? null, 'filter[status_project]');

        self::validateValues($riskStatuses, ['healthy', 'watch', 'critical', 'na'], 'filter[risk_status]');
        self::validateValues($projectStatuses, ['aktif', 'selesai'], 'filter[status_project]');

        if ($principal?->mitraId() !== null && $mitras !== [] && $mitras !== [$principal->mitraCode()]) {
            throw new ApiException('scope_violation', 403, 'Filter Mitra berada di luar cakupan API Key.');
        }

        $periodFrom = self::date($rawFilter['period_from'] ?? null, 'filter[period_from]');
        $periodTo = self::date($rawFilter['period_to'] ?? null, 'filter[period_to]');
        if ($periodFrom !== null && $periodTo !== null && $periodFrom->gt($periodTo)) {
            throw new ApiException('invalid_filter', 422, 'Rentang periode tidak valid.');
        }

        $reportingAsOf = self::date($query['reporting_as_of'] ?? null, 'reporting_as_of')
            ?? CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        $rawPage = $query['page'] ?? [];
        if (! is_array($rawPage)) {
            throw new ApiException('invalid_parameter', 422, 'Parameter page API harus berbentuk object.');
        }
        foreach (array_keys($rawPage) as $key) {
            if (! in_array($key, ['size', 'cursor'], true)) {
                throw new ApiException('invalid_parameter', 422, 'Parameter page tidak dikenal.', ['parameter' => $key]);
            }
        }
        $pageSize = $rawPage['size'] ?? 50;
        if (filter_var($pageSize, FILTER_VALIDATE_INT) === false || (int) $pageSize < 1 || (int) $pageSize > 200) {
            throw new ApiException('invalid_parameter', 422, 'page[size] harus berada di antara 1 dan 200.');
        }
        $cursor = $rawPage['cursor'] ?? null;
        if ($cursor !== null && ! is_string($cursor)) {
            throw new ApiException('invalid_parameter', 422, 'page[cursor] harus berupa string.');
        }

        $includes = self::list($query['include'] ?? null, 'include');
        self::validateValues($includes, [
            'mitra', 'steps', 'curve', 'material_readiness', 'material_requests',
            'material_transactions', 'service_prices', 'photo_links',
        ], 'include');

        return new self(
            projects: $projects,
            mitras: $mitras,
            periodFrom: $periodFrom,
            periodTo: $periodTo,
            riskStatuses: $riskStatuses,
            projectStatuses: $projectStatuses,
            reportingAsOf: $reportingAsOf,
            pageSize: (int) $pageSize,
            cursor: $cursor,
            includes: $includes,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'project' => $this->projects === [] ? null : $this->projects,
            'mitra' => $this->mitras === [] ? null : $this->mitras,
            'period_from' => $this->periodFrom?->toDateString(),
            'period_to' => $this->periodTo?->toDateString(),
            'risk_status' => $this->riskStatuses === [] ? null : $this->riskStatuses,
            'status_project' => $this->projectStatuses === [] ? null : $this->projectStatuses,
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /** @return array<int,string> */
    private static function list(mixed $value, string $parameter): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        }
        if (! is_string($value)) {
            throw new ApiException('invalid_filter', 422, 'Nilai filter tidak valid.', ['filter' => $parameter]);
        }

        $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== '')));
        if (count($values) > 100 || collect($values)->contains(fn (string $item): bool => strlen($item) > 100)) {
            throw new ApiException('invalid_filter', 422, 'Nilai filter terlalu panjang.', ['filter' => $parameter]);
        }

        return $values;
    }

    /** @param array<int,string> $values @param array<int,string> $allowed */
    private static function validateValues(array $values, array $allowed, string $parameter): void
    {
        $invalid = array_values(array_diff($values, $allowed));
        if ($invalid !== []) {
            throw new ApiException('invalid_filter', 422, 'Nilai filter tidak valid.', ['filter' => $parameter, 'values' => $invalid]);
        }
    }

    private static function date(mixed $value, string $parameter): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ApiException('invalid_filter', 422, 'Tanggal harus memakai format YYYY-MM-DD.', ['parameter' => $parameter]);
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        } catch (\Throwable) {
            $date = false;
        }
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ApiException('invalid_filter', 422, 'Tanggal filter tidak valid.', ['parameter' => $parameter]);
        }

        return $date;
    }
}
