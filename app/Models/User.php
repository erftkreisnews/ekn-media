<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'allowed_delivery_organization_ids',
        'koelnimage_licensed_download',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allowed_delivery_organization_ids' => 'array',
            'koelnimage_licensed_download' => 'boolean',
        ];
    }

    /**
     * Redaktions-/Druckdatei aus Kölnimage-Galerie: nur nach vertraglicher Freigabe
     * (Feld manuell setzen, z. B. nach Rechnungsstellung).
     */
    public function canKoelnimageLicensedDownload(): bool
    {
        return (bool) ($this->koelnimage_licensed_download ?? false);
    }

    /**
     * @return array<int, int>
     */
    public function allowedDeliveryOrganizationIds(): array
    {
        return collect((array) ($this->allowed_delivery_organization_ids ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function hasDeliveryOrganizationRestriction(): bool
    {
        return count($this->allowedDeliveryOrganizationIds()) > 0;
    }

    public function canSendToOrganization(?int $organizationId): bool
    {
        if (! $this->hasDeliveryOrganizationRestriction()) {
            return true;
        }

        if (! $organizationId || $organizationId <= 0) {
            return false;
        }

        return in_array($organizationId, $this->allowedDeliveryOrganizationIds(), true);
    }
}
