<?php

namespace Tests\Unit;

use App\Rules\ImageLongEdgeMax;
use App\Rules\ImageLongEdgeMin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ImageUploadRulesTest extends TestCase
{
    public function test_it_rejects_images_below_the_configured_minimum_long_edge(): void
    {
        $file = UploadedFile::fake()->image('small.jpg', 3000, 2000);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new ImageLongEdgeMin(3500)]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('mindestens 3500 Pixel', $validator->errors()->first('image'));
    }

    public function test_it_rejects_images_above_the_configured_maximum_short_edge(): void
    {
        $file = UploadedFile::fake()->image('too-tall.jpg', 7000, 6100);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new ImageLongEdgeMax(8064, 6048)]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('kurzen Kante maximal 6048 Pixel', $validator->errors()->first('image'));
    }

    public function test_it_accepts_images_within_all_limits(): void
    {
        $file = UploadedFile::fake()->image('valid.jpg', 5712, 4284);

        $validator = Validator::make(
            ['image' => $file],
            ['image' => [new ImageLongEdgeMin(3500), new ImageLongEdgeMax(8064, 6048)]]
        );

        $this->assertFalse($validator->fails());
    }
}
