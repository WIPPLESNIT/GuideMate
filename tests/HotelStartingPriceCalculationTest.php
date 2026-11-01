<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Controllers\ApiController;

final class HotelStartingPriceCalculationTest
{
    private array $cleanUpListingIds = [];

    public function run(): void
    {
        echo "=== Running Hotel Starting Price Calculation & Sync Tests ===\n";

        try {
            $this->testEmptyHotelHasNullStartingPrice();
            $this->testAddingRoomsCalculatesLowestPrice();
            $this->testUpdatingRoomPriceSyncsHotelPrice();
            $this->testRoomStatusChangeAdjustsStartingPrice();
            $this->testDeletingCheapestRoomSyncsStartingPrice();
            $this->testDeletingAllRoomsResetsStartingPrice();
            $this->testWebListingCardFormatting();
            $this->testWebListingDetailFormatting();
            $this->testApiListingPayloadLowestRoomPrice();
            $this->testHotelFormDoesNotRequireManualPrice();

            echo "\n✅ ALL HOTEL STARTING PRICE TESTS PASSED!\n";
        } finally {
            $this->cleanup();
        }
    }

    private function createTestHotel(string $title): int
    {
        $admin = Database::first("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $admin ? (int) $admin['id'] : 1;

        $id = Listing::create([
            'user_id' => $adminId,
            'category_id' => 3, // Hotels
            'title' => $title,
            'slug' => 'test-hotel-' . bin2hex(random_bytes(4)),
            'summary' => 'Test Hotel Summary',
            'description' => 'Test Hotel Description for pricing logic verification',
            'area' => 'Moalboal, Cebu',
            'address' => 'Panagsama Beach',
            'price' => 0.0,
            'price_unit' => 'per night',
            'duration' => 'Check-in 2:00 PM / Check-out 12:00 PM',
            'included' => 'WiFi, Pool',
            'not_included' => 'Airport transfer',
            'status' => 'approved',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->cleanUpListingIds[] = $id;
        return $id;
    }

    private function testEmptyHotelHasNullStartingPrice(): void
    {
        echo "- testEmptyHotelHasNullStartingPrice...";
        $hotelId = $this->createTestHotel("Empty Test Hotel");

        $lowest = HotelRoom::getLowestRoomPrice($hotelId);
        $this->assert($lowest === null, "Empty hotel should have null lowest room price");

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 0.0, "Empty hotel price in DB should be 0.0");
        echo " OK\n";
    }

    private function testAddingRoomsCalculatesLowestPrice(): void
    {
        echo "- testAddingRoomsCalculatesLowestPrice...";
        $hotelId = $this->createTestHotel("Multi Room Hotel");

        // Add first room: ₱3,500
        $room1Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Deluxe Suite',
            'price_per_night' => 3500.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 3500.00, "Hotel price should be 3500 after adding first room");

        // Add second room: ₱2,100 (cheaper)
        $room2Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Standard Queen',
            'price_per_night' => 2100.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 2100.00, "Hotel price should update to 2100 when cheaper room added");

        // Add third room: ₱4,800 (more expensive)
        $room3Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Penthouse Villa',
            'price_per_night' => 4800.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 2100.00, "Hotel price should remain 2100 after adding more expensive room");
        echo " OK\n";
    }

    private function testUpdatingRoomPriceSyncsHotelPrice(): void
    {
        echo "- testUpdatingRoomPriceSyncsHotelPrice...";
        $hotelId = $this->createTestHotel("Update Room Price Hotel");

        $room1Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Room A',
            'price_per_night' => 2000.00,
            'status' => 'available',
        ]);
        $room2Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Room B',
            'price_per_night' => 3000.00,
            'status' => 'available',
        ]);

        // Update Room A price from 2000 to 3500. Now Room B (3000) should be the lowest.
        HotelRoom::update($room1Id, [
            'name' => 'Room A',
            'price_per_night' => 3500.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 3000.00, "Hotel price should update to 3000 after Room A price increased above Room B");

        // Update Room A price down to 1800. Room A should become lowest again.
        HotelRoom::update($room1Id, [
            'name' => 'Room A',
            'price_per_night' => 1800.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 1800.00, "Hotel price should update to 1800 after Room A discounted");
        echo " OK\n";
    }

    private function testRoomStatusChangeAdjustsStartingPrice(): void
    {
        echo "- testRoomStatusChangeAdjustsStartingPrice...";
        $hotelId = $this->createTestHotel("Room Status Hotel");

        $room1Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Cheap Promo Room',
            'price_per_night' => 1500.00,
            'status' => 'available',
        ]);
        $room2Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Standard Room',
            'price_per_night' => 2500.00,
            'status' => 'available',
        ]);

        $this->assert((float) Listing::find($hotelId)['price'] === 1500.00, "Initial lowest available room price should be 1500");

        // Mark cheap room as unavailable
        HotelRoom::updateStatus($room1Id, 'unavailable');

        // Hotel price should now reflect the lowest AVAILABLE room (2500)
        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 2500.00, "Hotel price should update to 2500 when cheaper room marked unavailable");

        // Mark cheap room back to available
        HotelRoom::updateStatus($room1Id, 'available');
        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 1500.00, "Hotel price should revert to 1500 when cheaper room marked available again");
        echo " OK\n";
    }

    private function testDeletingCheapestRoomSyncsStartingPrice(): void
    {
        echo "- testDeletingCheapestRoomSyncsStartingPrice...";
        $hotelId = $this->createTestHotel("Delete Room Hotel");

        $room1Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Budget Room',
            'price_per_night' => 1200.00,
            'status' => 'available',
        ]);
        $room2Id = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Ocean Suite',
            'price_per_night' => 2800.00,
            'status' => 'available',
        ]);

        $this->assert((float) Listing::find($hotelId)['price'] === 1200.00, "Price before delete should be 1200");

        // Delete budget room
        HotelRoom::delete($room1Id);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 2800.00, "Hotel price should update to 2800 after deleting budget room");
        echo " OK\n";
    }

    private function testDeletingAllRoomsResetsStartingPrice(): void
    {
        echo "- testDeletingAllRoomsResetsStartingPrice...";
        $hotelId = $this->createTestHotel("Delete All Rooms Hotel");

        HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Room 1',
            'price_per_night' => 1900.00,
            'status' => 'available',
        ]);

        $this->assert((float) Listing::find($hotelId)['price'] === 1900.00, "Price with room should be 1900");

        HotelRoom::deleteForListing($hotelId);

        $listing = Listing::find($hotelId);
        $this->assert((float) $listing['price'] === 0.0, "Hotel price should be reset to 0.0 after deleteForListing");
        $this->assert(HotelRoom::getLowestRoomPrice($hotelId) === null, "Lowest room price should be null after deleteForListing");
        echo " OK\n";
    }

    private function testWebListingCardFormatting(): void
    {
        echo "- testWebListingCardFormatting...";

        // Test with price available
        $hotelId = $this->createTestHotel("Card View Hotel");
        HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Deluxe Room',
            'price_per_night' => 2450.00,
            'status' => 'available',
        ]);

        $l = Listing::find($hotelId);
        $l['category_slug'] = 'hotels';
        $l['category_name'] = 'Hotels';

        ob_start();
        include __DIR__ . '/../app/Views/partials/listing-card.php';
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'Lowest price room'), "Card HTML must contain 'Lowest price room'");
        $this->assert(str_contains($html, '/ per night'), "Card HTML must contain '/ per night'");
        $this->assert(str_contains($html, '2,450'), "Card HTML must contain formatted price 2,450");

        // Test with price unavailable (no rooms)
        $emptyHotelId = $this->createTestHotel("Empty Card Hotel");
        $lEmpty = Listing::find($emptyHotelId);
        $lEmpty['category_slug'] = 'hotels';
        $lEmpty['category_name'] = 'Hotels';

        ob_start();
        $l = $lEmpty;
        include __DIR__ . '/../app/Views/partials/listing-card.php';
        $emptyHtml = ob_get_clean();

        $this->assert(str_contains($emptyHtml, 'Price unavailable'), "Card HTML for hotel without rooms must show 'Price unavailable'");
        echo " OK\n";
    }

    private function testWebListingDetailFormatting(): void
    {
        echo "- testWebListingDetailFormatting...";

        $hotelId = $this->createTestHotel("Detail View Hotel");
        HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Sunrise Suite',
            'price_per_night' => 3100.00,
            'status' => 'available',
        ]);

        $listing = Listing::find($hotelId);
        $listing['category_slug'] = 'hotels';
        $listing['category_name'] = 'Hotels';
        $listing['owner_name'] = 'Test Owner';
        $listing['owner_role'] = 'hotel';

        $lowestRoomPrice = HotelRoom::getLowestRoomPrice($hotelId);
        $isHotel = true;

        // Verify lowest room price logic matches requirements
        $this->assert($lowestRoomPrice === 3100.0, "Detail lowest room price must be 3100");

        // Test detail booking box format
        $priceHtml = '';
        if ($isHotel) {
            if ($lowestRoomPrice !== null && $lowestRoomPrice > 0) {
                $priceHtml = '<span class="price-prefix">Starting from</span> <span>' . money($lowestRoomPrice) . '</span> <span class="price-unit">/ per night</span>';
            } else {
                $priceHtml = '<span>Price unavailable</span>';
            }
        }

        $this->assert(str_contains($priceHtml, 'Starting from'), "Detail price must contain 'Starting from'");
        $this->assert(str_contains($priceHtml, '/ per night'), "Detail price must contain '/ per night'");
        $this->assert(str_contains($priceHtml, '3,100'), "Detail price must contain lowest room price 3,100");

        // Test price unavailable fallback
        $emptyLowest = HotelRoom::getLowestRoomPrice($this->createTestHotel("Empty Detail Hotel"));
        $this->assert($emptyLowest === null, "Empty hotel lowest room price must be null");
        echo " OK\n";
    }

    private function testApiListingPayloadLowestRoomPrice(): void
    {
        echo "- testApiListingPayloadLowestRoomPrice...";

        $hotelId = $this->createTestHotel("API Hotel");
        HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Garden Bungalow',
            'price_per_night' => 1950.00,
            'status' => 'available',
        ]);

        $rawListing = Listing::find($hotelId);
        $rawListing['category_slug'] = 'hotels';
        $rawListing['category_name'] = 'Hotels';

        $api = new ApiController();
        $ref = new ReflectionClass(ApiController::class);
        $method = $ref->getMethod('listingPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($api, $rawListing, false);

        $this->assert($payload['price'] === 1950.0, "API listing price should equal lowest room price (1950.0)");
        $this->assert($payload['lowest_room_price'] === 1950.0, "API lowest_room_price should be 1950.0");
        $this->assert($payload['price_unit'] === 'per night', "API price_unit should be 'per night'");
        echo " OK\n";
    }

    private function testHotelFormDoesNotRequireManualPrice(): void
    {
        echo "- testHotelFormDoesNotRequireManualPrice...";

        // Ensure validateHotelData does not complain when price is omitted
        $controller = new \App\Controllers\HotelController();
        $ref = new ReflectionClass(\App\Controllers\HotelController::class);
        $method = $ref->getMethod('validateHotelData');
        $method->setAccessible(true);

        $_POST = [
            'title' => 'Form Test Hotel',
            'area' => 'Badian, Cebu',
            'description' => 'A wonderful resort by the sea with full amenities.',
            'duration' => 'Check-in 2:00 PM / Check-out 12:00 PM',
            // Notice: NO 'price' field provided!
        ];

        $data = $method->invoke($controller, false);
        $this->assert($data['title'] === 'Form Test Hotel', "validateHotelData should succeed without price field");
        $this->assert(!isset($data['price']), "validateHotelData output should not contain price");
        echo " OK\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException("Assertion failed: {$message}");
        }
    }

    private function cleanup(): void
    {
        foreach ($this->cleanUpListingIds as $id) {
            try {
                Database::run("DELETE FROM hotel_rooms WHERE listing_id = ?", [$id]);
                Database::run("DELETE FROM listings WHERE id = ?", [$id]);
            } catch (Throwable $e) {
                // Ignore cleanup errors
            }
        }
    }
}

$test = new HotelStartingPriceCalculationTest();
$test->run();
