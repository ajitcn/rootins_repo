<?php

namespace Admin\Models\Location;

use CodeIgniter\Model;
use InvalidArgumentException;
use Throwable;

class TelephoneCountryCode extends Model
{
    protected $table = 'telephone_country_codes';

    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $protectFields = true;

    protected $allowedFields = [
        'country_id',
        'code',
        'normalized_code',
        'label',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dropdown(): array
    {
        return $this->select(
            'telephone_country_codes.id, telephone_country_codes.country_id, '
            . 'telephone_country_codes.normalized_code, '
            . 'COALESCE(countries.name, telephone_country_codes.label) AS name, '
            . 'COALESCE(countries.name, telephone_country_codes.label) AS country_name',
        )
            ->join('countries', 'countries.id = telephone_country_codes.country_id', 'left')
            ->where('telephone_country_codes.status', 1)
            ->orderBy('name')
            ->findAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeByCode(string $code): ?array
    {
        $normalized = self::normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        return $this->where('normalized_code', $normalized)
            ->where('status', 1)
            ->first();
    }

    public static function normalizeCode(string $value): ?string
    {
        $normalized = preg_replace('/^\\+/', '', trim($value));

        return is_string($normalized)
            && preg_match('/^[1-9][0-9]{0,3}$/', $normalized)
            ? $normalized
            : null;
    }

    /**
     * Resolve an existing code or create a reusable code record.
     *
     * @return array<string, mixed>
     */
    public function resolveOrCreate(string $code, ?int $countryId = null, ?string $countryName = null): array
    {
        $normalized = self::normalizeCode($code);

        if ($normalized === null) {
            throw new InvalidArgumentException('A valid country code is required.');
        }

        $countryName = self::normalizeCountryName($countryName);
        $countryId = $countryId ?: $this->countryIdByName($countryName);

        $existing = $this->activeByCode($normalized);

        if ($existing !== null) {
            $existing = $this->enrichExisting($existing, $countryId, $countryName);

            return $existing;
        }

        try {
            $this->insert([
                'country_id' => $countryId,
                'code' => $normalized,
                'normalized_code' => $normalized,
                'label' => $countryName ?: 'Telephone code +' . $normalized,
                'status' => 1,
            ]);
        } catch (Throwable $e) {
            $existing = $this->activeByCode($normalized);

            if ($existing !== null) {
                return $this->enrichExisting($existing, $countryId, $countryName);
            }

            throw new InvalidArgumentException(
                'The country code could not be saved safely.',
                0,
                $e,
            );
        }

        $created = $this->activeByCode($normalized);

        if ($created === null) {
            throw new InvalidArgumentException('The country code could not be saved safely.');
        }

        return $created;
    }

    public static function normalizeCountryName(?string $value): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $name === '' ? null : substr($name, 0, 191);
    }

    /**
     * Attach a matching country row where possible, while keeping the
     * telephone-code record usable for countries absent from the master.
     *
     * @param array<string, mixed> $existing
     *
     * @return array<string, mixed>
     */
    private function enrichExisting(array $existing, ?int $countryId, ?string $countryName): array
    {
        $changes = [];

        if (empty($existing['country_id']) && $countryId !== null) {
            $changes['country_id'] = $countryId;
        }

        if (empty($existing['country_id']) && $countryName !== null && ($existing['label'] ?? '') !== $countryName) {
            $changes['label'] = $countryName;
        }

        if ($changes !== []) {
            if ($this->update((int) $existing['id'], $changes) === false) {
                throw new InvalidArgumentException('The country code could not be saved safely.');
            }
            $existing = $this->find((int) $existing['id']) ?? $existing;
        }

        return $existing;
    }

    private function countryIdByName(?string $countryName): ?int
    {
        if ($countryName === null) {
            return null;
        }

        $row = $this->db->table('countries')
            ->where('normalized_name', strtolower($countryName))
            ->where('status', 1)
            ->get()
            ->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }
}
