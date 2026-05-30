<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\NewsItemMedia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaDeliveryVisibilityTest extends TestCase
{
    #[Test]
    public function unrestricted_media_visible_when_unconfirmed(): void
    {
        $delivery = new Delivery;
        $delivery->confirmed_at = null;

        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => null,
        ]);

        $this->assertTrue($media->isVisibleInDeliveryPackage($delivery));
    }

    #[Test]
    public function restricted_media_hidden_until_confirmed(): void
    {
        $delivery = new Delivery;
        $delivery->confirmed_at = null;

        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [42],
        ]);

        $this->assertFalse($media->isVisibleInDeliveryPackage($delivery));
    }

    #[Test]
    public function restricted_media_visible_when_org_matches(): void
    {
        $delivery = new Delivery;
        $delivery->confirmed_at = now();
        $delivery->organization_id = 42;

        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [42, 99],
        ]);

        $this->assertTrue($media->isVisibleInDeliveryPackage($delivery));
    }

    #[Test]
    public function restricted_media_hidden_when_org_mismatch(): void
    {
        $delivery = new Delivery;
        $delivery->confirmed_at = now();
        $delivery->organization_id = 1;

        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [42],
        ]);

        $this->assertFalse($media->isVisibleInDeliveryPackage($delivery));
    }

    #[Test]
    public function restricted_hidden_for_self_reported_only_confirmation(): void
    {
        $delivery = new Delivery;
        $delivery->confirmed_at = now();
        $delivery->organization_id = null;
        $delivery->self_reported_organization_name = 'Freie Redaktion';
        $delivery->self_reported_product_name = 'Online';

        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [42],
        ]);

        $this->assertFalse($media->isVisibleInDeliveryPackage($delivery));
    }

    #[Test]
    public function ftp_includes_all_when_destination_has_no_org(): void
    {
        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [7],
        ]);

        $this->assertTrue($media->isVisibleForFtpDestination(null));
    }

    #[Test]
    public function ftp_filters_by_destination_org(): void
    {
        $media = new NewsItemMedia([
            'versand' => true,
            'delivery_visible_for_organization_ids' => [7, 8],
        ]);

        $this->assertTrue($media->isVisibleForFtpDestination(7));
        $this->assertFalse($media->isVisibleForFtpDestination(99));
    }

    #[Test]
    public function restricted_media_not_on_public_article(): void
    {
        $media = new NewsItemMedia([
            'delivery_visible_for_organization_ids' => [1],
        ]);

        $this->assertFalse($media->isVisibleOnPublicArticle());
    }

    #[Test]
    public function unrestricted_media_on_public_article(): void
    {
        $media = new NewsItemMedia([
            'delivery_visible_for_organization_ids' => null,
        ]);

        $this->assertTrue($media->isVisibleOnPublicArticle());
    }
}
