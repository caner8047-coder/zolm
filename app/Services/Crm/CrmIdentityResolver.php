<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\CrmContactIdentity;
use Illuminate\Support\Str;

class CrmIdentityResolver
{
    /**
     * @param array{
     *     user_id:int,
     *     store_id?:int|null,
     *     marketplace?:string|null,
     *     source_type:string,
     *     external_customer_id?:string|null,
     *     name?:string|null,
     *     email?:string|null,
     *     phone?:string|null,
     *     tax_number?:string|null,
     *     city?:string|null,
     *     district?:string|null,
     *     confidence?:float|int|null,
     *     raw_payload?:array|null
     * } $data
     */
    public function resolve(array $data): CrmContact
    {
        $userId = (int) $data['user_id'];
        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;
        $sourceType = trim((string) ($data['source_type'] ?? 'manual'));
        $externalCustomerId = $this->cleanNullable($data['external_customer_id'] ?? null);
        $name = $this->cleanNullable($data['name'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $phone = $this->cleanNullable($data['phone'] ?? null);
        $normalizedPhone = $this->normalizePhone($phone);
        $taxNumber = $this->normalizeTaxNumber($data['tax_number'] ?? null);
        $city = $this->cleanNullable($data['city'] ?? null);
        $district = $this->cleanNullable($data['district'] ?? null);
        $normalizedName = $this->normalizeName($name);

        $contact = $this->findExistingContact(
            $userId,
            $storeId,
            $sourceType,
            $externalCustomerId,
            $email,
            $normalizedPhone,
            $taxNumber,
            $normalizedName,
            $city,
        );

        if (!$contact) {
            $contact = CrmContact::create([
                'user_id' => $userId,
                'display_name' => $name ?: 'İsimsiz Müşteri',
                'normalized_name' => $normalizedName,
                'primary_email' => $email,
                'primary_phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'billing_tax_number' => $taxNumber,
                'city' => $city,
                'district' => $district,
                'status' => 'active',
            ]);
        } else {
            $contact->fill(array_filter([
                'display_name' => $contact->display_name === 'İsimsiz Müşteri' && $name ? $name : null,
                'normalized_name' => $contact->normalized_name ?: $normalizedName,
                'primary_email' => $contact->primary_email ?: $email,
                'primary_phone' => $contact->primary_phone ?: $phone,
                'normalized_phone' => $contact->normalized_phone ?: $normalizedPhone,
                'billing_tax_number' => $contact->billing_tax_number ?: $taxNumber,
                'city' => $contact->city ?: $city,
                'district' => $contact->district ?: $district,
            ], fn ($value) => $value !== null && $value !== ''));

            if ($contact->isDirty()) {
                $contact->save();
            }
        }

        $this->upsertIdentity($contact, [
            'user_id' => $userId,
            'store_id' => $storeId,
            'marketplace' => $this->cleanNullable($data['marketplace'] ?? null),
            'source_type' => $sourceType,
            'external_customer_id' => $externalCustomerId,
            'email' => $email,
            'phone' => $phone,
            'normalized_phone' => $normalizedPhone,
            'name' => $name,
            'normalized_name' => $normalizedName,
            'tax_number' => $taxNumber,
            'city' => $city,
            'district' => $district,
            'confidence' => (float) ($data['confidence'] ?? 0),
            'raw_payload' => $data['raw_payload'] ?? null,
        ]);

        return $contact->fresh();
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public function normalizeName(?string $name): ?string
    {
        $clean = $this->cleanNullable($name);

        return $clean ? (string) Str::of($clean)->lower()->squish() : null;
    }

    protected function findExistingContact(
        int $userId,
        ?int $storeId,
        string $sourceType,
        ?string $externalCustomerId,
        ?string $email,
        ?string $normalizedPhone,
        ?string $taxNumber,
        ?string $normalizedName,
        ?string $city,
    ): ?CrmContact {
        if ($externalCustomerId) {
            $identity = CrmContactIdentity::query()
                ->with('contact')
                ->where('user_id', $userId)
                ->where('source_type', $sourceType)
                ->where('external_customer_id', $externalCustomerId)
                ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
                ->first();

            if ($identity?->contact) {
                return $identity->contact;
            }
        }

        if ($normalizedPhone) {
            $contact = CrmContact::query()
                ->where('user_id', $userId)
                ->where('normalized_phone', $normalizedPhone)
                ->first();

            if ($contact) {
                return $contact;
            }

            $identity = CrmContactIdentity::query()
                ->with('contact')
                ->where('user_id', $userId)
                ->where('normalized_phone', $normalizedPhone)
                ->first();

            if ($identity?->contact) {
                return $identity->contact;
            }
        }

        if ($email) {
            $contact = CrmContact::query()
                ->where('user_id', $userId)
                ->where('primary_email', $email)
                ->first();

            if ($contact) {
                return $contact;
            }

            $identity = CrmContactIdentity::query()
                ->with('contact')
                ->where('user_id', $userId)
                ->where('email', $email)
                ->first();

            if ($identity?->contact) {
                return $identity->contact;
            }
        }

        if ($taxNumber) {
            $contact = CrmContact::query()
                ->where('user_id', $userId)
                ->where('billing_tax_number', $taxNumber)
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        if ($normalizedName && $city) {
            return CrmContact::query()
                ->where('user_id', $userId)
                ->where('normalized_name', $normalizedName)
                ->where('city', $city)
                ->first();
        }

        return null;
    }

    protected function upsertIdentity(CrmContact $contact, array $attributes): void
    {
        $lookup = [
            'user_id' => $attributes['user_id'],
            'contact_id' => $contact->id,
            'source_type' => $attributes['source_type'],
            'store_id' => $attributes['store_id'],
        ];

        if (!empty($attributes['external_customer_id'])) {
            $lookup = [
                'user_id' => $attributes['user_id'],
                'source_type' => $attributes['source_type'],
                'store_id' => $attributes['store_id'],
                'external_customer_id' => $attributes['external_customer_id'],
            ];
        } elseif (!empty($attributes['normalized_phone'])) {
            $lookup['normalized_phone'] = $attributes['normalized_phone'];
        } elseif (!empty($attributes['email'])) {
            $lookup['email'] = $attributes['email'];
        } elseif (!empty($attributes['normalized_name'])) {
            $lookup['normalized_name'] = $attributes['normalized_name'];
        }

        CrmContactIdentity::updateOrCreate($lookup, array_merge($attributes, [
            'contact_id' => $contact->id,
        ]));
    }

    protected function normalizeEmail(?string $email): ?string
    {
        $clean = $this->cleanNullable($email);

        return $clean ? Str::lower($clean) : null;
    }

    protected function normalizeTaxNumber(?string $taxNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $taxNumber);

        return $digits !== '' ? $digits : null;
    }

    protected function cleanNullable(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
