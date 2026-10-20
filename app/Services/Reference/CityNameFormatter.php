<?php

namespace App\Services\Reference;

use App\Models\RefCity;
use App\Models\RefDistrict;
use App\Models\RefSubdistrict;
use stdClass;

class CityNameFormatter
{
    public static function displayName(?string $name, ?string $type): ?string
    {
        if ($name === null || trim($name) === '' || preg_match('/^(Kota|Kabupaten)\s+/i', $name) === 1) {
            return $name;
        }

        $prefix = match (strtolower(trim((string) $type))) {
            'kota' => 'Kota',
            'kabupaten' => 'Kabupaten',
            default => null,
        };

        return $prefix === null ? $name : $prefix.' '.trim($name);
    }

    public function format(mixed $data, bool $isCityList = false): mixed
    {
        $locations = [];
        $this->collectLocations($data, $locations);
        if ($isCityList && is_array($data)) {
            foreach ($data as $city) {
                if ($city instanceof stdClass) {
                    $this->addLocation($locations, $city, 'name', $city->type ?? null);
                }
            }
        }

        $untyped = array_filter($locations, fn (array $location): bool => $location['type'] === null);
        $cityIds = array_unique(array_filter(array_column($untyped, 'city_id')));
        $cityTypes = $cityIds === [] ? [] : RefCity::query()
            ->whereIn('city_id', $cityIds)->pluck('city_type', 'city_id')->all();
        $unresolved = array_filter($untyped, fn (array $location): bool => ! isset($cityTypes[$location['city_id']]));
        $subdistrictIds = array_unique(array_filter(array_column($unresolved, 'subdistrict_id')));
        $cityTable = (new RefCity)->getTable();
        $districtTable = (new RefDistrict)->getTable();
        $subdistrictTable = (new RefSubdistrict)->getTable();
        $subdistrictTypes = $subdistrictIds === [] ? [] : RefSubdistrict::query()
            ->join($districtTable, "{$districtTable}.district_id", '=', "{$subdistrictTable}.subdistrict_district_id")
            ->join($cityTable, "{$cityTable}.city_id", '=', "{$districtTable}.district_city_id")
            ->whereIn("{$subdistrictTable}.subdistrict_id", $subdistrictIds)
            ->pluck("{$cityTable}.city_type", "{$subdistrictTable}.subdistrict_id")->all();
        $unresolved = array_filter($unresolved, fn (array $location): bool => ! isset($subdistrictTypes[$location['subdistrict_id']]));
        $districtIds = array_unique(array_filter(array_column($unresolved, 'district_id')));
        $districtTypes = $districtIds === [] ? [] : RefDistrict::query()
            ->join($cityTable, "{$cityTable}.city_id", '=', "{$districtTable}.district_city_id")
            ->whereIn("{$districtTable}.district_id", $districtIds)
            ->pluck("{$cityTable}.city_type", "{$districtTable}.district_id")->all();

        foreach ($locations as $location) {
            $type = $location['type']
                ?? $cityTypes[$location['city_id']]
                ?? $subdistrictTypes[$location['subdistrict_id']]
                ?? $districtTypes[$location['district_id']]
                ?? null;
            $target = $location['target'];
            $field = $location['field'];
            $target->{$field} = self::displayName($target->{$field}, $type);
        }

        return $data;
    }

    /** @param list<array<string, mixed>> $locations */
    private function collectLocations(mixed $data, array &$locations): void
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                $this->collectLocations($value, $locations);
            }

            return;
        }
        if (! $data instanceof stdClass) {
            return;
        }

        if (property_exists($data, 'city_name')) {
            $this->addLocation($locations, $data, 'city_name', $data->city_type ?? null,
                $data->city_id ?? null, $data->district_id ?? null, $data->subdistrict_id ?? null);
        }
        if (($data->city ?? null) instanceof stdClass) {
            $this->addLocation($locations, $data->city, 'name', $data->city->type ?? null,
                $data->city->id ?? null, $data->district->id ?? null, $data->subdistrict->id ?? null);
        }
        foreach (get_object_vars($data) as $value) {
            $this->collectLocations($value, $locations);
        }
    }

    /** @param list<array<string, mixed>> $locations */
    private function addLocation(
        array &$locations,
        stdClass $target,
        string $field,
        ?string $type,
        int|string|null $cityId = null,
        int|string|null $districtId = null,
        int|string|null $subdistrictId = null,
    ): void {
        $name = $target->{$field} ?? null;
        if (! is_string($name) || trim($name) === '' || preg_match('/^(Kota|Kabupaten)\s+/i', $name) === 1) {
            return;
        }

        $locations[] = [
            'target' => $target,
            'field' => $field,
            'type' => in_array(strtolower(trim((string) $type)), ['kota', 'kabupaten'], true) ? $type : null,
            'city_id' => $cityId,
            'district_id' => $districtId,
            'subdistrict_id' => $subdistrictId,
        ];
    }
}
