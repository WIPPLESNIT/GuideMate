<?php

require __DIR__ . '/patch_helper.php';

$file = 'app/listing/[slug].tsx';

// 1. Add rooms list right before "About this experience"
$targetAbout = '<Text style={[styles.sectionTitle, { color: theme.textMain }]}>About this experience</Text>';
$roomsUI = '{isHotel && hotelRooms.length > 0 ? (
            <>
              <Text style={[styles.sectionTitle, { color: theme.textMain }]}>Available Rooms & Suites</Text>
              <Text style={[styles.about, { color: theme.textSub, marginBottom: 12 }]}>
                Choose your room. Reserve with a partial deposit now — pay the rest at hotel check-in.
              </Text>
              {hotelRooms.map((room) => {
                const isAvail = room.status === \'available\';
                const isCurrent = selectedRoom?.id === room.id;
                const roomDepPct = room.deposit_percentage ?? 20;
                const roomDepNow = Math.round((room.price_per_night * roomDepPct) / 100);
                const roomBal = room.price_per_night - roomDepNow;

                return (
                  <View
                    key={room.id}
                    style={[
                      styles.roomCard,
                      { backgroundColor: theme.card, borderColor: isCurrent ? theme.accent : theme.border },
                      !isAvail && { opacity: 0.65 },
                    ]}
                  >
                    {room.image ? (
                      <Image source={{ uri: resolveImage(room.image) }} style={styles.roomImage} />
                    ) : null}
                    <View style={styles.roomBody}>
                      <View style={styles.roomHeaderRow}>
                        <Text style={[styles.roomTitle, { color: theme.textMain }]} numberOfLines={1}>
                          {room.name}
                        </Text>
                        <View
                          style={[
                            styles.roomBadge,
                            {
                              backgroundColor:
                                room.status === \'available\'
                                  ? \'#DCFCE7\'
                                  : room.status === \'blocked\'
                                  ? \'#FEF3C7\'
                                  : \'#FEE2E2\',
                            },
                          ]}
                        >
                          <Text
                            style={[
                              styles.roomBadgeText,
                              {
                                color:
                                  room.status === \'available\'
                                    ? \'#166534\'
                                    : room.status === \'blocked\'
                                    ? \'#92400E\'
                                    : \'#991B1B\',
                              },
                            ]}
                          >
                            {room.status === \'available\'
                              ? \'Available\'
                              : room.status === \'blocked\'
                              ? \'Blocked\'
                              : \'Booked\'}
                          </Text>
                        </View>
                      </View>

                      <Text style={[styles.roomSpecs, { color: theme.textSub }]}>
                        {room.bed_type || \'1 King Bed\'} · {room.room_size || \'32 m²\'} · Max {room.max_guests || 2} guests
                      </Text>

                      {room.description ? (
                        <Text style={[styles.roomDesc, { color: theme.textSub }]} numberOfLines={2}>
                          {room.description}
                        </Text>
                      ) : null}

                      <View style={styles.roomBottomRow}>
                        <View>
                          <Text style={[styles.roomPrice, { color: colors.accent }]}>
                            {formatPrice(room.price_per_night)}
                            <Text style={[styles.roomPriceUnit, { color: theme.textSub }]}> / night</Text>
                          </Text>
                          <Text style={styles.roomDepositHint}>
                            {roomDepPct}% deposit ({formatPrice(roomDepNow)}) today · {formatPrice(roomBal)} at check-in
                          </Text>
                        </View>

                        <TouchableOpacity
                          disabled={!isAvail}
                          onPress={() => {
                            setSelectedRoom(room);
                            openBooking();
                          }}
                          style={[
                            styles.roomSelectBtn,
                            { backgroundColor: isAvail ? theme.accent : theme.border },
                          ]}
                        >
                          <Text style={styles.roomSelectBtnText}>
                            {isAvail ? (isCurrent ? \'Selected\' : \'Select\') : \'Unavailable\'}
                          </Text>
                        </TouchableOpacity>
                      </View>
                    </View>
                  </View>
                );
              })}
            </>
          ) : null}

          ' . $targetAbout;

patchFile($file, $targetAbout, $roomsUI);

// 2. Wrap time slots with !isHotel and add hotel room picker in modal
$targetTimeSlots = '<Text style={[styles.modalLabel, { color: theme.textSub }]}>Select start time</Text>';
$wrappedTimeSlots = '{isHotel && hotelRooms.length > 0 ? (
                    <View style={{ marginTop: 12 }}>
                      <Text style={[styles.modalLabel, { color: theme.textSub }]}>Select room</Text>
                      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginHorizontal: -4 }}>
                        {hotelRooms.map((rm) => {
                          const isCur = selectedRoom?.id === rm.id;
                          const isAvail = rm.status === \'available\';
                          return (
                            <TouchableOpacity
                              key={rm.id}
                              disabled={!isAvail}
                              onPress={() => setSelectedRoom(rm)}
                              style={[
                                styles.timeChip,
                                { backgroundColor: theme.inputBg, borderColor: theme.border, marginHorizontal: 4, paddingVertical: 10, paddingHorizontal: 12 },
                                isCur && { backgroundColor: theme.accent, borderColor: theme.accent },
                                !isAvail && { opacity: 0.4 },
                              ]}
                            >
                              <Text style={[styles.timeChipText, { color: isCur ? \'#FFFFFF\' : theme.textMain }]}>
                                {rm.name}
                              </Text>
                              <Text style={[styles.bookedTag, { color: isCur ? \'#FFFFFF\' : theme.textSub }]}>
                                {formatPrice(rm.price_per_night)}/nt
                              </Text>
                            </TouchableOpacity>
                          );
                        })}
                      </ScrollView>
                    </View>
                  ) : null}

                  {!isHotel ? (
                    <>
                      ' . $targetTimeSlots;

patchFile($file, $targetTimeSlots, $wrappedTimeSlots);

// Close the !isHotel block right before Guests label
$targetGuests = '<Text style={[styles.modalLabel, { color: theme.textSub }]}>Guests</Text>';
$closedTimeSlots = '</>
                  ) : null}

                  ' . $targetGuests;

patchFile($file, $targetGuests, $closedTimeSlots);

// 3. Update price summary in modal
$oldPriceBox = '{listing.price > 0 ? (
                    <View style={styles.priceSummaryBox}>
                      <Text style={[styles.totalLine, { color: theme.textMain }]}>
                        {`Total: ${formatPrice(totalAmount)}`}
                      </Text>
                      <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>
                        {bookingDates.length > 1
                          ? `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? \'s\' : \'\'} × ${bookingDates.length} days`
                          : `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? \'s\' : \'\'} (1 day)`}
                      </Text>
                    </View>
                  ) : null}';

$newPriceBox = '{isHotel && selectedRoom ? (
                    <View style={[styles.priceSummaryBox, { backgroundColor: theme.card, padding: 12, borderRadius: 12, borderWidth: 1, borderColor: theme.border }]}>
                      <View style={{ flexDirection: \'row\', justifyContent: \'space-between\', marginBottom: 4 }}>
                        <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>Total Stay ({daysCount} night{daysCount > 1 ? \'s\' : \'\'}):</Text>
                        <Text style={[styles.totalLine, { fontSize: 14, color: theme.textMain }]}>{formatPrice(hotelTotal)}</Text>
                      </View>
                      <View style={{ flexDirection: \'row\', justifyContent: \'space-between\', marginBottom: 4 }}>
                        <Text style={[styles.priceBreakdownText, { color: \'#059669\', fontWeight: \'700\' }]}>Deposit Due Today ({depositPct}%):</Text>
                        <Text style={[styles.totalLine, { color: \'#059669\' }]}>{formatPrice(hotelDeposit)}</Text>
                      </View>
                      <View style={{ flexDirection: \'row\', justifyContent: \'space-between\', borderTopWidth: 1, borderTopColor: theme.border, paddingTop: 6, marginTop: 4 }}>
                        <Text style={[styles.priceBreakdownText, { color: \'#D97706\', fontWeight: \'700\' }]}>Balance at Hotel Check-in:</Text>
                        <Text style={[styles.totalLine, { fontSize: 14, color: \'#D97706\' }]}>{formatPrice(hotelBalance)}</Text>
                      </View>
                      <Text style={[styles.slotHint, { color: theme.textSub, marginTop: 6 }]}>
                        🔒 Free cancellation up to 48 hours before check-in. Remaining balance is paid at the hotel front desk upon arrival.
                      </Text>
                    </View>
                  ) : listing.price > 0 ? (
                    <View style={styles.priceSummaryBox}>
                      <Text style={[styles.totalLine, { color: theme.textMain }]}>
                        {`Total: ${formatPrice(totalAmount)}`}
                      </Text>
                      <Text style={[styles.priceBreakdownText, { color: theme.textSub }]}>
                        {bookingDates.length > 1
                          ? `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? \'s\' : \'\'} × ${bookingDates.length} days`
                          : `${formatPrice(listing.price)} × ${guests} guest${guests > 1 ? \'s\' : \'\'} (1 day)`}
                      </Text>
                    </View>
                  ) : null}';

patchFile($file, $oldPriceBox, $newPriceBox);

// 4. Update sticky bottom bar button label
$oldBookBtnText = '<Text style={styles.bookButtonText}>Book now</Text>';
$newBookBtnText = '<Text style={styles.bookButtonText}>{isHotel ? \'Reserve room\' : \'Book now\'}</Text>';
patchFile($file, $oldBookBtnText, $newBookBtnText);

// 5. Add styles at bottom
$oldEndStyle = "  voucherHint: { fontSize: 12, lineHeight: 17, paddingHorizontal: 12, paddingBottom: 12 },\n});";
$newEndStyle = "  voucherHint: { fontSize: 12, lineHeight: 17, paddingHorizontal: 12, paddingBottom: 12 },
  roomCard: { borderRadius: 16, borderWidth: 1, marginBottom: 14, overflow: 'hidden' },
  roomImage: { width: '100%', height: 160, backgroundColor: '#E2E8F0' },
  roomBody: { padding: 14 },
  roomHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  roomTitle: { fontSize: 16, fontWeight: '800', flex: 1, marginRight: 8 },
  roomBadge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6 },
  roomBadgeText: { fontSize: 11, fontWeight: '700' },
  roomSpecs: { fontSize: 13, marginTop: 4, marginBottom: 6 },
  roomDesc: { fontSize: 13, lineHeight: 18, marginBottom: 10 },
  roomBottomRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', borderTopWidth: 1, borderTopColor: '#F1F5F9', paddingTop: 10 },
  roomPrice: { fontSize: 16, fontWeight: '800' },
  roomPriceUnit: { fontSize: 12, fontWeight: '500' },
  roomDepositHint: { fontSize: 11, color: '#059669', fontWeight: '600', marginTop: 2 },
  roomSelectBtn: { borderRadius: 10, paddingVertical: 8, paddingHorizontal: 16 },
  roomSelectBtnText: { color: '#FFFFFF', fontSize: 13, fontWeight: '700' },
});";

patchFile($file, $oldEndStyle, $newEndStyle);

echo "Rooms UI patched in mobile listing.\n";
