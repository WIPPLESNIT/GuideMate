<?php

require __DIR__ . '/patch_helper.php';

$file = 'app/listing/[slug].tsx';

// 1. Update import
patchFile(
    $file,
    "import { ApiListing, createBooking, getListing, isUnauthorized, PaymentMethod, resolveImage, validateVoucher } from '../../services/api';",
    "import { ApiHotelRoom, ApiListing, createBooking, getListing, isUnauthorized, PaymentMethod, resolveImage, validateVoucher } from '../../services/api';"
);

// 2. Add state
patchFile(
    $file,
    "  const [listing, setListing] = useState<ApiListing | null>(null);",
    "  const [listing, setListing] = useState<ApiListing | null>(null);\n  const [hotelRooms, setHotelRooms] = useState<ApiHotelRoom[]>([]);\n  const [selectedRoom, setSelectedRoom] = useState<ApiHotelRoom | null>(null);"
);

// 3. Update load()
patchFile(
    $file,
    "      setListing(data.listing);\n      setFavorited(!!data.favorited);",
    "      setListing(data.listing);\n      const r = Array.isArray(data.rooms) ? data.rooms : [];\n      setHotelRooms(r);\n      if (r.length > 0) {\n        setSelectedRoom(r.find((x) => x.status === 'available') || r[0]);\n      }\n      setFavorited(!!data.favorited);"
);

// 4. Update pricing & isHotel
$oldPricing = "  const daysCount = Math.max(1, bookingDates.length);\n  const subtotal = listing ? listing.price * guests * daysCount : 0;\n  const appliedVoucher = voucherCode ? findVoucher(voucherCode) : null;\n  const voucherDiscount = appliedVoucher ? getVoucherDiscount(appliedVoucher, subtotal) : 0;\n  const totalAmount = Math.max(0, subtotal - voucherDiscount);";

$newPricing = "  const isHotel = listing?.category === 'Hotels' || (listing as any)?.category_slug === 'hotels';\n  const daysCount = Math.max(1, bookingDates.length);\n  const roomPrice = isHotel && selectedRoom ? selectedRoom.price_per_night : (listing ? listing.price : 0);\n  const hotelTotal = roomPrice * daysCount;\n  const depositPct = isHotel && selectedRoom ? (selectedRoom.deposit_percentage ?? 20) : 100;\n  const hotelDeposit = Math.round((hotelTotal * depositPct) / 100);\n  const hotelBalance = Math.max(0, hotelTotal - hotelDeposit);\n  const subtotal = isHotel ? hotelTotal : (listing ? listing.price * guests * daysCount : 0);\n  const appliedVoucher = voucherCode ? findVoucher(voucherCode) : null;\n  const voucherDiscount = appliedVoucher ? getVoucherDiscount(appliedVoucher, subtotal) : 0;\n  const totalAmount = isHotel ? hotelDeposit : Math.max(0, subtotal - voucherDiscount);";

patchFile($file, $oldPricing, $newPricing);

// 5. Update goToPayment
$oldGoToPayment = "  const goToPayment = () => {\n    if (bookingDates.length === 0) {\n      Alert.alert('Pick date(s)', 'Please choose one or more dates from the calendar.');\n      return;\n    }\n    if (!bookingTime) {\n      Alert.alert('Pick a start time', 'Please choose a start time for your experience.');\n      return;\n    }\n    if (!bookingEndTime) {\n      Alert.alert('Pick an end time', 'Please choose an end time for your experience.');\n      return;\n    }\n    if (bookingEndTime <= bookingTime) {\n      Alert.alert('Invalid time', 'End time must be after the start time.');\n      return;\n    }\n    // Free listings skip the payment step entirely.\n    if (totalAmount <= 0) {\n      submitBooking('card');\n      return;\n    }\n    setStep('method');\n  };";

$newGoToPayment = "  const goToPayment = () => {\n    if (bookingDates.length === 0) {\n      Alert.alert('Pick date(s)', isHotel ? 'Please select your check-in and check-out dates.' : 'Please choose one or more dates from the calendar.');\n      return;\n    }\n    if (!isHotel) {\n      if (!bookingTime) {\n        Alert.alert('Pick a start time', 'Please choose a start time for your experience.');\n        return;\n      }\n      if (!bookingEndTime) {\n        Alert.alert('Pick an end time', 'Please choose an end time for your experience.');\n        return;\n      }\n      if (bookingEndTime <= bookingTime) {\n        Alert.alert('Invalid time', 'End time must be after the start time.');\n        return;\n      }\n    }\n    // Free listings skip the payment step entirely.\n    if (totalAmount <= 0) {\n      submitBooking('card');\n      return;\n    }\n    setStep('method');\n  };";

patchFile($file, $oldGoToPayment, $newGoToPayment);

// 6. Update submitBooking
$oldSubmit = "      await createBooking({\n        listing_id: listing.id,\n        booking_date: bookingDates[0],\n        booking_dates: bookingDates,\n        booking_time: bookingTime,\n        booking_end_time: bookingEndTime,\n        guests,\n        payment_method: method,\n        payment_reference: reference,\n        card_last4: cardLast4,\n        promo_code: voucherDiscount > 0 && appliedVoucher ? appliedVoucher.code : undefined,\n      });";

$newSubmit = "      await createBooking({\n        listing_id: listing.id,\n        room_id: isHotel ? selectedRoom?.id : undefined,\n        check_in_date: isHotel ? bookingDates[0] : undefined,\n        check_out_date: isHotel ? (bookingDates[bookingDates.length - 1] || bookingDates[0]) : undefined,\n        booking_date: bookingDates[0],\n        booking_dates: bookingDates,\n        booking_time: isHotel ? undefined : bookingTime,\n        booking_end_time: isHotel ? undefined : bookingEndTime,\n        guests,\n        payment_method: method,\n        payment_reference: reference,\n        card_last4: cardLast4,\n        promo_code: voucherDiscount > 0 && appliedVoucher ? appliedVoucher.code : undefined,\n      });";

patchFile($file, $oldSubmit, $newSubmit);

echo "Mobile listing patched.\n";
