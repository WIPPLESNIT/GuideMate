<?php

require __DIR__ . '/patch_helper.php';

patchFile(
    'app/Controllers/ListingController.php',
    'use App\Models\Favorite;',
    "use App\Models\Favorite;\nuse App\Models\HotelRoom;"
);

patchFile(
    'app/Controllers/ListingController.php',
    "'isBookable' => Listing::isBookable(\$listing),",
    "'isBookable' => Listing::isBookable(\$listing),\n            'hotelRooms' => HotelRoom::forListing(\$listingId),"
);
