<?php

namespace Tests\Unit;

use App\Http\Controllers\PassengerReportController;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class PassengerEvidenceTest extends TestCase
{
    public function test_it_accepts_a_real_base64_image_and_rejects_fake_image_data(): void
    {
        $method = new ReflectionMethod(PassengerReportController::class, 'decodeEvidenceImage');
        $controller = app(PassengerReportController::class);
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        [$bytes, $mimeType] = $method->invoke($controller, $png);

        $this->assertNotSame('', $bytes);
        $this->assertSame('image/png', $mimeType);

        $this->expectException(ValidationException::class);
        $method->invoke($controller, 'data:image/jpeg;base64,'.base64_encode('not an image'));
    }
}
